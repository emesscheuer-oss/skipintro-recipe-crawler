<?php
/**
 * SkipIntro Recipe Crawler – ColibriWP Adjustments & Features
 */

if (!defined('ABSPATH')) { exit; }

define('SIC_COLIBRI_DIR', __DIR__);
define('SIC_COLIBRI_URL', trailingslashit(plugin_dir_url(__FILE__)));
define('SIC_COLIBRI_VERSION', '1.1.0');

final class SIC_ColibriWP_Features {

    private static $instance;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init',     [$this, 'register_rest_routes']);
        add_shortcode('skipintro-more-recipies', [$this, 'shortcode_related']);
    }

    public function enqueue_assets() {
        if (is_admin()) { return; }

        wp_enqueue_style(
            'sic-colibri-features',
            SIC_COLIBRI_URL . 'assets/css/sic-colibriwp-features.css',
            [],
            SIC_COLIBRI_VERSION
        );

        wp_enqueue_script(
            'sic-colibri-features',
            SIC_COLIBRI_URL . 'assets/js/sic-colibriwp-features.js',
            [],
            SIC_COLIBRI_VERSION,
            true
        );

        wp_localize_script('sic-colibri-features', 'SIC_COLIBRI', [
            'nonce'         => wp_create_nonce('wp_rest'),
            'restUrl'       => esc_url_raw(get_rest_url()),
            'restPosts'     => esc_url_raw( rest_url('wp/v2/posts') ),
            'siteUrl'       => esc_url_raw(home_url('/')),
            'i18n'          => [
                'loading'   => __('Lade weitere Beiträge…', 'skipintro'),
                'noMore'    => __('Keine weiteren Beiträge.', 'skipintro'),
                'loadMore'  => __('Lade mehr Rezepte …', 'skipintro'),
                'moreFrom'  => __('Weitere Rezepte aus', 'skipintro'),
            ],
            // Batch-Größe für Blogliste:
            'blogBatch'     => 6,
        ]);
    }

    public function register_rest_routes() {
        register_rest_route('skipintro/v1', '/related', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_get_related'],
            'permission_callback' => '__return_true',
            'args'                => [
                'post'     => ['required' => true,  'type' => 'integer'],
                'per_page' => ['required' => false, 'type' => 'integer', 'default' => 6, 'minimum' => 1, 'maximum' => 24],
                'exclude'  => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    public function rest_get_related(WP_REST_Request $req): WP_REST_Response {
        $post_id   = (int) $req->get_param('post');
        $per_page  = (int) $req->get_param('per_page');
        $exclude_s = (string) $req->get_param('exclude');

        $exclude = [];
        if ($exclude_s !== '') {
            foreach (explode(',', $exclude_s) as $id) {
                $id = (int) trim($id);
                if ($id > 0) { $exclude[] = $id; }
            }
        }

        $posts = $this->get_related_posts($post_id, $per_page, $exclude);

        $data = array_map(function (WP_Post $p) {
            $thumb = get_the_post_thumbnail_url($p->ID, 'medium_large')
                  ?: get_the_post_thumbnail_url($p->ID, 'large')
                  ?: get_the_post_thumbnail_url($p->ID, 'medium');
            return [
                'id'    => $p->ID,
                'link'  => get_permalink($p),
                'title' => html_entity_decode(get_the_title($p->ID), ENT_QUOTES, 'UTF-8'),
                'image' => $thumb ?: '',
            ];
        }, $posts);

        return new WP_REST_Response(['items' => $data], 200);
    }

    private function get_related_posts(int $post_id, int $per_page = 6, array $exclude = []): array {
        $cats = wp_get_post_categories($post_id, ['fields' => 'ids']);
        if (empty($cats)) { return []; }

        $exclude = array_unique(array_filter(array_map('intval', $exclude)));
        $exclude[] = $post_id;

        $q = new WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'orderby'             => 'rand',
            'ignore_sticky_posts' => true,
            'category__in'        => $cats,
            'post__not_in'        => $exclude,
            'no_found_rows'       => true,
        ]);

        return $q->posts ?: [];
    }
}

SIC_ColibriWP_Features::instance();
