<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;
final class ProductPhotoProcessor
{
    private function clean(string $file): void
    {
        $base=realpath(wp_upload_dir()['basedir']);$path=realpath($file);
        if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path))throw new \RuntimeException('A fotó nem a WordPress médiatárában található.');
        $tool=getenv('AK_PHOTO_METADATA_TOOL')?:'/usr/bin/exiftool';
        if(!is_executable($tool))throw new \RuntimeException('A biztonságos fotófeldolgozó nincs telepítve. A képet ne publikáld.');
        $process=proc_open([$tool,'-overwrite_original','-all=','-tagsFromFile','@','-ICC_Profile','-Orientation','--',$path],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new \RuntimeException('A fotó privát adatainak eltávolítása nem indult el.');
        fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new \RuntimeException('A fotó privát adatainak eltávolítása sikertelen.');
    }
    public function sanitizeMetadata(array $metadata,int $id): array
    {
        $file=get_attached_file($id);$files=[$file];
        foreach(($metadata['sizes']??[]) as $size)if(!empty($size['file']))$files[]=dirname($file).'/'.basename($size['file']);
        if(!empty($metadata['original_image']))$files[]=dirname($file).'/'.basename($metadata['original_image']);
        foreach((array)get_post_meta($id,'_wp_attachment_backup_sizes',true) as $size)if(!empty($size['file']))$files[]=dirname($file).'/'.basename($size['file']);
        $known=(array)get_post_meta($id,'_appleklinika_photo_clean_hashes',true);
        $clean=[];
        foreach(array_unique($files) as $path) {
            $key=basename($path);$hash=is_file($path)?hash_file('sha256',$path):false;
            if (!$hash || ($known[$key]??'')!==$hash) $this->clean($path);
            $clean[$key]=hash_file('sha256',$path);
        }
        update_post_meta($id,'_appleklinika_photo_clean_hashes',$clean);
        // Public REST media_details must not retain the private EXIF copied by WP.
        $metadata['image_meta']=[];
        return $metadata;
    }
    public function sanitizeAttachment(int $id): void
    {
        $metadata=wp_get_attachment_metadata($id);
        if(!is_array($metadata)||empty($metadata['width'])||empty($metadata['height'])||empty($metadata['sizes'])){
            require_once ABSPATH.'wp-admin/includes/image.php';
            $metadata=wp_generate_attachment_metadata($id,get_attached_file($id));
        }
        if(empty($metadata['width']))throw new \RuntimeException('A képfájl nem olvasható. HEIC/HEIF esetén ellenőrizd a szerver képfeldolgozóját.');
        wp_update_attachment_metadata($id,$this->sanitizeMetadata($metadata,$id));
    }
}
