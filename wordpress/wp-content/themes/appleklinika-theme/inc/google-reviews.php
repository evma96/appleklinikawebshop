<?php

declare(strict_types=1);
use Appleklinika\Inventory\Application\GoogleReviews\ReviewService;
use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;
use Appleklinika\Inventory\Infrastructure\WordPress\GoogleBusinessReviews;
use Appleklinika\Inventory\Infrastructure\WordPress\LocalReviewPreview;

function appleklinika_review_settings(mixed $input = null): array
{
    $input = is_array($input) ? $input : get_option('appleklinika_google_reviews', []);
    $input = is_array($input) ? $input : [];
    return ['title'=>sanitize_text_field(is_scalar($input['title'] ?? null) ? (string) $input['title'] : 'Rólunk mondtátok'),
        'subtitle'=>sanitize_textarea_field(is_scalar($input['subtitle'] ?? null) ? (string) $input['subtitle'] : 'Vásárlóink tapasztalatai, a saját szavaikkal.'),
        'home'=>!array_key_exists('home', $input) || !empty($input['home']),
        'compact'=>!array_key_exists('compact', $input) || !empty($input['compact'])];
}
function appleklinika_trust_surface_allowed(): bool
{
    if (is_admin() || wp_doing_ajax() || is_feed() || is_404()
        || (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())
        || (function_exists('is_account_page') && is_account_page())
        || get_query_var('appleklinika_backoffice') || is_page('backoffice')) { return false; }
    return true;
}
function appleklinika_reviews_surface(): string
{
    if (!appleklinika_trust_surface_allowed()) { return ''; }
    $settings = appleklinika_review_settings();
    return is_front_page() ? ($settings['home'] ? 'full' : '') : ($settings['compact'] ? 'compact' : '');
}
add_action('template_redirect', static function(): void {
    if (class_exists(LocalReviewPreview::class) && LocalReviewPreview::read(time()) !== null) {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
    }
});
add_action('wp_enqueue_scripts', static function(): void {
    if (!appleklinika_trust_surface_allowed()) { return; }
    wp_enqueue_style('appleklinika-google-reviews', get_template_directory_uri() . '/assets/css/google-reviews.css', ['appleklinika-theme'], (string) filemtime(__DIR__ . '/../assets/css/google-reviews.css'));
    wp_enqueue_script('appleklinika-google-reviews', get_template_directory_uri() . '/assets/js/google-reviews.js', [], (string) filemtime(__DIR__ . '/../assets/js/google-reviews.js'), true);
});
add_filter('render_block_core/template-part', static function(string $html, array $block): string {
    static $rendered = false;
    if ($rendered || ($block['attrs']['slug'] ?? '') !== 'footer') { return $html; }
    $variant = appleklinika_reviews_surface();
    if (!appleklinika_trust_surface_allowed()) { return $html; }
    $rendered = true;
    $reviews = '';
    if ($variant !== '' && class_exists(ReviewService::class)) {
        $data = LocalReviewPreview::read(time()) ?? (new ReviewService(new GoogleBusinessReviews()))->read(time());
        $reviews = appleklinika_render_google_reviews($variant, $data);
    }
    return $reviews . appleklinika_render_service_logos() . $html;
}, 10, 2);

