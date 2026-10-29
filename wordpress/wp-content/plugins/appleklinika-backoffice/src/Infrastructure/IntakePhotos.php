<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Private temporary decoding followed by ordinary WP attachments. No source HEIC/EXIF is published. */
final class IntakePhotos implements \Appleklinika\BackOffice\Application\Port\DevicePhotos
{
    public const MAX_FILES = 12;
    public const MAX_BYTES = 20971520;
    public const MAX_TOTAL_BYTES = 104857600;

    public function ingest(array $files, string $operation): array
    {
        if (!$files || count($files) > self::MAX_FILES) { throw new \InvalidArgumentException('Válassz 1–12 termékfotót.'); }
        if (!class_exists('Imagick')) { throw new \RuntimeException('A képfeldolgozó nem érhető el. Kérd a rendszergazda segítségét.'); }
        $total = 0;
        foreach ($files as $file) {
            $source = $file['tmp_name'] ?? '';
            if (is_string($source) && is_uploaded_file($source)) { $total += (int)filesize($source); }
        }
        if ($total > self::MAX_TOTAL_BYTES) { throw new \InvalidArgumentException('A fotók összes mérete legfeljebb 100 MB lehet. Válassz kevesebb képet.'); }
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        $ids = [];
        try {
            foreach ($files as $file) { $ids[] = $this->one($file, $operation); }
            return $ids;
        } catch (\Throwable $error) {
            $this->remove($ids);
            throw $error;
        }
    }

    private function one(array $file, string $operation): int
    {
        $source = $file['tmp_name'] ?? '';
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($source) || !is_uploaded_file($source)) {
            throw new \InvalidArgumentException('A fotó feltöltése nem sikerült. Válaszd ki újra; a fájl legfeljebb 20 MB lehet.');
        }
        if (filesize($source) < 12 || filesize($source) > self::MAX_BYTES) { throw new \InvalidArgumentException('Egy fotó legfeljebb 20 MB lehet; üres fájl nem tölthető fel.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/heic','image/heif'], true)) { throw new \InvalidArgumentException('Csak valódi JPEG, PNG, WebP, HEIC vagy HEIF kép tölthető fel.'); }
        $tmp = tempnam(sys_get_temp_dir(), 'akbo-photo-');
        if (!$tmp) { throw new \RuntimeException('Nincs szabad ideiglenes tárhely a fotók feldolgozásához.'); }
        $image = null; $id = 0; $uploaded = '';
        try {
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_THREAD, 1);
            $image = new \Imagick();
            $image->pingImage($source);
            if ($image->getImageWidth() * $image->getImageHeight() > 60000000 || $image->getNumberImages() > 8) { throw new \InvalidArgumentException('A fotó túl nagy. Legfeljebb 60 megapixeles állókép tölthető fel.'); }
            $image->clear();
            $image->readImage($source.'[0]');
            if (!in_array(strtoupper($image->getImageFormat()), ['JPEG','PNG','WEBP','HEIC','HEIF'], true)) { throw new \InvalidArgumentException('A fájl tartalma nem támogatott kép.'); }
            $image->autoOrient();
            $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            if (max($image->getImageWidth(), $image->getImageHeight()) > 2400) { $image->thumbnailImage(2400, 2400, true); }
            $image->stripImage();
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            $format = $mime === 'image/png' ? 'png' : 'jpg';
            $image->setImageFormat($format);
            $image->setImageCompressionQuality(86);
            $image->writeImage($tmp);
            $sideload = ['name'=>'akbo-device-'.wp_generate_uuid4().'.'.$format, 'tmp_name'=>$tmp, 'error'=>0, 'size'=>filesize($tmp)];
            $upload = wp_handle_sideload($sideload, ['test_form'=>false, 'mimes'=>['jpg'=>'image/jpeg','png'=>'image/png']]);
            if (isset($upload['error'])) { throw new \RuntimeException('A feldolgozott fotó mentése nem sikerült. Ellenőrizd a szabad tárhelyet.'); }
            $uploaded = $upload['file'];
            $result = wp_insert_attachment(['post_title'=>'Készülékfotó', 'post_mime_type'=>$upload['type'], 'post_status'=>'inherit', 'meta_input'=>['_akbo_intake_upload'=>$operation]], $uploaded, 0, true);
            if (is_wp_error($result)) { throw new \RuntimeException('A fotó médiatári mentése nem sikerült.'); }
            $id = (int)$result;
            // Save ordinary responsive variants; the shared processor clears public image_meta too.
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $uploaded));
            (new \Appleklinika\Inventory\Infrastructure\WordPress\ProductPhotoProcessor())->sanitizeAttachment($id);
            return $id;
        } catch (\Throwable $error) {
            if ($id) { wp_delete_attachment($id, true); }
            elseif ($uploaded && is_file($uploaded)) { wp_delete_file($uploaded); }
            if ($error instanceof \InvalidArgumentException || $error instanceof \RuntimeException && !$error instanceof \ImagickException) { throw $error; }
            throw new \RuntimeException('A fotót nem sikerült feldolgozni. Próbáld újra az eredeti képpel; HEIC/HEIF hibánál kérd a rendszergazda segítségét.');
        } finally {
            $image?->clear();
            if (is_file($tmp)) { unlink($tmp); }
            // PHP also removes upload temporary files at request end. Never retain the private source.
            if (is_uploaded_file($source)) { unlink($source); }
        }
    }

    public function remove(array $ids): void
    {
        foreach ($ids as $id) { if (get_post_meta($id, '_akbo_intake_upload', true) !== '' && !(int)wp_get_post_parent_id($id)) { wp_delete_attachment($id, true); } }
    }

    public function cleanupAbandoned(): void
    {
        // Only this workflow's unattached media, older than one day. Bounded and cron-safe.
        $ids = get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_parent'=>0,'fields'=>'ids','numberposts'=>100,
            'meta_key'=>'_akbo_intake_upload','date_query'=>[['before'=>gmdate('Y-m-d H:i:s', time()-DAY_IN_SECONDS), 'column'=>'post_date_gmt']]]);
        $this->remove($ids);
    }
}
