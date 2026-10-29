<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Resolve legacy HEIC markup to the attachment's existing web derivatives only. */
final class ProductDescriptionImages
{
    public function render(string $html): string
    {
        if (!preg_match('/\.hei[cf](?:[?"\s]|$)/i', $html)) return $html;
        $tags = new \WP_HTML_Tag_Processor($html);
        $resolved = [];
        while ($tags->next_tag('IMG')) {
            $source = (string) $tags->get_attribute('src');
            if (!$this->isHeic($source)) continue;
            preg_match('/\bwp-image-(\d+)\b/', (string) $tags->get_attribute('class'), $id);
            $id = (int) ($id[1] ?? 0);
            $meta = $id ? wp_get_attachment_metadata($id) : false;
            $canonical = $id ? wp_get_attachment_url($id) : false;
            // Match the attachment's actual original, never infer a different photo.
            $original = is_array($meta) ? ($meta['original_image'] ?? '') : '';
            $sameOriginal = $canonical && $original !== ''
                && strtok($source, '?') === trailingslashit(dirname($canonical)) . $original;
            $class = (string) $tags->get_attribute('class');
            $size = preg_match('/\bsize-(medium|thumbnail|large)\b/', $class, $match) ? $match[1] : 'large';
            $image = $sameOriginal ? wp_get_attachment_image_src($id, $size) : false;
            if (!$image || $this->isHeic($image[0])) {
                // No browser-safe derivative: retain alt text but never download HEIC.
                $tags->remove_attribute('src');
                $tags->remove_attribute('srcset');
                continue;
            }
            $tags->set_attribute('src', $image[0]);
            $tags->set_attribute('width', $image[1]);
            $tags->set_attribute('height', $image[2]);
            $srcset = wp_get_attachment_image_srcset($id, $size);
            if ($srcset) $tags->set_attribute('srcset', $srcset);
            else $tags->remove_attribute('srcset');
            $sizes = wp_get_attachment_image_sizes($id, $size);
            if ($sizes) $tags->set_attribute('sizes', $sizes);
            $resolved[strtok($source, '?')] = $canonical;
        }
        $links = new \WP_HTML_Tag_Processor($tags->get_updated_html());
        while ($links->next_tag('A')) {
            $href = strtok((string) $links->get_attribute('href'), '?');
            if (isset($resolved[$href])) $links->set_attribute('href', $resolved[$href]);
        }
        return $links->get_updated_html();
    }

    private function isHeic(string $url): bool
    {
        return (bool) preg_match('/\.hei[cf]$/i', (string) wp_parse_url($url, PHP_URL_PATH));
    }
}