function appleklinika_review_stars(float $rating): string
{
    $label = number_format($rating, 1, ',', '') . ' / 5 csillag';
    $width = max(0, min(100, $rating * 20));
    return '<span class="ak-reviews__stars" role="img" aria-label="' . esc_attr($label) . '"><span aria-hidden="true">☆☆☆☆☆</span><span class="ak-reviews__stars-fill" aria-hidden="true" style="width:' . esc_attr((string) $width) . '%">★★★★★</span></span>';
}
/** Render only the public projection; unavailable data never becomes a fabricated testimonial. */
function appleklinika_render_google_reviews(string $variant, ?array $data): string
{
    if ($data === null || $data['count'] < 1 || $data['rating'] === null || $data['reviews'] === []) { return ''; }
    $qa = !empty($data['qa']);
    if ($qa && (!class_exists(LocalReviewPreview::class) || !LocalReviewPreview::allowed())) { return ''; }
    $profile = $data['profile_url'];
    $settings = appleklinika_review_settings();
    $full = $variant === 'full';
    $reviews = array_slice($data['reviews'], 0, $full ? PublicReviews::MAX_REVIEWS : 2);
    ob_start(); ?>
    <section class="ak-reviews ak-reviews--<?php echo $full ? 'full' : 'compact'; ?><?php echo $qa ? ' ak-reviews--qa' : ''; ?>" aria-labelledby="ak-google-reviews-title">
        <?php if ($qa) : ?><span class="ak-reviews__qa" title="Kizárólag helyi mintaadatok, nem valódi Google-vélemények.">LOCAL · QA review data</span><?php endif; ?>
        <div class="ak-reviews__summary">
            <?php if ($full) : ?>
                <span class="ak-reviews__eyebrow">Google vélemények</span>
                <h2 id="ak-google-reviews-title"><?php echo esc_html($settings['title'] ?: 'Rólunk mondtátok'); ?></h2>
            <?php else : ?>
                <h2 id="ak-google-reviews-title" class="ak-reviews__eyebrow">Google vélemények</h2>
            <?php endif; ?>
            <div class="ak-reviews__score"><strong><?php echo esc_html(number_format($data['rating'], 1, ',', '')); ?><small> / 5</small></strong><div><?php echo appleklinika_review_stars($data['rating']); ?><span class="ak-reviews__count"><?php echo esc_html(number_format($data['count'], 0, ',', ' ') . ' vélemény'); ?></span></div></div>
            <?php if ($settings['subtitle'] !== '') : ?><p class="ak-reviews__trust"><?php echo esc_html($settings['subtitle']); ?></p><?php endif; ?>
            <div class="ak-reviews__actions">
                <a class="ak-reviews__text-link ak-reviews__all" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">Összes Google-vélemény <span aria-hidden="true">↗&#xfe0e;</span><span class="screen-reader-text"> – új lapon</span></a>
                <?php if (!empty($data['write_url'])) : ?><a class="ak-reviews__text-link ak-reviews__write" href="<?php echo esc_url($data['write_url']); ?>" target="_blank" rel="noopener noreferrer">Írj véleményt<span class="screen-reader-text"> – Google, új lapon</span></a><?php endif; ?>
            </div>
            <?php if (!$qa) : ?><p class="ak-reviews__note"><?php echo $full ? 'A Google legutóbb frissített értékeléseiből. ' : ''; ?>Frissítve: <?php echo esc_html(wp_date('Y. m. d.', $data['fetched_at'])); ?></p><?php endif; ?>
        </div>
        <div class="ak-reviews__feed">
        <?php if ($reviews !== []) : ?>
            <div id="ak-google-review-cards" class="ak-reviews__cards" tabindex="0" role="region" aria-label="Google-vélemények, vízszintesen lapozható">
                <?php foreach ($reviews as $review) :
                    // Only the visibly marked LOCAL preview omits its internal fixture prefix.
                    // Real Google review text and the fixture reader's safety checks stay intact.
                    $text = $qa ? (string) preg_replace('/^QA-minta:\s*/u', '', $review['text']) : $review['text'];
                    ?>
                    <article class="ak-reviews__card">
                        <header class="ak-reviews__reviewer">
                            <?php if ($qa) : ?><span class="ak-reviews__avatar ak-reviews__qa-avatar" aria-hidden="true"><svg viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg"><circle cx="22" cy="22" r="22" fill="#eae5e8"/><circle cx="22" cy="15" r="7" fill="#b5a4ab"/><path d="M8 40v-5a14 14 0 0 1 28 0v5" fill="#b5a4ab"/></svg></span><?php elseif (!empty($review['avatar'])) : ?><img class="ak-reviews__avatar" src="<?php echo esc_url($review['avatar']); ?>" width="44" height="44" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php else : ?><span class="ak-reviews__initial" aria-hidden="true"><?php echo esc_html(mb_substr($review['name'], 0, 1)); ?></span><?php endif; ?>
                            <div><h3><?php echo esc_html($review['name']); ?></h3><?php if ($review['date'] !== '') : ?><time datetime="<?php echo esc_attr($review['date']); ?>"><?php echo esc_html(wp_date('Y. m. d.', strtotime($review['date']))); ?></time><?php endif; ?></div>
                        </header>
                        <?php echo appleklinika_review_stars((float) $review['rating']); ?>
                        <?php if ($text !== '') : ?>
                            <?php if (mb_strlen($text) > ($full ? 260 : 130)) : ?>
                                <details class="ak-reviews__expand"><summary><span class="ak-reviews__excerpt"><?php echo esc_html($text); ?></span><span class="ak-reviews__expand-label"><span class="ak-reviews__read-more">Tovább olvasom</span><span class="ak-reviews__read-less">Kevesebb</span><span class="screen-reader-text"> – <?php echo esc_html($review['name']); ?> véleménye</span></span></summary><p><?php echo esc_html($text); ?></p></details>
                            <?php else : ?><p class="ak-reviews__text"><?php echo esc_html($text); ?></p><?php endif; ?>
                        <?php endif; ?>
                        <span class="ak-reviews__card-source" translate="no">Google</span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($full && count($reviews) > 3) : ?>
            <nav class="ak-reviews__navigation" aria-label="Értékelések lapozása" hidden>
                <span class="ak-reviews__range" aria-live="polite" aria-atomic="true"></span>
                <button type="button" data-review-prev aria-controls="ak-google-review-cards" aria-label="Előző vélemények"><span aria-hidden="true">←</span></button>
                <button type="button" data-review-next aria-controls="ak-google-review-cards" aria-label="Következő vélemények"><span aria-hidden="true">→</span></button>
            </nav>
        <?php endif; ?>
        </div>
    </section>
    <?php return (string) ob_get_clean();
}

