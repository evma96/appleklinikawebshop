<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Narrow WordPress boundary policy; no change to Woo/provider business rules. */
final class PublicSurfaceSecurity
{
    public function register(): void
    {
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', static fn() => []);
        add_filter('rest_pre_dispatch', [$this, 'users'], 10, 3);
        add_action('template_redirect', [$this, 'author'], 0);
    }
    public function users($result, $server, $request)
    {
        if ($request->get_method() !== 'GET' || !preg_match('#^/wp/v2/users(?:/\d+)?/?$#', $request->get_route())) { return $result; }
        if (current_user_can('list_users') || current_user_can('edit_posts')) { return $result; }
        return new \WP_Error('ak_public_users_disabled', 'A felhasználói lista nem nyilvános.', ['status'=>403]);
    }
    public function author(): void
    {
        if (current_user_can('list_users') || current_user_can('edit_posts')) { return; }
        if (!is_author() && !isset($_GET['author'])) { return; }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        // Remove the canonical redirect before it exposes the author slug.
        remove_action('template_redirect', 'redirect_canonical');
    }
}
