<?php

declare(strict_types=1);

// Only local WordPress/WooCommerce data is used. No external HTTP or fixture writes.
if (! defined('WP_HTTP_BLOCK_EXTERNAL')) {
    define('WP_HTTP_BLOCK_EXTERNAL', true);
}
require_once dirname(__DIR__, 4) . '/wp-load.php';

final class HomepagePresentationTest
{
    private int $assertions = 0;
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        foreach ($this->failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        if ($this->failures !== []) {
            exit(1);
        }
        echo "Homepage presentation tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$test = new HomepagePresentationTest();
$capture = static function (callable $render): string {
    ob_start();
    try {
        $render();
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$parse = static function (string $html): array {
    $previousErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    try {
        $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
        return [$document, new DOMXPath($document)];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
};
$class = static fn (string $value): string => 'contains(concat(" ", normalize-space(@class), " "), " ' . $value . ' ")';
$normalize = static function (string $html) use ($parse): string {
    [$document, $xpath] = $parse($html);
    // WordPress adjusts these attributes by page image position, not by card context.
    foreach ($xpath->query('//img') as $image) {
        $image->removeAttribute('loading');
        $image->removeAttribute('fetchpriority');
    }
    $body = $xpath->query('//body')->item(0);
    $result = '';
    foreach ($body->childNodes as $node) {
        $result .= $document->saveHTML($node);
    }
    return trim($result);
};
$homepageCss = (string) file_get_contents(dirname(__DIR__) . '/assets/css/homepage.css');
$homepageCss = (string) preg_replace('~/\*.*?\*/~s', '', $homepageCss);
$cssRule = static function (string $selector) use ($homepageCss): string {
    if (! preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^{}]*)\}/s', $homepageCss, $match)) {
        return '';
    }
    return (string) preg_replace('/\s+/', '', $match[1]);
};
$blockHttp = static fn () => new WP_Error('homepage_test_http_blocked', 'External requests are disabled in homepage tests.');
add_filter('pre_http_request', $blockHttp, PHP_INT_MAX);

$contentOverride = null;
$selectedOverride = [];
// References let each case change only its in-memory view of the option.
$contentFilter = static function () use (&$contentOverride) { return $contentOverride; };
$selectedFilter = static function () use (&$selectedOverride) { return $selectedOverride; };
$limitFilter = static fn (): int => 2;
add_filter('pre_option_appleklinika_home_content', $contentFilter);
add_filter('pre_option_appleklinika_home_featured_product_ids', $selectedFilter);
add_filter('pre_option_appleklinika_home_featured_product_limit', $limitFilter);

try {
    $navigationRule = $cssRule('.ak-home-hero__navigation');
    $navigationButtonRule = $cssRule('.ak-home-hero__navigation button');
    $dotRule = $cssRule('.ak-home-hero__dots');
    $toggleRule = $cssRule('.ak-home-hero__navigation [data-home-toggle]');
    $toggleFocusRule = $cssRule('.ak-home-hero__navigation [data-home-toggle]:focus-visible');
    $test->assert(str_contains($navigationRule, 'position:absolute;') && str_contains($navigationRule, 'inset:0;'), 'Carousel navigation overlays the hero instead of consuming a separate toolbar row.');
    $test->assert(str_contains($navigationRule, 'pointer-events:none;') && str_contains($navigationButtonRule, 'pointer-events:auto;'), 'The overlay leaves the artwork link clickable while its actual controls receive pointer input.');
    $test->assert(! str_contains($homepageCss, 'data-home-direction'), 'Homepage styling contains no previous/next arrow selectors.');
    $test->assert(str_contains($dotRule, 'position:absolute;') && str_contains($dotRule, 'bottom:') && str_contains($dotRule, 'left:50%;'), 'Position dots remain anchored at the bottom centre of the image.');
    $test->assert(str_contains($toggleRule, 'opacity:0;') && str_contains($toggleRule, 'pointer-events:none;') && ! str_contains($toggleRule, 'display:none') && ! str_contains($toggleRule, 'visibility:hidden'), 'Pause is visually discreet without being removed from keyboard or assistive-technology access.');
    $test->assert(str_contains($toggleFocusRule, 'opacity:1;') && str_contains($toggleFocusRule, 'pointer-events:auto;'), 'Keyboard focus reveals a usable pause/resume control.');
    $test->assert(str_contains($cssRule('.ak-home .ak-home-products'), 'grid-auto-rows:1fr;'), 'Homepage grid rows share natural available height across stacked mobile and tablet rows.');
    $cardScope = '.ak-home .ak-home-products-shell.woocommerce .ak-home-products.wc-block-product-template';
    $cardFrameRule = $cssRule($cardScope . ' li.wc-block-product');
    $test->assert(str_contains($cardFrameRule, 'display:flex;') && str_contains($cardFrameRule, 'flex-direction:column;') && str_contains($cardFrameRule, 'height:auto;') && str_contains($cardFrameRule, 'align-self:stretch;'), 'Homepage card frames use one scoped, content-safe equal-row fill chain.');
    $test->assert(str_contains($cssRule($cardScope . ' li.wc-block-product > .ak-product-card__inner'), 'flex:1;'), 'Only the direct shared card link fills the available homepage frame.');
    $test->assert(str_contains($cssRule($cardScope . ' .ak-product-card__content'), 'grid-template-rows:42px1fr42px040px;'), 'Homepage alignment retains title, price and CTA row sizes and allocates spare space to metadata.');
    $sharedCss = (string) file_get_contents(dirname(__DIR__) . '/assets/css/frontend.css');
    $test->assert(! str_contains($sharedCss, $cardScope . ' li.wc-block-product'), 'The old homepage-only alignment override is removed from the shared stylesheet.');

    $defaults = appleklinika_home_content_defaults();
    $test->assert(is_array($defaults) && count($defaults['hero_items']) >= 1, 'Defaults contain a usable hero.');
    $test->assert(appleklinika_sanitize_home_content(null) === $defaults, 'Malformed top-level content falls back to safe defaults.');
    $test->assert(appleklinika_sanitize_home_content([]) === $defaults, 'Missing content fields preserve defaults.');
    $test->assert($defaults['categories'][0]['url'] === appleklinika_shop_type_url('iphone'), 'Default category destinations use the configured shop URLs.');
    $test->assert($defaults['hero_layout'] === 'split', 'Existing installations keep the split hero layout by default.');
    $test->assert($defaults['hero_items'][0]['enabled'] === true, 'Existing default hero rows are enabled.');
    $legacy = $defaults;
    unset($legacy['hero_layout']);
    foreach ($legacy['hero_items'] as &$legacyRow) {
        unset($legacyRow['enabled'], $legacyRow['url'], $legacyRow['alt']);
    }
    unset($legacyRow);
    $legacy['hero_items'][] = array_replace($legacy['hero_items'][0], ['title' => 'Additional legacy slide']);
    $legacyClean = appleklinika_sanitize_home_content($legacy);
    $test->assert($legacyClean['hero_layout'] === 'split' && $legacyClean['hero_items'][0]['enabled'] === true, 'Saved pre-carousel content retains its split layout and visible hero.');
    $test->assert($legacyClean['hero_items'][1]['enabled'] === true, 'Legacy rows beyond the first default row are enabled too.');
    $test->assert($legacyClean['hero_items'][0]['title'] === $legacy['hero_items'][0]['title'] && $legacyClean['hero_items'][0]['primary_url'] === $legacy['hero_items'][0]['primary_url'], 'Legacy copy and button destinations are preserved during schema normalization.');
    $test->assert(appleklinika_sanitize_home_content(['hero_layout' => 'untrusted-layout'])['hero_layout'] === 'split', 'Unknown hero layout values fall back to split.');
    foreach ([true, 1, '1', false, 0, '0', 'unexpected'] as $enabledValue) {
        $enabledInput = $defaults;
        $enabledInput['hero_items'][0]['enabled'] = $enabledValue;
        $enabledClean = appleklinika_sanitize_home_content($enabledInput);
        $test->assert($enabledClean['hero_items'][0]['enabled'] === in_array($enabledValue, [true, 1, '1'], true), 'Hero enabled state uses the explicit boolean allowlist: ' . var_export($enabledValue, true));
    }

    $input = $defaults;
    $input['unknown_field'] = '<script>alert(1)</script>';
    $input['hero_items'] = [[
        'eyebrow' => '<b>Edited eyebrow</b>',
        'title' => 'Homepage <b>bold</b> & "safe"',
        'text' => 'First line\n<script>alert(1)</script><img src=x onerror=alert(2)>Second line',
        'image_id' => PHP_INT_MAX,
        'primary_label' => '<b>Browse</b>',
        'primary_url' => 'javascript:alert(1)',
        'secondary_label' => 'More',
        'secondary_url' => 'data:text/html,<script>alert(1)</script>',
        'unexpected' => 'discard me',
    ]];
    $input['process_image_id'] = PHP_INT_MAX;
    $input['trust_items'][0]['icon'] = '<svg onload=alert(1)>';
    $sanitized = appleklinika_sanitize_home_content($input);
    $hero = $sanitized['hero_items'][0];
    $test->assert($hero['title'] === 'Homepage bold & "safe"', 'Hero titles are plain text, preserving ordinary punctuation.');
    $test->assert(! str_contains($hero['text'], '<') && ! str_contains($hero['text'], 'alert('), 'Hero text strips HTML and executable script content.');
    $test->assert($hero['primary_url'] === '' && $hero['secondary_url'] === '', 'JavaScript and data URLs cannot become CTA targets.');
    $test->assert($hero['image_id'] === 0 && $sanitized['process_image_id'] === 0, 'Unknown attachment IDs become empty media selections.');
    $test->assert($sanitized['trust_items'][0]['icon'] === 'check', 'Unknown icons use the allowlisted fallback.');
    $test->assert(! array_key_exists('unknown_field', $sanitized) && ! array_key_exists('unexpected', $hero), 'Unrecognized content keys are discarded.');

    $links = $defaults;
    $links['hero_items'][0]['primary_url'] = 'https://example.test/shop/?device=iphone';
    $links['hero_items'][0]['secondary_url'] = '/kapcsolat/';
    $links['categories'][0]['url'] = 'javascript:alert(1)';
    $links = appleklinika_sanitize_home_content($links);
    $test->assert($links['hero_items'][0]['primary_url'] === 'https://example.test/shop/?device=iphone' && $links['hero_items'][0]['secondary_url'] === '/kapcsolat/', 'Normal HTTPS and site-relative CTA links remain usable.');
    $test->assert($links['categories'][0]['url'] === '', 'Category links reject executable URL protocols too.');

    $reordered = $defaults;
    $firstHero = $defaults['hero_items'][0];
    $firstHero['title'] = 'First configured slide';
    $secondHero = $firstHero;
    $secondHero['title'] = 'Second configured slide';
    $reordered['hero_items'] = [9 => $firstHero, 2 => $secondHero];
    $reordered = appleklinika_sanitize_home_content($reordered);
    $test->assert(array_column($reordered['hero_items'], 'title') === ['First configured slide', 'Second configured slide'] && array_keys($reordered['hero_items']) === [0, 1], 'Sparse hero indexes are normalized without changing the configured slide order.');

    $caps = ['hero_items' => 8, 'hero_benefits' => 8, 'categories' => 12, 'trust_items' => 12, 'process_items' => 10];
    foreach ($caps as $key => $cap) {
        $overfull = $defaults;
        $overfull[$key] = array_fill(0, $cap + 3, $defaults[$key][0]);
        $limited = appleklinika_sanitize_home_content($overfull);
        $test->assert(count($limited[$key]) === $cap, "{$key} enforces the documented row limit.");
        $test->assert(array_keys($limited[$key]) === range(0, $cap - 1), "{$key} uses consecutive row indexes.");
    }
    $empty = $defaults;
    foreach (array_keys($caps) as $key) {
        $empty[$key] = [];
    }
    $empty = appleklinika_sanitize_home_content($empty);
    $test->assert(count($empty['hero_items']) >= 1, 'Removing every hero still leaves a usable default hero.');
    foreach (['hero_benefits', 'categories', 'trust_items', 'process_items'] as $key) {
        $test->assert($empty[$key] === [], "An intentionally empty {$key} list stays empty.");
    }
    $blank = appleklinika_sanitize_home_content(['featured_text' => '']);
    $test->assert($blank['featured_text'] === '', 'A deliberately blank optional paragraph does not restore default copy.');

    $attachments = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids']);
    if ($attachments !== []) {
        $imageId = (int) $attachments[0];
        $media = $defaults;
        $media['hero_items'][0]['image_id'] = -$imageId;
        $media['process_image_id'] = (string) $imageId;
        $media = appleklinika_sanitize_home_content($media);
        $test->assert($media['hero_items'][0]['image_id'] === $imageId && $media['process_image_id'] === $imageId, 'Existing image IDs are normalized to positive integers.');
    } else {
        echo "SKIP: Positive media-ID lookup requires an existing local image attachment.\n";
    }

    $products = wc_get_products(['status' => 'publish', 'limit' => 2, 'orderby' => 'ID', 'order' => 'ASC']);
    $test->assert($products !== [], 'A published local WooCommerce product exists for read-only card parity tests.');
    $selectedOverride = array_reverse(appleklinika_product_ids_from_products($products));
    $selectedProducts = appleklinika_homepage_products('home_featured', 2);
    $test->assert(appleklinika_product_ids_from_products($selectedProducts) === $selectedOverride, 'Selected real products retain their configured order.');
    $test->assert(count($selectedProducts) <= 2, 'The existing product limit remains effective.');
    $selectedOverride = [];
    $fallbackProducts = appleklinika_homepage_products('home_featured', 2);
    $fallbackIds = appleklinika_product_ids_from_products($fallbackProducts);
    $test->assert(count($fallbackIds) <= 2 && count($fallbackIds) === count(array_unique($fallbackIds)), 'The real WooCommerce fallback query respects the limit without duplicates.');
    $test->assert(count(array_filter($fallbackProducts, static fn (WC_Product $product): bool => $product->get_status() !== 'publish')) === 0, 'The real WooCommerce fallback contains only published products.');
    $selectedOverride = array_reverse(appleklinika_product_ids_from_products($products));

    $contentOverride = $defaults;
    $headerBefore = $capture('appleklinika_render_header');
    $homepage = $capture('appleklinika_render_homepage');
    [$document, $xpath] = $parse($homepage);
    $main = $xpath->query('//main[' . $class('ak-home') . ']')->item(0);
    $test->assert($main instanceof DOMElement, 'The homepage keeps one dedicated main.ak-home body.');
    $test->assert($xpath->query('//main')->length === 1 && $xpath->query('//h1')->length === 1, 'The body has one main landmark and one primary heading.');
    $test->assert($xpath->query('//header')->length === 0, 'The homepage renderer does not duplicate the shared header.');
    foreach ($xpath->query('//a[starts-with(@href, "#")]') as $anchor) {
        $target = substr($anchor->getAttribute('href'), 1);
        $test->assert($target !== '' && $document->getElementById($target) instanceof DOMElement, 'Each default in-page CTA points to an existing section.');
    }
    if ($main instanceof DOMElement) {
        $sections = $xpath->query('./section', $main);
        $test->assert($sections->length === 5, 'Exactly five body sections are rendered.');
        $expected = ['hero', 'ak-home-categories', 'ak-home-showcase', 'ak-home-trust', 'ak-home-process'];
        foreach ($expected as $index => $sectionClass) {
            $section = $sections->item($index);
            $classes = $section instanceof DOMElement ? explode(' ', $section->getAttribute('class')) : [];
            $matches = $index === 0
                ? in_array('ak-hero', $classes, true) || in_array('ak-home-hero', $classes, true)
                : in_array($sectionClass, $classes, true);
            $test->assert($matches, "Section {$index} follows the hero/categories/showcase/trust/process order.");
        }
    }
    $process = $xpath->query('//section[' . $class('ak-home-process') . ']')->item(0);
    $test->assert($process instanceof DOMElement && $xpath->query('.//a|.//button|.//form', $process)->length === 0, 'The process section contains no CTA, form or purchase action.');

    foreach ($selectedProducts as $product) {
        $homeCard = $capture(static fn () => appleklinika_render_product_card($product, 'home'));
        $shopCard = $capture(static fn () => appleklinika_render_product_card($product, 'shop'));
        $test->assert($normalize($homeCard) === $normalize($shopCard), 'Home and catalogue use identical shared card markup for product ' . $product->get_id() . '.');
        $card = $xpath->query('//section[' . $class('ak-home-showcase') . ']//li[' . $class('post-' . $product->get_id()) . ']')->item(0);
        $cardHtml = '';
        if ($card instanceof DOMElement) {
            foreach ($card->childNodes as $child) {
                $cardHtml .= $document->saveHTML($child);
            }
        }
        $test->assert($card instanceof DOMElement && $normalize($cardHtml) === $normalize($homeCard), 'Showcase renders the unmodified shared card for product ' . $product->get_id() . '.');
    }

    $contentOverride = $reordered;
    $multipleHtml = $capture('appleklinika_render_homepage');
    [, $multipleXpath] = $parse($multipleHtml);
    $firstPosition = strpos($multipleHtml, 'First configured slide');
    $secondPosition = strpos($multipleHtml, 'Second configured slide');
    $test->assert($firstPosition !== false && $secondPosition !== false && $firstPosition < $secondPosition, 'Multiple configured hero slides appear in their saved order.');
    $test->assert($multipleXpath->query('//h1')->length === 1, 'Multiple hero slides do not duplicate the primary page heading.');
    $test->assert($multipleXpath->query('//*[@data-home-direction]')->length === 0, 'Split-layout carousel markup contains no previous/next arrows.');

    $contentOverride = $sanitized;
    $customHtml = $capture('appleklinika_render_homepage');
    [, $customXpath] = $parse($customHtml);
    $test->assert(str_contains($customHtml, esc_html($hero['title'])), 'The selected custom hero title is HTML-escaped at output.');
    $test->assert($customXpath->query('//script|//*[@onerror or @onload]')->length === 0, 'Custom homepage content cannot inject scripts or event attributes.');
    $test->assert(! str_contains($customHtml, 'javascript:') && ! str_contains($customHtml, 'data:text/html'), 'Unsafe CTA protocols never reach rendered markup.');
    $test->assert($capture('appleklinika_render_header') === $headerBefore, 'Homepage content editing leaves shared header markup unchanged.');
    $test->assert($customXpath->query('//section')->length === 5, 'Missing or invalid media does not remove body sections.');
    foreach ($customXpath->query('//img') as $image) {
        $test->assert(trim($image->getAttribute('src')) !== '', 'Every rendered product or fallback image has a nonempty source.');
    }

    $contentOverride = $legacy;
    $legacyHtml = $capture('appleklinika_render_homepage');
    [, $legacyXpath] = $parse($legacyHtml);
    $test->assert($legacyXpath->query('//*[@data-home-slide]')->length === count($legacy['hero_items']) && $legacyXpath->query('//h1')->length === 1, 'Legacy split content still renders its hero and single main heading.');
    $test->assert(str_contains($legacyHtml, esc_html($legacy['hero_items'][0]['primary_label'])), 'The existing split-layout CTA remains rendered.');

    if ($attachments !== []) {
        $artwork = $defaults;
        $artwork['hero_layout'] = 'artwork';
        $artworkRow = array_merge($defaults['hero_items'][0], [
            'enabled' => true,
            'image_id' => (int) $attachments[0],
            'url' => 'https://example.test/campaign-a/?type=iphone&source=home',
            'alt' => 'Campaign <b>artwork</b> & "offer"',
            'title' => 'DO NOT OVERLAY THIS TITLE',
            'text' => 'DO NOT OVERLAY THIS PARAGRAPH',
            'primary_label' => 'DO NOT OVERLAY THIS BUTTON',
        ]);
        $disabledRow = array_replace($artworkRow, ['enabled' => false, 'url' => 'https://example.test/disabled-campaign/']);
        $missingImageRow = array_replace($artworkRow, ['image_id' => 0, 'url' => 'https://example.test/missing-image/']);
        $missingUrlRow = array_replace($artworkRow, ['url' => '']);
        $unsafeUrlRow = array_replace($artworkRow, ['url' => 'javascript:alert(1)']);
        $lastRow = array_replace($artworkRow, ['url' => '/campaign-b/', 'alt' => 'Second campaign']);
        // Keep six rows to exercise filtering independently of the saved row cap.
        $artwork['hero_items'] = [$disabledRow, $artworkRow, $missingImageRow, $missingUrlRow, $unsafeUrlRow, $lastRow];
        $artworkClean = appleklinika_sanitize_home_content($artwork);
        $test->assert($artworkClean['hero_layout'] === 'artwork' && $artworkClean['hero_items'][0]['enabled'] === false, 'Artwork layout and an explicitly disabled row survive sanitization.');
        $test->assert($artworkClean['hero_items'][1]['alt'] === 'Campaign artwork & "offer"', 'Artwork alternative text is sanitized as plain text.');
        $test->assert($artworkClean['hero_items'][4]['url'] === '', 'Artwork destination links reject executable URL protocols.');
        $contentOverride = $artwork;
        $artworkHtml = $capture('appleklinika_render_homepage');
        [, $artworkXpath] = $parse($artworkHtml);
        $artworkSlides = $artworkXpath->query('//*[@data-home-slide]');
        $artworkLinks = $artworkXpath->query('//a[' . $class('ak-home-hero__artwork') . ']');
        $test->assert($artworkSlides->length === 2 && $artworkLinks->length === 2, 'Only enabled artwork rows with both an image and safe destination are rendered.');
        $hrefs = [];
        foreach ($artworkLinks as $link) {
            $hrefs[] = $link->getAttribute('href');
            $test->assert($artworkXpath->query('./img', $link)->length === 1 && trim($link->textContent) === '', 'Each artwork slide is one image-only link without a copy overlay.');
        }
        $test->assert($hrefs === [$artworkClean['hero_items'][1]['url'], $artworkClean['hero_items'][5]['url']], 'Filtering retains configured artwork order and exact destinations.');
        $firstArtworkImage = $artworkLinks->length > 0 ? $artworkXpath->query('./img', $artworkLinks->item(0))->item(0) : null;
        $test->assert($firstArtworkImage instanceof DOMElement && $firstArtworkImage->getAttribute('alt') === 'Campaign artwork & "offer"', 'Rendered artwork preserves safe accessible alternative text.');
        $test->assert($artworkXpath->query('//*[' . $class('ak-home-hero__copy') . ' or ' . $class('ak-home-hero__text') . ' or ' . $class('ak-home-hero__actions') . ' or ' . $class('ak-home-hero__benefits') . ']')->length === 0, 'Artwork mode suppresses stored split copy, CTA overlays and duplicate hero benefits.');
        $test->assert($artworkXpath->query('//h1')->length === 1 && $artworkXpath->query('//h1[' . $class('screen-reader-text') . ']')->length === 1, 'Artwork mode retains one accessible page heading without overlaying the image.');
        $test->assert($artworkXpath->query('//*[@data-home-hero and @data-home-interval="5500"]')->length === 1, 'Carousel markup exposes the agreed 5.5-second interval.');
        $test->assert($artworkXpath->query('//*[@data-home-dot]')->length === 2 && $artworkXpath->query('//*[@data-home-toggle]')->length === 1, 'Multiple artwork slides include matching dots and an autoplay pause control.');
        $pauseControl = $artworkXpath->query('//*[@data-home-toggle]')->item(0);
        $test->assert($pauseControl instanceof DOMElement && $pauseControl->tagName === 'button' && ! $pauseControl->hasAttribute('hidden') && $pauseControl->getAttribute('tabindex') !== '-1' && $pauseControl->getAttribute('aria-label') !== '', 'The visually discreet pause control remains a named, keyboard-reachable button.');
        $test->assert($artworkXpath->query('//*[@data-home-direction]')->length === 0, 'Artwork carousel markup contains no previous/next arrows.');
        $test->assert($artworkXpath->query('//script|//*[@onerror or @onload]')->length === 0 && ! str_contains($artworkHtml, 'javascript:'), 'Artwork mode does not introduce executable injected markup.');

        $singleArtwork = $artwork;
        $singleArtwork['hero_items'] = [$artworkRow];
        $contentOverride = $singleArtwork;
        [, $singleXpath] = $parse($capture('appleklinika_render_homepage'));
        $test->assert($singleXpath->query('//*[@data-home-slide]')->length === 1 && $singleXpath->query('//*[@data-home-navigation]')->length === 0, 'A single valid artwork slide renders without redundant carousel controls.');

        $disabledArtwork = $artwork;
        $disabledArtwork['hero_items'] = [$disabledRow, $missingImageRow, $missingUrlRow, $unsafeUrlRow];
        $contentOverride = $disabledArtwork;
        [, $disabledXpath] = $parse($capture('appleklinika_render_homepage'));
        $test->assert($disabledXpath->query('//*[@data-home-hero]')->length === 0 && $disabledXpath->query('//*[@data-home-slide]')->length === 0, 'An entirely disabled or incomplete artwork set does not restore a hidden campaign as fallback.');
        $test->assert($disabledXpath->query('//main/section')->length === 4 && $disabledXpath->query('//h1')->length === 1, 'An empty artwork set leaves the remaining sections and accessible main heading intact.');
        $test->assert($capture('appleklinika_render_header') === $headerBefore, 'Artwork layout and filtering do not change the shared header.');
    } else {
        echo "SKIP: Artwork rendering checks require an existing local image attachment.\n";
    }
} finally {
    remove_filter('pre_option_appleklinika_home_content', $contentFilter);
    remove_filter('pre_option_appleklinika_home_featured_product_ids', $selectedFilter);
    remove_filter('pre_option_appleklinika_home_featured_product_limit', $limitFilter);
    remove_filter('pre_http_request', $blockHttp, PHP_INT_MAX);
}

$test->finish();