/** Owner-approved customer-facing providers; no inferred card/wallet acceptance marks. */
function appleklinika_render_service_logos(): string
{
    $base = get_template_directory_uri() . '/assets/images/services/';
    $services = [
        ['barion.svg', 'Barion', 'Online fizetés', 110, 30],
        ['gls.svg', 'GLS', 'Csomagszállítás', 88, 31],
        ['mpl.jpg', 'MPL', 'Postai kézbesítés', 64, 48],
        ['szamlazzhu.svg', 'Számlázz.hu', 'Elektronikus számla', 128, 18],
    ];
    ob_start(); ?>
    <section class="ak-service-strip" aria-label="Fizetési, szállítási és számlázási szolgáltatók">
        <ul><?php foreach ($services as [$file, $name, $purpose, $width, $height]) : ?>
            <li><div class="ak-service-strip__logo"><img src="<?php echo esc_url($base . $file); ?>" alt="<?php echo esc_attr($name); ?>" width="<?php echo (int) $width; ?>" height="<?php echo (int) $height; ?>" loading="lazy" decoding="async"></div><span><?php echo esc_html($purpose); ?></span></li>
        <?php endforeach; ?></ul>
    </section>
    <?php return (string) ob_get_clean();
}
add_action('admin_menu', static function(): void {
    add_options_page('Google-vélemények', 'Apple Klinika vélemények', 'manage_options', 'appleklinika-google-reviews', 'appleklinika_reviews_admin');
});
add_action('admin_init', static function(): void {
    register_setting('appleklinika_google_reviews', 'appleklinika_google_reviews', ['type'=>'array', 'sanitize_callback'=>'appleklinika_review_settings']);
});
function appleklinika_reviews_admin(): void
{
    if (!current_user_can('manage_options')) { return; }
    $settings = appleklinika_review_settings();
    $configured = class_exists(GoogleBusinessReviews::class) && (new GoogleBusinessReviews())->configured();
    $status = get_option('ak_google_reviews_status', []);
    ?>
    <div class="wrap"><h1>Google-vélemények</h1>
    <p><?php echo $configured ? 'Szerveroldali kapcsolat beállítva.' : 'Google Business Profile-hozzáférés még nincs beállítva. Élő adat nélkül az értékelésszakasz rejtve marad. Kifejezetten engedélyezett LOCAL környezetben jelölt QA-előnézet használható.'; ?></p>
    <?php if ($configured) : ?><p><?php echo ($status['state'] ?? '') === 'ok' ? 'Az utolsó frissítés sikeres.' : 'Még nincs sikeres frissítés, vagy a Google átmenetileg nem érhető el.'; ?></p><?php endif; ?>
    <p>Az értékelések kizárólag a Google-tól érkeznek; ezen az oldalon nem szerkeszthetők. Titkos hozzáférési adatot itt ne adj meg.</p>
    <form action="options.php" method="post"><?php settings_fields('appleklinika_google_reviews'); ?>
    <table class="form-table"><tbody>
    <?php foreach (['title'=>'Főoldali cím','subtitle'=>'Alcím'] as $key=>$label) : ?><tr><th><label for="ak-review-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="large-text" id="ak-review-<?php echo esc_attr($key); ?>" name="appleklinika_google_reviews[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($settings[$key]); ?>"></td></tr><?php endforeach; ?>
    <?php foreach (['home'=>'Főoldali szakasz','compact'=>'Kompakt szakasz a többi nyilvános oldalon'] as $key=>$label) : ?><tr><th><?php echo esc_html($label); ?></th><td><input type="hidden" name="appleklinika_google_reviews[<?php echo esc_attr($key); ?>]" value="0"><label><input type="checkbox" name="appleklinika_google_reviews[<?php echo esc_attr($key); ?>]" value="1" <?php checked($settings[$key]); ?>> Megjelenítés</label></td></tr><?php endforeach; ?>
    </tbody></table><?php submit_button(); ?></form></div>
    <?php
}
