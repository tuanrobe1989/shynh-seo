<?php
/**
 * Shynh SEO — REST API module (namespace `shynh-seo/v1`).
 *
 * Exposes SEO/AEO/GEO data to authorized AI/machine clients (read) and a small,
 * whitelisted set of SEO-meta write operations. Auth is by API key (see
 * Shynh_SEO_API_Keys) or a logged-in admin with a valid REST nonce (used by the
 * bundled Swagger UI "Try it out").
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_API {

    const NAMESPACE = 'shynh-seo/v1';
    const LOG_OPTION = 'shynh_seo_api_log';

    private static $instance = null;

    /** Authenticated context for the current request (for logging). */
    private $auth_context = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /* ---------------------------------------------------------------------
     * Route registration
     * ------------------------------------------------------------------- */

    public function register_routes() {
        $read  = [ $this, 'perm_read' ];
        $write = [ $this, 'perm_write' ];

        $ns = self::NAMESPACE;

        register_rest_route( $ns, '/', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_index' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/openapi.json', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_openapi' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/site', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_site' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/posts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_posts' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/posts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_post' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/posts/(?P<id>\d+)/seo', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'route_update_post_seo' ],
            'permission_callback' => $write,
        ] );

        register_rest_route( $ns, '/posts/(?P<id>\d+)/faq', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'route_update_post_faq' ],
            'permission_callback' => $write,
        ] );

        register_rest_route( $ns, '/taxonomies', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_taxonomies' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/terms', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_terms' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/terms/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'route_term' ],
                'permission_callback' => $read,
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'route_update_term_seo' ],
                'permission_callback' => $write,
            ],
        ] );

        register_rest_route( $ns, '/faqs', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_faqs' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/keywords', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_keywords' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/related', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_related' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/analysis', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_analysis' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/audit', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_audit' ],
            'permission_callback' => $read,
        ] );

        // --- Report Dashboard endpoints (paginated + cached, anti-hang) ---
        register_rest_route( $ns, '/report/summary', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_summary' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/rows', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_rows' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/keywords', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_keywords' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/geo', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_geo' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/rescan', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'route_report_rescan' ],
            'permission_callback' => $write,
        ] );

        register_rest_route( $ns, '/report/fixes', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_fixes' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/fixes/summary', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_fixes_summary' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/duplicates', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_report_duplicates' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/report/duplicates/scan', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'route_report_duplicates_scan' ],
            'permission_callback' => $write,
        ] );

        // --- AI context endpoints (understand the site + create drafts) ---
        register_rest_route( $ns, '/guidelines', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_guidelines' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/schema-types', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_schema_types' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/blocks', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_blocks' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/content-brief', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_content_brief' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/drafts', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'route_create_draft' ],
            'permission_callback' => $write,
        ] );

        register_rest_route( $ns, '/keywords/suggestions', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_keyword_suggestions' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/keywords/expand', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'route_keyword_expand' ],
            'permission_callback' => $write,
        ] );

        register_rest_route( $ns, '/keyword-map', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_keyword_map' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/keywords/seeds', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_keyword_seeds' ],
            'permission_callback' => $read,
        ] );

        register_rest_route( $ns, '/keywords/expand-seed', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'route_keyword_expand_seed' ],
            'permission_callback' => $read,
        ] );
    }

    /* ---------------------------------------------------------------------
     * Authentication / authorization
     * ------------------------------------------------------------------- */

    public function perm_read( $request ) {
        return $this->check_permission( $request, 'read' );
    }

    public function perm_write( $request ) {
        return $this->check_permission( $request, 'write' );
    }

    /**
     * Grant if a valid API key carries the required scope (and API is enabled),
     * or the request comes from a logged-in admin (cookie + valid REST nonce).
     *
     * @return true|WP_Error
     */
    private function check_permission( $request, $scope ) {
        // 1) Logged-in admin (Swagger UI "Try it out" inside wp-admin).
        $cap = 'write' === $scope ? 'edit_posts' : 'read';
        if ( is_user_logged_in() && current_user_can( $cap === 'read' ? 'read' : 'edit_posts' ) ) {
            $this->auth_context = [ 'type' => 'user', 'id' => get_current_user_id(), 'scope' => $scope ];
            $this->log_request( $request, 'user' );
            return true;
        }

        // 2) API key.
        $raw = $this->extract_key( $request );
        if ( empty( $raw ) ) {
            return new WP_Error(
                'shynh_seo_no_key',
                __( 'Thiếu API key. Gửi header X-Shynh-API-Key hoặc Authorization: Bearer.', 'shynh-seo' ),
                [ 'status' => 401 ]
            );
        }

        if ( ! Shynh_SEO_API_Keys::is_api_enabled() ) {
            return new WP_Error(
                'shynh_seo_api_disabled',
                __( 'API đang tắt. Bật trong wp-admin → Shynh SEO → API & AI Access.', 'shynh-seo' ),
                [ 'status' => 403 ]
            );
        }

        $record = Shynh_SEO_API_Keys::verify( $raw );
        if ( ! $record ) {
            return new WP_Error(
                'shynh_seo_bad_key',
                __( 'API key không hợp lệ hoặc đã bị thu hồi.', 'shynh-seo' ),
                [ 'status' => 401 ]
            );
        }

        if ( ! in_array( $scope, (array) $record['scopes'], true ) ) {
            return new WP_Error(
                'shynh_seo_forbidden_scope',
                sprintf( __( 'API key thiếu quyền "%s".', 'shynh-seo' ), $scope ),
                [ 'status' => 403 ]
            );
        }

        $this->auth_context = [ 'type' => 'key', 'id' => $record['id'], 'label' => $record['label'], 'scope' => $scope ];
        $this->log_request( $request, 'key:' . $record['label'] );
        return true;
    }

    private function extract_key( $request ) {
        $key = $request->get_header( 'x_shynh_api_key' );
        if ( ! empty( $key ) ) {
            return trim( $key );
        }
        $auth = $request->get_header( 'authorization' );
        if ( ! empty( $auth ) && preg_match( '/Bearer\s+(\S+)/i', $auth, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    private function log_request( $request, $actor ) {
        $log = get_option( self::LOG_OPTION, [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        array_unshift( $log, [
            'time'   => time(),
            'actor'  => $actor,
            'method' => $request->get_method(),
            'route'  => $request->get_route(),
        ] );
        $log = array_slice( $log, 0, 200 );
        update_option( self::LOG_OPTION, $log, false );
    }

    /* ---------------------------------------------------------------------
     * Discovery routes
     * ------------------------------------------------------------------- */

    public function route_index() {
        return rest_ensure_response( [
            'name'        => 'Shynh SEO AI Access API',
            'version'     => defined( 'SHYNH_SEO_VERSION' ) ? SHYNH_SEO_VERSION : '1.0.0',
            'description' => 'REST API cung cấp dữ liệu SEO/AEO/GEO cho AI để phân tích và đề xuất kế hoạch.',
            'openapi'     => rest_url( self::NAMESPACE . '/openapi.json' ),
            'auth'        => [
                'header'  => 'X-Shynh-API-Key: shynh_sk_...  (hoặc Authorization: Bearer shynh_sk_...)',
                'scopes'  => [ 'read', 'write' ],
                'enabled' => Shynh_SEO_API_Keys::is_api_enabled(),
            ],
            'endpoints'   => [
                'GET  /site', 'GET  /posts', 'GET  /posts/{id}', 'GET  /taxonomies',
                'GET  /terms', 'GET  /terms/{id}', 'GET  /faqs', 'GET  /keywords',
                'GET  /related?post_id=', 'GET  /analysis?post_id=|term_id=', 'GET  /audit',
                'PATCH /posts/{id}/seo', 'PUT /posts/{id}/faq', 'PATCH /terms/{id}/seo',
            ],
        ] );
    }

    public function route_openapi() {
        return rest_ensure_response( Shynh_SEO_API_OpenAPI::build() );
    }

    /* ---------------------------------------------------------------------
     * Read routes
     * ------------------------------------------------------------------- */

    public function route_site() {
        $post_types = [];
        foreach ( Shynh_SEO_Core::get_public_content_post_types() as $pt ) {
            $obj = get_post_type_object( $pt );
            if ( ! $obj ) {
                continue;
            }
            $counts = wp_count_posts( $pt );
            $post_types[] = [
                'name'      => $pt,
                'label'     => $obj->labels->name ?? $pt,
                'rest_base' => $obj->rest_base ? $obj->rest_base : $pt,
                'published' => (int) ( $counts->publish ?? 0 ),
            ];
        }

        $taxonomies = [];
        foreach ( Shynh_SEO_Core::get_public_content_taxonomies() as $tx ) {
            $obj = get_taxonomy( $tx );
            if ( ! $obj ) {
                continue;
            }
            $taxonomies[] = [
                'name'  => $tx,
                'label' => $obj->labels->name ?? $tx,
                'count' => (int) wp_count_terms( [ 'taxonomy' => $tx, 'hide_empty' => false ] ),
            ];
        }

        $languages = [];
        if ( function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list();
        }

        $settings = Shynh_SEO_Core::get_settings();
        $social   = [];
        foreach ( [ 'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_twitter' ] as $key ) {
            if ( ! empty( $settings[ $key ] ) ) {
                $social[] = $settings[ $key ];
            }
        }

        return rest_ensure_response( [
            'name'         => get_bloginfo( 'name' ),
            'description'  => get_bloginfo( 'description' ),
            'url'          => home_url( '/' ),
            'language'     => get_bloginfo( 'language' ),
            'languages'    => $languages,
            'organization' => [
                'name'          => ! empty( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
                'logo'          => $settings['org_logo'] ?? '',
                'default_image' => $settings['default_og_image'] ?? '',
                'same_as'       => $social,
                'reviewer'      => $settings['schema_default_reviewer'] ?? '',
            ],
            'post_types'   => $post_types,
            'taxonomies'   => $taxonomies,
        ] );
    }

    public function route_posts( $request ) {
        $type     = sanitize_key( $request->get_param( 'type' ) );
        $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term     = absint( $request->get_param( 'term' ) );
        $status   = sanitize_key( $request->get_param( 'status' ) ) ?: 'publish';
        $orderby  = sanitize_key( $request->get_param( 'orderby' ) ) ?: 'date';
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) );
        $has_fk   = $request->get_param( 'has_focus_keyword' );

        $allowed_types = Shynh_SEO_Core::get_public_content_post_types();
        $post_type     = ( $type && in_array( $type, $allowed_types, true ) ) ? $type : $allowed_types;

        $args = [
            'post_type'      => $post_type,
            'post_status'    => ( 'any' === $status ) ? [ 'publish', 'draft', 'pending', 'future', 'private' ] : $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => in_array( $orderby, [ 'date', 'modified', 'title' ], true ) ? $orderby : 'date',
            'order'          => 'title' === $orderby ? 'ASC' : 'DESC',
            's'              => $search,
        ];

        if ( 'seo_score' === $orderby ) {
            $args['meta_key'] = '_shynh_seo_score';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
        }

        if ( $taxonomy && $term ) {
            $args['tax_query'] = [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term ] ];
        }

        if ( null !== $has_fk && '' !== $has_fk ) {
            $want = rest_sanitize_boolean( $has_fk );
            $args['meta_query'] = [ [
                'key'     => '_shynh_seo_focus_keyword',
                'compare' => $want ? 'EXISTS' : 'NOT EXISTS',
            ] ];
            if ( $want ) {
                $args['meta_query'][0]['value']   = '';
                $args['meta_query'][0]['compare'] = '!=';
            }
        }

        $query = new WP_Query( $args );
        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = $this->post_summary( $post );
        }

        $response = rest_ensure_response( $items );
        $response->header( 'X-WP-Total', (int) $query->found_posts );
        $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
        return $response;
    }

    public function route_post( $request ) {
        $post = get_post( absint( $request['id'] ) );
        if ( ! $post || 'attachment' === $post->post_type ) {
            return $this->not_found();
        }
        return rest_ensure_response( $this->post_detail( $post ) );
    }

    public function route_taxonomies() {
        $out = [];
        foreach ( Shynh_SEO_Core::get_public_content_taxonomies() as $tx ) {
            $obj = get_taxonomy( $tx );
            if ( ! $obj ) {
                continue;
            }
            $out[] = [
                'name'         => $tx,
                'label'        => $obj->labels->name ?? $tx,
                'hierarchical' => (bool) $obj->hierarchical,
                'count'        => (int) wp_count_terms( [ 'taxonomy' => $tx, 'hide_empty' => false ] ),
                'post_types'   => array_values( (array) $obj->object_type ),
            ];
        }
        return rest_ensure_response( $out );
    }

    public function route_terms( $request ) {
        $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
        $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 50 ) );

        $taxonomies = Shynh_SEO_Core::get_public_content_taxonomies();
        $tax        = ( $taxonomy && in_array( $taxonomy, $taxonomies, true ) ) ? $taxonomy : $taxonomies;

        $terms = get_terms( [
            'taxonomy'   => $tax,
            'hide_empty' => false,
            'search'     => $search,
            'number'     => $per_page,
            'offset'     => ( $page - 1 ) * $per_page,
        ] );

        $out = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $out[] = $this->term_summary( $term );
            }
        }
        return rest_ensure_response( $out );
    }

    public function route_term( $request ) {
        $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term     = $taxonomy ? get_term( absint( $request['id'] ), $taxonomy ) : get_term( absint( $request['id'] ) );
        if ( ! $term || is_wp_error( $term ) ) {
            return $this->not_found();
        }
        return rest_ensure_response( $this->term_detail( $term ) );
    }

    public function route_faqs( $request ) {
        $search      = mb_strtolower( sanitize_text_field( (string) $request->get_param( 'search' ) ), 'UTF-8' );
        $source_type = sanitize_key( $request->get_param( 'source_type' ) );
        $out         = [];

        global $wpdb;

        if ( 'term' !== $source_type ) {
            $rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_shynh_seo_schema_faq' AND meta_value != ''" );
            foreach ( $rows as $row ) {
                $post = get_post( $row->post_id );
                if ( ! $post || 'publish' !== $post->post_status ) {
                    continue;
                }
                foreach ( $this->decode_faq( $row->meta_value ) as $item ) {
                    if ( $search && false === mb_strpos( mb_strtolower( $item['question'] . ' ' . $item['answer'], 'UTF-8' ), $search ) ) {
                        continue;
                    }
                    $out[] = array_merge( $item, [
                        'source_type'  => 'post',
                        'source_id'    => (int) $row->post_id,
                        'source_title' => get_the_title( $row->post_id ),
                        'url'          => get_permalink( $row->post_id ),
                    ] );
                }
            }
        }

        if ( 'post' !== $source_type ) {
            $rows = $wpdb->get_results( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = '_shynh_seo_schema_faq' AND meta_value != ''" );
            foreach ( $rows as $row ) {
                $term = get_term( $row->term_id );
                if ( ! $term || is_wp_error( $term ) ) {
                    continue;
                }
                foreach ( $this->decode_faq( $row->meta_value ) as $item ) {
                    if ( $search && false === mb_strpos( mb_strtolower( $item['question'] . ' ' . $item['answer'], 'UTF-8' ), $search ) ) {
                        continue;
                    }
                    $out[] = array_merge( $item, [
                        'source_type'  => 'term',
                        'source_id'    => (int) $row->term_id,
                        'source_title' => $term->name,
                        'url'          => get_term_link( $term ),
                    ] );
                }
            }
        }

        return $this->paginate( $out, $request, 100 );
    }

    public function route_keywords( $request ) {
        $search = mb_strtolower( sanitize_text_field( (string) $request->get_param( 'search' ) ), 'UTF-8' );

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS keyword
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_shynh_seo_focus_keyword'
               AND pm.meta_value != ''
               AND p.post_status = 'publish'"
        );

        // Prime caches once to avoid N+1 lookups inside the loop.
        $this->prime_caches( wp_list_pluck( $rows, 'post_id' ) );

        $map = [];
        foreach ( $rows as $row ) {
            $key = mb_strtolower( trim( $row->keyword ), 'UTF-8' );
            if ( '' === $key ) {
                continue;
            }
            if ( $search && false === mb_strpos( $key, $search ) ) {
                continue;
            }
            if ( ! isset( $map[ $key ] ) ) {
                $map[ $key ] = [ 'keyword' => $row->keyword, 'targets' => [], 'language_counts' => [] ];
            }
            $language = $this->get_post_language( (int) $row->post_id );
            $map[ $key ]['targets'][] = [
                'id'       => (int) $row->post_id,
                'title'    => get_the_title( $row->post_id ),
                'url'      => get_permalink( $row->post_id ),
                'type'     => get_post_type( $row->post_id ),
                'language' => $language,
            ];
            $lang_key = $language ?: '_unknown';
            $map[ $key ]['language_counts'][ $lang_key ] = ( $map[ $key ]['language_counts'][ $lang_key ] ?? 0 ) + 1;
        }

        $out = [];
        foreach ( $map as $entry ) {
            $count = count( $entry['targets'] );
            $max_language_count = ! empty( $entry['language_counts'] ) ? max( $entry['language_counts'] ) : $count;
            $out[] = [
                'keyword'         => $entry['keyword'],
                'usage_count'     => $count,
                'cannibalized'    => $max_language_count > 1,
                'language_counts' => $entry['language_counts'],
                'targets'         => $entry['targets'],
            ];
        }

        usort( $out, function ( $a, $b ) {
            return $b['usage_count'] <=> $a['usage_count'];
        } );

        return $this->paginate( $out, $request, 100 );
    }

    public function route_related( $request ) {
        $post_id = absint( $request->get_param( 'post_id' ) );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            return $this->not_found();
        }
        $keyword = get_post_meta( $post_id, '_shynh_seo_focus_keyword', true );

        return rest_ensure_response( [
            'post_id'          => $post_id,
            'focus_keyword'    => $keyword,
            'related_by_keyword' => Shynh_SEO_Internal_Linking::suggest_links_data( $keyword, $post_id ),
            'linked_by'        => Shynh_SEO_Internal_Linking::get_linked_posts_data( $post_id ),
        ] );
    }

    public function route_analysis( $request ) {
        $post_id = absint( $request->get_param( 'post_id' ) );
        $term_id = absint( $request->get_param( 'term_id' ) );

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                return $this->not_found();
            }
            return rest_ensure_response( $this->run_post_analysis( $post ) );
        }

        if ( $term_id ) {
            $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
            $term     = $taxonomy ? get_term( $term_id, $taxonomy ) : get_term( $term_id );
            if ( ! $term || is_wp_error( $term ) ) {
                return $this->not_found();
            }
            return rest_ensure_response( $this->run_term_analysis( $term ) );
        }

        return new WP_Error( 'shynh_seo_missing_param', __( 'Cần post_id hoặc term_id.', 'shynh-seo' ), [ 'status' => 400 ] );
    }

    public function route_audit() {
        global $wpdb;
        $post_types = Shynh_SEO_Core::get_related_post_types();
        if ( empty( $post_types ) ) {
            $post_types = Shynh_SEO_Core::get_public_content_post_types();
        }
        $ph = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($ph)",
            $post_types
        ) );

        $missing_fk   = $this->count_missing_meta( $post_types, '_shynh_seo_focus_keyword' );
        $missing_desc = $this->count_missing_meta( $post_types, '_shynh_seo_meta_desc' );

        $low_score = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key='_shynh_seo_score'
             WHERE p.post_status='publish' AND p.post_type IN ($ph) AND CAST(pm.meta_value AS UNSIGNED) < 40",
            $post_types
        ) );

        // Cannibalization: focus keywords used by >1 published post in the same language.
        $cann_rows = $this->get_language_scoped_cannibalization_rows( 50 );
        $cannibalization = [];
        foreach ( $cann_rows as $r ) {
            $cannibalization[] = [ 'keyword' => $r['kw'], 'count' => (int) $r['c'], 'language' => $r['language'] ];
        }

        return rest_ensure_response( [
            'generated_at'          => gmdate( 'c' ),
            'published_content'     => $total,
            'missing_focus_keyword' => $missing_fk,
            'missing_meta_desc'     => $missing_desc,
            'low_seo_score'         => $low_score,
            'cannibalization_pairs' => $cannibalization,
            'recommendation'        => 'Ưu tiên: gán focus keyword cho bài thiếu, viết meta description, và gỡ trùng lặp từ khóa (cannibalization) trước khi mở rộng nội dung mới.',
        ] );
    }

    /* ---------------------------------------------------------------------
     * Report Dashboard routes (paginated + cached)
     * ------------------------------------------------------------------- */

    public function route_report_summary( $request ) {
        $refresh   = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
        $cache_key = 'shynh_seo_report_summary';

        if ( ! $refresh ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                $cached['cached'] = true;
                return rest_ensure_response( $cached );
            }
        }

        global $wpdb;
        $post_types = Shynh_SEO_Core::get_related_post_types();
        if ( empty( $post_types ) ) {
            $post_types = Shynh_SEO_Core::get_public_content_post_types();
        }
        $ph = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($ph)",
            $post_types
        ) );

        // Per-post-type counts.
        $by_type_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_type, COUNT(*) c FROM {$wpdb->posts}
             WHERE post_status='publish' AND post_type IN ($ph) GROUP BY post_type",
            $post_types
        ) );
        $by_type = [];
        foreach ( $by_type_rows as $r ) {
            $obj = get_post_type_object( $r->post_type );
            $by_type[] = [
                'post_type' => $r->post_type,
                'label'     => $obj ? ( $obj->labels->name ?? $r->post_type ) : $r->post_type,
                'count'     => (int) $r->c,
            ];
        }

        // SEO score distribution.
        $scored = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_shynh_seo_score'
             WHERE p.post_status='publish' AND p.post_type IN ($ph)", $post_types
        ) );
        $green = $this->count_score_band( $post_types, 70, 1000 );
        $orange = $this->count_score_band( $post_types, 40, 69 );
        $red = $this->count_score_band( $post_types, -1, 39 );
        $not_scored = max( 0, $total - $scored );

        // GEO/AEO coverage.
        $geo_enabled = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_shynh_seo_geo_aeo_score'
             WHERE p.post_status='publish' AND p.post_type IN ($ph)", $post_types
        ) );
        $geo_avg = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_shynh_seo_geo_aeo_score'
             WHERE p.post_status='publish' AND p.post_type IN ($ph)", $post_types
        ) );

        $cann_rows = $this->get_language_scoped_cannibalization_rows();

        $data = [
            'generated_at'          => gmdate( 'c' ),
            'cached'                => false,
            'published_content'     => $total,
            'by_post_type'          => $by_type,
            'seo_score'             => [
                'green'      => $green,
                'orange'     => $orange,
                'red'        => $red,
                'not_scored' => $not_scored,
            ],
            'geo_aeo'               => [
                'enabled_count' => $geo_enabled,
                'avg_score'     => round( $geo_avg, 1 ),
                'coverage_pct'  => $total ? round( $geo_enabled / $total * 100, 1 ) : 0,
            ],
            'missing_focus_keyword' => $this->count_missing_meta( $post_types, '_shynh_seo_focus_keyword' ),
            'missing_meta_desc'     => $this->count_missing_meta( $post_types, '_shynh_seo_meta_desc' ),
            'missing_meta_title'    => $this->count_missing_meta( $post_types, '_shynh_seo_title' ),
            'cannibalization_count' => count( $cann_rows ),
        ];

        set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
        return rest_ensure_response( $data );
    }

    public function route_report_rows( $request ) {
        $post_type = sanitize_key( $request->get_param( 'post_type' ) );
        $taxonomy  = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term      = absint( $request->get_param( 'term' ) );
        $search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $orderby   = sanitize_key( $request->get_param( 'orderby' ) ) ?: 'date';
        $page      = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page  = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) );

        $allowed = Shynh_SEO_Core::get_public_content_post_types();
        $types   = ( $post_type && in_array( $post_type, $allowed, true ) ) ? $post_type : $allowed;

        $meta_query = [ 'relation' => 'AND' ];

        $this->band_meta_query( $meta_query, '_shynh_seo_score', $request->get_param( 'score_min' ), $request->get_param( 'score_max' ) );
        $this->band_meta_query( $meta_query, '_shynh_seo_geo_aeo_score', $request->get_param( 'geo_min' ), $request->get_param( 'geo_max' ) );

        $missing = sanitize_key( $request->get_param( 'missing' ) );
        $missing_keys = [
            'focus_keyword' => '_shynh_seo_focus_keyword',
            'meta_desc'     => '_shynh_seo_meta_desc',
            'meta_title'    => '_shynh_seo_title',
        ];
        if ( isset( $missing_keys[ $missing ] ) ) {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => $missing_keys[ $missing ], 'compare' => 'NOT EXISTS' ],
                [ 'key' => $missing_keys[ $missing ], 'value' => '', 'compare' => '=' ],
            ];
        }

        if ( rest_sanitize_boolean( $request->get_param( 'has_faq' ) ) ) {
            $meta_query[] = [ 'key' => '_shynh_seo_schema_faq', 'value' => '', 'compare' => '!=' ];
        }
        if ( rest_sanitize_boolean( $request->get_param( 'has_schema' ) ) ) {
            $meta_query[] = [ 'key' => '_shynh_seo_schema_type', 'value' => '', 'compare' => '!=' ];
        }

        $args = [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => $search,
        ];
        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }
        if ( $taxonomy && $term ) {
            $args['tax_query'] = [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term ] ];
        }

        switch ( $orderby ) {
            case 'seo_score':
                $args['meta_key'] = '_shynh_seo_score';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'geo_score':
                $args['meta_key'] = '_shynh_seo_geo_aeo_score';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'title':
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
                break;
            case 'modified':
                $args['orderby'] = 'modified';
                $args['order']   = 'DESC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
        }

        $query = new WP_Query( $args );
        $this->prime_caches( wp_list_pluck( $query->posts, 'ID' ) );

        $rows = [];
        foreach ( $query->posts as $post ) {
            $internal_out = get_post_meta( $post->ID, '_shynh_seo_internal_links', true );
            $rows[] = [
                'id'                  => (int) $post->ID,
                'type'                => $post->post_type,
                'title'               => get_the_title( $post ),
                'url'                 => get_permalink( $post ),
                'edit_url'            => get_edit_post_link( $post->ID, 'raw' ),
                'focus_keyword'       => (string) get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true ),
                'meta_title'          => (string) get_post_meta( $post->ID, '_shynh_seo_title', true ),
                'meta_desc'           => (string) get_post_meta( $post->ID, '_shynh_seo_meta_desc', true ),
                'seo_score'           => $this->meta_int( $post->ID, '_shynh_seo_score' ),
                'geo_aeo_score'       => $this->meta_int( $post->ID, '_shynh_seo_geo_aeo_score' ),
                'ai_search_score'     => $this->meta_int( $post->ID, '_shynh_seo_ai_search_score' ),
                'geo_aeo_mode'        => (string) get_post_meta( $post->ID, '_shynh_seo_geo_aeo_mode', true ),
                'internal_links_out'  => is_array( $internal_out ) ? count( $internal_out ) : 0,
                'has_faq'             => '' !== trim( (string) get_post_meta( $post->ID, '_shynh_seo_schema_faq', true ) ),
                'has_schema'          => '' !== trim( (string) get_post_meta( $post->ID, '_shynh_seo_schema_type', true ) ),
            ];
        }

        $response = rest_ensure_response( $rows );
        $response->header( 'X-WP-Total', (int) $query->found_posts );
        $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
        return $response;
    }

    public function route_report_keywords( $request ) {
        return $this->route_keywords( $request );
    }

    public function route_report_geo( $request ) {
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) );
        $post_type = sanitize_key( $request->get_param( 'post_type' ) );

        $allowed = Shynh_SEO_Core::get_public_content_post_types();
        $types   = ( $post_type && in_array( $post_type, $allowed, true ) ) ? $post_type : $allowed;

        $query = new WP_Query( [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'meta_key'       => '_shynh_seo_geo_aeo_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC', // worst first (needs attention)
            'meta_query'     => [ [ 'key' => '_shynh_seo_geo_aeo_score', 'compare' => 'EXISTS' ] ],
        ] );

        $this->prime_caches( wp_list_pluck( $query->posts, 'ID' ) );

        $rows = [];
        foreach ( $query->posts as $post ) {
            $issues = get_post_meta( $post->ID, '_shynh_seo_geo_aeo_issues', true );
            $rows[] = [
                'id'              => (int) $post->ID,
                'type'            => $post->post_type,
                'title'           => get_the_title( $post ),
                'url'             => get_permalink( $post ),
                'edit_url'        => get_edit_post_link( $post->ID, 'raw' ),
                'geo_aeo_score'   => $this->meta_int( $post->ID, '_shynh_seo_geo_aeo_score' ),
                'ai_search_score' => $this->meta_int( $post->ID, '_shynh_seo_ai_search_score' ),
                'focus_keyword'   => (string) get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true ),
                'issues'          => is_array( $issues ) ? $issues : [],
            ];
        }

        $response = rest_ensure_response( $rows );
        $response->header( 'X-WP-Total', (int) $query->found_posts );
        $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
        return $response;
    }

    /**
     * Re-score a small batch of posts. Browser loops until done:true to avoid
     * a single long-running request that could time out.
     */
    public function route_report_rescan( $request ) {
        $offset    = max( 0, absint( $request->get_param( 'offset' ) ) );
        $limit     = min( 10, max( 1, absint( $request->get_param( 'limit' ) ) ?: 10 ) );
        $scope     = sanitize_key( $request->get_param( 'scope' ) ) ?: 'missing';
        $post_type = sanitize_key( $request->get_param( 'post_type' ) );

        $allowed = Shynh_SEO_Core::get_public_content_post_types();
        $types   = ( $post_type && in_array( $post_type, $allowed, true ) ) ? $post_type : $allowed;

        $args = [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ];
        if ( 'missing' === $scope ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => '_shynh_seo_score', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_shynh_seo_internal_link_index', 'compare' => 'NOT EXISTS' ],
            ];
        }

        $query   = new WP_Query( $args );
        $ids     = $query->posts;
        $total   = (int) $query->found_posts;
        $processed = 0;

        $analysis = Shynh_SEO_Analysis::get_instance();
        foreach ( $ids as $pid ) {
            $result = $analysis->analyze_post( $pid );
            if ( $result ) {
                $analysis->persist_post_analysis( $pid, $result );
                $processed++;
            }
        }

        // For 'missing' scope the matched set shrinks as we score, so keep offset 0.
        $next_offset = ( 'missing' === $scope ) ? 0 : $offset + $limit;
        $done        = ( 'missing' === $scope ) ? ( count( $ids ) === 0 ) : ( $next_offset >= $total );

        // Invalidate summary cache after any scoring.
        if ( $processed > 0 ) {
            delete_transient( 'shynh_seo_report_summary' );
        }

        return rest_ensure_response( [
            'processed'   => $processed,
            'total'       => $total,
            'next_offset' => $next_offset,
            'done'        => $done,
        ] );
    }

    /* ---------------------------------------------------------------------
     * Fix suggestions + duplicate detection
     * ------------------------------------------------------------------- */

    public function route_report_fixes( $request ) {
        $post_type = sanitize_key( $request->get_param( 'post_type' ) );
        $issue     = sanitize_key( $request->get_param( 'issue' ) );
        $page      = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page  = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) );

        $allowed = Shynh_SEO_Core::get_public_content_post_types();
        $types   = ( $post_type && in_array( $post_type, $allowed, true ) ) ? $post_type : $allowed;

        // Posts that actually have stored SEO issues.
        $meta_query = [
            [ 'key' => '_shynh_seo_seo_issues', 'compare' => 'EXISTS' ],
        ];

        $query = new WP_Query( [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'meta_key'       => '_shynh_seo_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC', // worst score first
            'meta_query'     => $meta_query,
        ] );

        $this->prime_caches( wp_list_pluck( $query->posts, 'ID' ) );

        $rows = [];
        foreach ( $query->posts as $post ) {
            $seo_issues = get_post_meta( $post->ID, '_shynh_seo_seo_issues', true );
            $geo_issues = get_post_meta( $post->ID, '_shynh_seo_geo_aeo_issues', true );
            $seo_issues = is_array( $seo_issues ) ? $seo_issues : [];
            $geo_issues = is_array( $geo_issues ) ? $geo_issues : [];

            if ( $issue ) {
                $seo_issues = array_values( array_filter( $seo_issues, function ( $i ) use ( $issue ) {
                    return ( $i['key'] ?? '' ) === $issue;
                } ) );
                if ( empty( $seo_issues ) ) {
                    continue;
                }
            }

            $impact = 0;
            foreach ( $seo_issues as $i ) {
                $impact += (int) ( $i['weight'] ?? 0 );
            }

            $rows[] = [
                'id'         => (int) $post->ID,
                'type'       => $post->post_type,
                'title'      => get_the_title( $post ),
                'url'        => get_permalink( $post ),
                'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
                'seo_score'  => $this->meta_int( $post->ID, '_shynh_seo_score' ),
                'impact'     => $impact,
                'seo_fixes'  => $seo_issues,
                'geo_fixes'  => $geo_issues,
            ];
        }

        $response = rest_ensure_response( $rows );
        $response->header( 'X-WP-Total', (int) $query->found_posts );
        $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
        return $response;
    }

    public function route_report_fixes_summary( $request ) {
        $refresh   = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
        $cache_key = 'shynh_seo_fixes_summary';
        if ( ! $refresh ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                $cached['cached'] = true;
                return rest_ensure_response( $cached );
            }
        }

        global $wpdb;
        // Pull all stored SEO issue blobs and tally by issue key.
        $rows = $wpdb->get_col(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_shynh_seo_seo_issues' AND p.post_status = 'publish'"
        );

        $tally = [];
        foreach ( $rows as $blob ) {
            $issues = maybe_unserialize( $blob );
            if ( ! is_array( $issues ) ) {
                continue;
            }
            foreach ( $issues as $i ) {
                $key = $i['key'] ?? '';
                if ( '' === $key ) {
                    continue;
                }
                if ( ! isset( $tally[ $key ] ) ) {
                    $tally[ $key ] = [
                        'issue'      => $key,
                        'posts'      => 0,
                        'weight'     => (int) ( $i['weight'] ?? 0 ),
                        'action_tip' => $i['action_tip'] ?? '',
                        'message'    => $i['message'] ?? '',
                    ];
                }
                $tally[ $key ]['posts']++;
            }
        }

        $tally = array_values( $tally );
        usort( $tally, function ( $a, $b ) {
            return ( $b['posts'] * $b['weight'] ) <=> ( $a['posts'] * $a['weight'] );
        } );

        $data = [ 'cached' => false, 'generated_at' => gmdate( 'c' ), 'top_issues' => $tally ];
        set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
        return rest_ensure_response( $data );
    }

    public function route_report_duplicates( $request ) {
        if ( ! class_exists( 'Shynh_SEO_Duplicate_Detector' ) ) {
            return rest_ensure_response( [] );
        }
        $pairs = Shynh_SEO_Duplicate_Detector::get_report();

        $this->prime_caches( array_merge( wp_list_pluck( $pairs, 'a' ), wp_list_pluck( $pairs, 'b' ) ) );

        $out = [];
        foreach ( $pairs as $p ) {
            $a = get_post( $p['a'] );
            $b = get_post( $p['b'] );
            if ( ! $a || ! $b ) {
                continue;
            }
            $out[] = [
                'similarity' => (int) $p['similarity'],
                'reasons'    => $p['reasons'],
                'a' => [ 'id' => (int) $a->ID, 'title' => get_the_title( $a ), 'url' => get_permalink( $a ), 'edit_url' => get_edit_post_link( $a->ID, 'raw' ) ],
                'b' => [ 'id' => (int) $b->ID, 'title' => get_the_title( $b ), 'url' => get_permalink( $b ), 'edit_url' => get_edit_post_link( $b->ID, 'raw' ) ],
            ];
        }
        return $this->paginate( $out, $request, 100 );
    }

    public function route_report_duplicates_scan( $request ) {
        if ( ! class_exists( 'Shynh_SEO_Duplicate_Detector' ) ) {
            return new WP_Error( 'shynh_seo_no_module', 'Duplicate detector chưa sẵn sàng.', [ 'status' => 500 ] );
        }
        $offset = max( 0, absint( $request->get_param( 'offset' ) ) );
        $limit  = min( 20, max( 1, absint( $request->get_param( 'limit' ) ) ?: 15 ) );
        return rest_ensure_response( Shynh_SEO_Duplicate_Detector::scan_batch( $offset, $limit ) );
    }

    /* ---------------------------------------------------------------------
     * AI context routes (site rules, schema types, blocks, brief, drafts)
     * ------------------------------------------------------------------- */

    public function route_guidelines() {
        $settings = Shynh_SEO_Core::get_settings();
        $rules    = get_option( 'shynh_seo_rules', [] );
        $rules    = is_array( $rules ) ? $rules : [];

        $workflow = '';
        $wf_file  = SHYNH_SEO_PATH . 'SEO-AEO-GEO-CONTENT-WORKFLOW.md';
        if ( is_readable( $wf_file ) ) {
            $workflow = file_get_contents( $wf_file );
        }

        return rest_ensure_response( [
            'seo_rules'      => $rules,
            'geo_aeo'        => [
                'enabled'              => ! empty( $settings['geo_aeo_enabled'] ),
                'min_score'            => (int) ( $settings['geo_aeo_min_score'] ?? 80 ),
                'require_quick_answer' => ! empty( $settings['geo_aeo_require_quick_answer'] ),
            ],
            'medical_safety' => [
                'avoid_absolute_claims' => [ 'guaranteed', 'no risk', '100% safe', 'permanent cure', 'always effective', 'FDA approved (không nguồn)' ],
                'must_include'          => [ 'consultation', 'contraindication', 'aftercare', 'result variability' ],
                'reviewer_required'     => 'Nội dung y khoa cần tín hiệu người duyệt (reviewed_by / reviewed_date).',
            ],
            'formatting'     => [
                'answer_first'   => 'Đoạn đầu sau H1 phải trả lời trực tiếp câu hỏi chính của khách.',
                'quick_answer'   => 'Quick Answer 60–100 từ gần đầu bài (tối ưu AEO).',
                'strong_em'      => 'Dùng <strong> cho entity/keyword quan trọng, <em> cho lưu ý/caveat.',
                'faq_block'      => 'Nhiều FAQ liên tiếp phải dùng block kp/faq (không dựng bằng heading/paragraph rời).',
                'preserve_media' => 'Giữ nguyên ảnh gốc và featured image, trừ khi được yêu cầu đổi.',
            ],
            'workflow_markdown' => $workflow,
        ] );
    }

    public function route_schema_types() {
        $out = [ 'post' => [], 'term' => [] ];
        foreach ( [ 'post', 'term' ] as $ctx ) {
            foreach ( Shynh_SEO_Schema::get_schema_type_options( $ctx ) as $value => $opt ) {
                if ( '' === $value ) {
                    continue;
                }
                $out[ $ctx ][] = [
                    'type'   => $value,
                    'label'  => $opt['label'],
                    'when'   => $opt['desc'],
                    'fields' => array_map( function ( $f ) {
                        return '_shynh_seo_' . $f;
                    }, isset( $opt['fields'] ) ? $opt['fields'] : [] ),
                ];
            }
        }
        return rest_ensure_response( $out );
    }

    public function route_blocks() {
        $manifest = get_theme_file_path( 'docs/blocks-manifest.json' );
        if ( ! is_readable( $manifest ) ) {
            return rest_ensure_response( [ 'blocks' => [], 'note' => 'blocks-manifest.json không tìm thấy.' ] );
        }
        $data   = json_decode( file_get_contents( $manifest ), true );
        $blocks = ( is_array( $data ) && isset( $data['blocks'] ) ) ? $data['blocks'] : [];

        $out = [];
        foreach ( $blocks as $name => $b ) {
            $out[] = [
                'name'               => $name,
                'title'              => $b['title'] ?? '',
                'description'        => $b['description'] ?? '',
                'keywords'           => $b['keywords'] ?? [],
                'attributes'         => is_array( $b['attributes'] ?? null ) ? array_keys( $b['attributes'] ) : [],
                'allowedInnerBlocks' => $b['allowedInnerBlocks'] ?? null,
            ];
        }
        return rest_ensure_response( [ 'blocks' => $out, 'count' => count( $out ) ] );
    }

    public function route_content_brief( $request ) {
        $post_id = absint( $request->get_param( 'post_id' ) );
        $keyword = sanitize_text_field( (string) $request->get_param( 'keyword' ) );

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                return $this->not_found();
            }
            if ( '' === $keyword ) {
                $keyword = (string) get_post_meta( $post_id, '_shynh_seo_focus_keyword', true );
            }
        }
        if ( '' === $keyword ) {
            return new WP_Error( 'shynh_seo_missing_param', __( 'Cần keyword hoặc post_id.', 'shynh-seo' ), [ 'status' => 400 ] );
        }

        // Existing posts targeting the same/related keyword (cannibalization awareness).
        $similar = Shynh_SEO_Internal_Linking::suggest_links_data( $keyword, $post_id );

        // Relevant FAQ across the site.
        $faq_req  = new WP_REST_Request( 'GET' );
        $faq_req->set_param( 'search', $keyword );
        $faq_resp = $this->route_faqs( $faq_req );
        $faqs     = is_wp_error( $faq_resp ) ? [] : array_slice( (array) $faq_resp->get_data(), 0, 10 );

        // Knowledge vault resources (docs/media/videos) for the topic.
        $knowledge = [];
        if ( $post_id ) {
            $knowledge = $this->resolve_knowledge_for_post( $post_id );
        }

        // Recommended taxonomy placement (terms whose name matches the keyword).
        $placement = [];
        $terms     = get_terms( [
            'taxonomy'   => Shynh_SEO_Core::get_public_content_taxonomies(),
            'hide_empty' => false,
            'search'     => $keyword,
            'number'     => 8,
        ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $placement[] = [ 'taxonomy' => $t->taxonomy, 'term_id' => (int) $t->term_id, 'name' => $t->name ];
            }
        }

        return rest_ensure_response( [
            'target_keyword'        => $keyword,
            'from_post'             => $post_id ?: null,
            'cannibalization_risk'  => $similar, // posts already using this keyword
            'internal_link_targets' => $similar, // same set — good anchor candidates
            'related_faq'           => $faqs,
            'knowledge'             => $knowledge,
            'recommended_placement' => $placement,
            'recommended_schema'    => $this->recommend_schema_for_keyword( $keyword ),
            'guidelines_hint'       => 'Gọi GET /guidelines để lấy đầy đủ luật viết + an toàn y khoa; GET /blocks để biết block hợp lệ; GET /schema-types để chọn schema.',
        ] );
    }

    private function recommend_schema_for_keyword( $keyword ) {
        $k = mb_strtolower( $keyword, 'UTF-8' );
        if ( preg_match( '/(gi\x{00e1}|b\x{1ea3}ng gi\x{00e1}|bao nhi\x{00ea}u)/u', $k ) ) {
            return 'Product';
        }
        if ( preg_match( '/(quy tr\x{00ec}nh|c\x{00e1}ch|h\x{01b0}\x{1edb}ng d\x{1eab}n|b\x{01b0}\x{1edb}c)/u', $k ) ) {
            return 'HowTo';
        }
        if ( preg_match( '/(review|\x{0111}\x{00e1}nh gi\x{00e1})/u', $k ) ) {
            return 'Review';
        }
        return 'MedicalWebPage';
    }

    /**
     * Create a DRAFT post from AI-provided content. Never publishes.
     */
    public function route_create_draft( $request ) {
        $body = $request->get_json_params();
        $body = is_array( $body ) ? $body : [];

        $title = isset( $body['title'] ) ? sanitize_text_field( wp_unslash( $body['title'] ) ) : '';
        if ( '' === $title ) {
            return new WP_Error( 'shynh_seo_missing_title', __( 'Cần title.', 'shynh-seo' ), [ 'status' => 400 ] );
        }

        $post_type     = isset( $body['post_type'] ) ? sanitize_key( $body['post_type'] ) : 'post';
        $allowed_types = Shynh_SEO_Core::get_public_content_post_types();
        if ( ! in_array( $post_type, $allowed_types, true ) ) {
            $post_type = 'post';
        }

        $content = isset( $body['content'] ) ? wp_kses_post( wp_unslash( $body['content'] ) ) : '';

        // Always a draft — never published from the API.
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => $post_type,
            'post_status'  => 'draft',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'shynh_seo_draft_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Optional taxonomy terms: { taxonomy: [term_id, ...] }.
        if ( ! empty( $body['terms'] ) && is_array( $body['terms'] ) ) {
            foreach ( $body['terms'] as $tax => $ids ) {
                $tax = sanitize_key( $tax );
                if ( taxonomy_exists( $tax ) ) {
                    wp_set_object_terms( $post_id, array_map( 'absint', (array) $ids ), $tax );
                }
            }
        }

        // SEO meta (same whitelist as PATCH /posts/{id}/seo).
        $meta_map = [
            'focus_keyword'       => [ '_shynh_seo_focus_keyword', 'sanitize_text_field' ],
            'meta_title'          => [ '_shynh_seo_title', 'sanitize_text_field' ],
            'meta_desc'           => [ '_shynh_seo_meta_desc', 'sanitize_textarea_field' ],
            'breadcrumb_title'    => [ '_shynh_seo_breadcrumb_title', 'sanitize_text_field' ],
            'schema_type'         => [ '_shynh_seo_schema_type', 'sanitize_text_field' ],
            'schema_service_name' => [ '_shynh_seo_schema_service_name', 'sanitize_text_field' ],
            'geo_aeo_mode'        => [ '_shynh_seo_geo_aeo_mode', 'sanitize_key' ],
        ];
        foreach ( $meta_map as $field => $conf ) {
            if ( isset( $body[ $field ] ) ) {
                update_post_meta( $post_id, $conf[0], call_user_func( $conf[1], wp_unslash( $body[ $field ] ) ) );
            }
        }

        // Optional FAQ.
        if ( isset( $body['faq'] ) ) {
            update_post_meta( $post_id, '_shynh_seo_schema_faq', wp_json_encode( $this->sanitize_faq_input( $body['faq'] ) ) );
        }

        // Score the fresh draft.
        $this->run_post_analysis( get_post( $post_id ) );

        $detail             = $this->post_detail( get_post( $post_id ) );
        $detail['edit_url'] = get_edit_post_link( $post_id, 'raw' );
        $detail['notice']   = 'Bài được tạo ở trạng thái Draft. Cần người duyệt trước khi xuất bản.';
        return rest_ensure_response( $detail );
    }

    public function route_keyword_suggestions( $request ) {
        $search  = mb_strtolower( sanitize_text_field( (string) $request->get_param( 'search' ) ), 'UTF-8' );
        $only    = sanitize_key( $request->get_param( 'only' ) ); // 'gap' | 'covered' | ''
        $rows    = Shynh_SEO_Keyword_Expander::get_all();

        $out = [];
        foreach ( $rows as $r ) {
            if ( 'gap' === $only && ! empty( $r['covered'] ) ) {
                continue;
            }
            if ( 'covered' === $only && empty( $r['covered'] ) ) {
                continue;
            }
            if ( $search && false === mb_strpos( mb_strtolower( $r['candidate'], 'UTF-8' ), $search ) ) {
                continue;
            }
            $out[] = $r;
        }

        $response = $this->paginate( $out, $request, 100 );
        $response->header( 'X-Shynh-Last-Run', (string) Shynh_SEO_Keyword_Expander::last_run() );
        return $response;
    }

    public function route_keyword_expand() {
        $stats = Shynh_SEO_Keyword_Expander::run();
        return rest_ensure_response( $stats );
    }

    public function route_keyword_seeds( $request ) {
        $search = mb_strtolower( sanitize_text_field( (string) $request->get_param( 'search' ) ), 'UTF-8' );
        $seeds  = Shynh_SEO_Keyword_Expander::get_seeds();
        if ( $search ) {
            $seeds = array_values( array_filter( $seeds, function ( $s ) use ( $search ) {
                return false !== mb_strpos( mb_strtolower( $s['keyword'], 'UTF-8' ), $search );
            } ) );
        }
        return $this->paginate( $seeds, $request, 500 );
    }

    public function route_keyword_expand_seed( $request ) {
        $keyword = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
        $depth   = absint( $request->get_param( 'depth' ) ) ?: 4;
        if ( '' === $keyword ) {
            return new WP_Error( 'shynh_seo_missing_param', __( 'Cần tham số keyword.', 'shynh-seo' ), [ 'status' => 400 ] );
        }
        $tree = Shynh_SEO_Keyword_Expander::expand_seed( $keyword, $depth );

        // Summary by level + intent for the grouped table view.
        $by_level = [];
        foreach ( $tree['nodes'] as $n ) {
            if ( (int) $n['level'] <= 1 ) {
                continue;
            }
            $by_level[ (int) $n['level'] ][] = $n;
        }
        ksort( $by_level );
        $tree['by_level'] = $by_level;
        $tree['stats']    = [
            'nodes' => count( $tree['nodes'] ),
            'gaps'  => count( array_filter( $tree['nodes'], function ( $n ) { return empty( $n['covered'] ) && (int) $n['level'] > 1; } ) ),
        ];

        return rest_ensure_response( $tree );
    }

    public function route_keyword_map( $request ) {
        $taxonomy        = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term            = absint( $request->get_param( 'term' ) );
        $min_usage       = max( 1, absint( $request->get_param( 'min_usage' ) ) );
        $top             = min( 500, max( 10, absint( $request->get_param( 'top' ) ) ?: 200 ) );
        $include_suggest = rest_sanitize_boolean( $request->get_param( 'include_suggested' ) );

        // Covered keywords → targeted posts (reuse the keyword aggregation query).
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS keyword
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_shynh_seo_focus_keyword' AND pm.meta_value != '' AND p.post_status = 'publish'"
        );
        $this->prime_caches( wp_list_pluck( $rows, 'post_id' ) );

        $kw_map = [];
        foreach ( $rows as $row ) {
            $key = mb_strtolower( trim( $row->keyword ), 'UTF-8' );
            if ( '' === $key ) {
                continue;
            }
            if ( $taxonomy && $term && ! has_term( $term, $taxonomy, $row->post_id ) ) {
                continue;
            }
            $language = $this->get_post_language( (int) $row->post_id ) ?: '_unknown';
            $kw_map[ $key ][ $language ][] = (int) $row->post_id;
        }

        // Keep the strongest keywords first.
        uasort( $kw_map, function ( $a, $b ) { return $this->count_language_group_posts( $b ) <=> $this->count_language_group_posts( $a ); } );
        $kw_map = array_slice( $kw_map, 0, $top, true );

        $nodes = [];
        $edges = [];
        $seen  = [];
        $add_node = function ( $id, $label, $type, $extra = [] ) use ( &$nodes, &$seen ) {
            if ( isset( $seen[ $id ] ) ) {
                return;
            }
            $seen[ $id ] = true;
            $nodes[] = array_merge( [ 'id' => $id, 'label' => $label, 'type' => $type ], $extra );
        };

        foreach ( $kw_map as $key => $language_groups ) {
            $post_ids = [];
            foreach ( $language_groups as $ids ) {
                $post_ids = array_merge( $post_ids, $ids );
            }
            $usage = count( $post_ids );
            $max_language_usage = 0;
            foreach ( $language_groups as $ids ) {
                $max_language_usage = max( $max_language_usage, count( $ids ) );
            }
            if ( $usage < $min_usage ) {
                continue;
            }
            $kw_id = 'kw:' . md5( $key );
            $add_node( $kw_id, $key, 'keyword', [ 'usage' => $usage, 'cannibalized' => $max_language_usage > 1, 'status' => 'covered' ] );

            foreach ( $post_ids as $pid ) {
                $post_node = 'post:' . $pid;
                $add_node( $post_node, get_the_title( $pid ), 'post', [ 'url' => get_permalink( $pid ), 'edit_url' => get_edit_post_link( $pid, 'raw' ) ] );
                $edges[] = [ 'source' => $kw_id, 'target' => $post_node, 'rel' => 'targets' ];

                // Topic node from primary term.
                $topic = $this->primary_category_name( $pid );
                if ( $topic ) {
                    $topic_id = 'topic:' . md5( $topic );
                    $add_node( $topic_id, $topic, 'topic', [] );
                    $edges[] = [ 'source' => $post_node, 'target' => $topic_id, 'rel' => 'in_topic' ];
                }
            }
        }

        // Optional: gap/suggested keywords (not yet targeted).
        if ( $include_suggest ) {
            $suggested = Shynh_SEO_Keyword_Expander::get_all();
            $count = 0;
            foreach ( $suggested as $s ) {
                if ( ! empty( $s['covered'] ) ) {
                    continue;
                }
                $sid = 'kw:' . md5( mb_strtolower( $s['candidate'], 'UTF-8' ) );
                $add_node( $sid, $s['candidate'], 'keyword', [ 'status' => 'suggested', 'score' => $s['score'] ] );
                // Link suggested keyword to its seed keyword node when present.
                foreach ( (array) ( $s['seeds'] ?? [] ) as $seed ) {
                    $seed_id = 'kw:' . md5( mb_strtolower( $seed, 'UTF-8' ) );
                    if ( isset( $seen[ $seed_id ] ) ) {
                        $edges[] = [ 'source' => $seed_id, 'target' => $sid, 'rel' => 'expands' ];
                    }
                }
                if ( ++$count >= 100 ) {
                    break;
                }
            }
        }

        return rest_ensure_response( [
            'nodes' => $nodes,
            'edges' => $edges,
            'stats' => [ 'node_count' => count( $nodes ), 'edge_count' => count( $edges ) ],
        ] );
    }

    /* ---------------------------------------------------------------------
     * Write routes
     * ------------------------------------------------------------------- */

    public function route_update_post_seo( $request ) {
        $post = get_post( absint( $request['id'] ) );
        if ( ! $post || 'attachment' === $post->post_type ) {
            return $this->not_found();
        }

        $map = [
            'focus_keyword'       => [ '_shynh_seo_focus_keyword', 'sanitize_text_field' ],
            'meta_title'          => [ '_shynh_seo_title', 'sanitize_text_field' ],
            'meta_desc'           => [ '_shynh_seo_meta_desc', 'sanitize_textarea_field' ],
            'breadcrumb_title'    => [ '_shynh_seo_breadcrumb_title', 'sanitize_text_field' ],
            'schema_type'         => [ '_shynh_seo_schema_type', 'sanitize_text_field' ],
            'schema_service_name' => [ '_shynh_seo_schema_service_name', 'sanitize_text_field' ],
            'geo_aeo_mode'        => [ '_shynh_seo_geo_aeo_mode', 'sanitize_key' ],
        ];

        $changed = $this->apply_meta_updates( $post->ID, 'post', $request, $map );

        // Re-run analysis + persist scores.
        $analysis = $this->run_post_analysis( $post );

        $detail            = $this->post_detail( get_post( $post->ID ) );
        $detail['updated'] = $changed;
        return rest_ensure_response( $detail );
    }

    public function route_update_post_faq( $request ) {
        $post = get_post( absint( $request['id'] ) );
        if ( ! $post || 'attachment' === $post->post_type ) {
            return $this->not_found();
        }

        $faq   = $request->get_param( 'faq' );
        $clean = $this->sanitize_faq_input( $faq );
        update_post_meta( $post->ID, '_shynh_seo_schema_faq', wp_json_encode( $clean ) );

        $detail            = $this->post_detail( get_post( $post->ID ) );
        $detail['updated'] = [ 'faq' => count( $clean ) ];
        return rest_ensure_response( $detail );
    }

    public function route_update_term_seo( $request ) {
        $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term     = $taxonomy ? get_term( absint( $request['id'] ), $taxonomy ) : get_term( absint( $request['id'] ) );
        if ( ! $term || is_wp_error( $term ) ) {
            return $this->not_found();
        }

        $map = [
            'focus_keyword'    => [ '_shynh_seo_focus_keyword', 'sanitize_text_field' ],
            'meta_title'       => [ '_shynh_seo_title', 'sanitize_text_field' ],
            'meta_desc'        => [ '_shynh_seo_meta_desc', 'sanitize_textarea_field' ],
            'breadcrumb_title' => [ '_shynh_seo_breadcrumb_title', 'sanitize_text_field' ],
            'schema_type'      => [ '_shynh_seo_schema_type', 'sanitize_text_field' ],
            'geo_aeo_mode'     => [ '_shynh_seo_geo_aeo_mode', 'sanitize_key' ],
        ];

        $changed = $this->apply_meta_updates( $term->term_id, 'term', $request, $map );

        $detail            = $this->term_detail( get_term( $term->term_id, $term->taxonomy ) );
        $detail['updated'] = $changed;
        return rest_ensure_response( $detail );
    }

    private function apply_meta_updates( $object_id, $object_type, $request, $map ) {
        $changed = [];
        $body    = $request->get_json_params();
        $body    = is_array( $body ) ? $body : [];

        foreach ( $map as $field => $conf ) {
            if ( ! array_key_exists( $field, $body ) ) {
                continue;
            }
            list( $meta_key, $callback ) = $conf;
            $value = call_user_func( $callback, wp_unslash( $body[ $field ] ) );

            if ( 'term' === $object_type ) {
                update_term_meta( $object_id, $meta_key, $value );
            } else {
                update_post_meta( $object_id, $meta_key, $value );
            }
            $changed[ $field ] = $value;
        }

        return $changed;
    }

    /* ---------------------------------------------------------------------
     * Builders
     * ------------------------------------------------------------------- */

    private function post_summary( $post ) {
        $plain = $this->plain_text( $post );
        return [
            'id'               => (int) $post->ID,
            'type'             => $post->post_type,
            'title'            => get_the_title( $post ),
            'slug'             => $post->post_name,
            'url'              => get_permalink( $post ),
            'status'           => $post->post_status,
            'date'             => mysql2date( 'c', $post->post_date_gmt ),
            'modified'         => mysql2date( 'c', $post->post_modified_gmt ),
            'focus_keyword'    => (string) get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true ),
            'meta_title'       => (string) get_post_meta( $post->ID, '_shynh_seo_title', true ),
            'meta_desc'        => (string) get_post_meta( $post->ID, '_shynh_seo_meta_desc', true ),
            'seo_score'        => $this->meta_int( $post->ID, '_shynh_seo_score' ),
            'geo_aeo_score'    => $this->meta_int( $post->ID, '_shynh_seo_geo_aeo_score' ),
            'ai_search_score'  => $this->meta_int( $post->ID, '_shynh_seo_ai_search_score' ),
            'word_count'       => $this->word_count( $plain ),
            'primary_category' => $this->primary_category_name( $post->ID ),
        ];
    }

    private function post_detail( $post ) {
        $context = Shynh_SEO_Content_Extractor::extract( $post->ID, $post->post_content );
        $plain   = trim( $context['plain_text'] );

        $summary = $this->post_summary( $post );

        $internal = [];
        $external = [];
        foreach ( (array) $context['links'] as $link ) {
            $entry = [
                'href' => $link['href'] ?? '',
                'text' => $link['text'] ?? '',
            ];
            if ( ! empty( $link['is_internal'] ) ) {
                $internal[] = $entry;
            } else {
                $external[] = $entry;
            }
        }

        $detail = array_merge( $summary, [
            'content_plain'    => $plain,
            'content_markdown' => $this->html_to_markdown( $context['html'] ),
            'outline'          => array_map( function ( $h ) {
                return [ 'level' => (int) ( $h['level'] ?? 0 ), 'text' => $h['text'] ?? '' ];
            }, (array) $context['headings'] ),
            'images'           => array_map( function ( $img ) {
                return [ 'src' => $img['src'] ?? '', 'alt' => $img['alt'] ?? '' ];
            }, (array) $context['images'] ),
            'internal_links'   => $internal,
            'external_links'   => $external,
            'faq'              => $this->decode_faq( get_post_meta( $post->ID, '_shynh_seo_schema_faq', true ) ),
            'schema'           => [
                'type'            => (string) get_post_meta( $post->ID, '_shynh_seo_schema_type', true ),
                'service_name'    => (string) get_post_meta( $post->ID, '_shynh_seo_schema_service_name', true ),
                'reviewed_by'     => (string) get_post_meta( $post->ID, '_shynh_seo_schema_reviewed_by', true ),
                'reviewed_date'   => (string) get_post_meta( $post->ID, '_shynh_seo_schema_reviewed_date', true ),
                'geo_aeo_mode'    => (string) get_post_meta( $post->ID, '_shynh_seo_geo_aeo_mode', true ),
            ],
            'keywords'         => $this->keyword_profile( $post, $plain ),
            'related_posts'    => Shynh_SEO_Internal_Linking::suggest_links_data( $summary['focus_keyword'], $post->ID ),
            'linked_by'        => Shynh_SEO_Internal_Linking::get_linked_posts_data( $post->ID ),
            'knowledge'        => $this->resolve_knowledge_for_post( $post->ID ),
        ] );

        return $detail;
    }

    private function term_summary( $term ) {
        return [
            'id'            => (int) $term->term_id,
            'taxonomy'      => $term->taxonomy,
            'name'          => $term->name,
            'slug'          => $term->slug,
            'url'           => get_term_link( $term ),
            'count'         => (int) $term->count,
            'parent'        => (int) $term->parent,
            'description'   => $term->description,
            'focus_keyword' => (string) get_term_meta( $term->term_id, '_shynh_seo_focus_keyword', true ),
            'meta_title'    => (string) get_term_meta( $term->term_id, '_shynh_seo_title', true ),
            'meta_desc'     => (string) get_term_meta( $term->term_id, '_shynh_seo_meta_desc', true ),
            'faq'           => $this->decode_faq( get_term_meta( $term->term_id, '_shynh_seo_schema_faq', true ) ),
        ];
    }

    private function term_detail( $term ) {
        $summary = $this->term_summary( $term );

        $children = get_terms( [
            'taxonomy'   => $term->taxonomy,
            'parent'     => $term->term_id,
            'hide_empty' => false,
            'number'     => 50,
        ] );
        $child_list = [];
        if ( ! is_wp_error( $children ) ) {
            foreach ( $children as $c ) {
                $child_list[] = [ 'id' => (int) $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => (int) $c->count ];
            }
        }

        $top_posts = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'tax_query'      => [ [ 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ] ],
        ] );
        $post_list = [];
        foreach ( $top_posts as $p ) {
            $post_list[] = [ 'id' => (int) $p->ID, 'title' => get_the_title( $p ), 'url' => get_permalink( $p ) ];
        }

        return array_merge( $summary, [
            'schema'    => [
                'type'         => (string) get_term_meta( $term->term_id, '_shynh_seo_schema_type', true ),
                'geo_aeo_mode' => (string) get_term_meta( $term->term_id, '_shynh_seo_geo_aeo_mode', true ),
            ],
            'children'  => $child_list,
            'top_posts' => $post_list,
            'knowledge' => Shynh_SEO_Knowledge_Vault::resolve_inherited_knowledge( $term->term_id, $term->taxonomy ),
        ] );
    }

    /* ---------------------------------------------------------------------
     * Analysis wrappers
     * ------------------------------------------------------------------- */

    private function run_post_analysis( $post ) {
        $analysis_obj = Shynh_SEO_Analysis::get_instance();
        $keyword      = get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true );
        $title        = get_post_meta( $post->ID, '_shynh_seo_title', true ) ?: get_the_title( $post );
        $desc         = get_post_meta( $post->ID, '_shynh_seo_meta_desc', true );

        $result = $analysis_obj->analyze_data(
            $post->ID, $keyword, $title, $desc, $post->post_content, $post->post_name,
            'post', $post->ID, $post->post_type
        );

        if ( method_exists( $analysis_obj, 'persist_post_analysis' ) ) {
            $analysis_obj->persist_post_analysis( $post->ID, $result );
        }

        return $this->format_analysis( $result );
    }

    private function run_term_analysis( $term ) {
        $analysis_obj = Shynh_SEO_Analysis::get_instance();
        $keyword      = get_term_meta( $term->term_id, '_shynh_seo_focus_keyword', true );
        $title        = get_term_meta( $term->term_id, '_shynh_seo_title', true ) ?: $term->name;
        $desc         = get_term_meta( $term->term_id, '_shynh_seo_meta_desc', true );

        $result = $analysis_obj->analyze_data(
            0, $keyword, $title, $desc, $term->description, $term->slug,
            'term', $term->term_id, $term->taxonomy
        );

        if ( method_exists( $analysis_obj, 'persist_term_analysis' ) ) {
            $analysis_obj->persist_term_analysis( $term->term_id, $result );
        }

        return $this->format_analysis( $result );
    }

    private function format_analysis( $result ) {
        return [
            'seo_score'       => isset( $result['score'] ) ? (int) $result['score'] : null,
            'geo_aeo_score'   => isset( $result['geo_aeo']['score'] ) ? (int) $result['geo_aeo']['score'] : null,
            'ai_search_score' => isset( $result['overall_ai_search_score'] ) ? (int) $result['overall_ai_search_score'] : null,
            'results'         => isset( $result['results'] ) ? $result['results'] : [],
            'geo_aeo'         => isset( $result['geo_aeo'] ) ? $result['geo_aeo'] : null,
        ];
    }

    /* ---------------------------------------------------------------------
     * Small helpers
     * ------------------------------------------------------------------- */

    private function not_found() {
        return new WP_Error( 'shynh_seo_not_found', __( 'Không tìm thấy đối tượng.', 'shynh-seo' ), [ 'status' => 404 ] );
    }

    private function meta_int( $post_id, $key ) {
        $v = get_post_meta( $post_id, $key, true );
        return ( '' === $v || null === $v ) ? null : (int) $v;
    }

    private function plain_text( $post ) {
        $content = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
        return $content;
    }

    private function word_count( $plain ) {
        $plain = trim( (string) $plain );
        if ( '' === $plain ) {
            return 0;
        }
        $parts = preg_split( '/\s+/u', $plain );
        return is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
    }

    private function primary_category_name( $post_id ) {
        if ( method_exists( 'Shynh_SEO_Core', 'get_primary_term' ) ) {
            $term = Shynh_SEO_Core::get_primary_term( $post_id );
            if ( $term && ! is_wp_error( $term ) ) {
                return $term->name;
            }
        }
        $taxes = get_object_taxonomies( get_post_type( $post_id ) );
        foreach ( $taxes as $tx ) {
            $terms = get_the_terms( $post_id, $tx );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                return $terms[0]->name;
            }
        }
        return null;
    }

    private function decode_faq( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return [];
        }
        $decoded = json_decode( html_entity_decode( $raw ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }
        $out = [];
        foreach ( $decoded as $item ) {
            if ( is_array( $item ) && ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
                $out[] = [
                    'question' => (string) $item['question'],
                    'answer'   => (string) $item['answer'],
                ];
            }
        }
        return $out;
    }

    private function sanitize_faq_input( $faq ) {
        if ( ! is_array( $faq ) ) {
            return [];
        }
        $out = [];
        foreach ( $faq as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $q = isset( $item['question'] ) ? sanitize_text_field( wp_unslash( $item['question'] ) ) : '';
            $a = isset( $item['answer'] ) ? wp_kses_post( wp_unslash( $item['answer'] ) ) : '';
            if ( '' !== $q && '' !== $a ) {
                $out[] = [ 'question' => $q, 'answer' => $a ];
            }
        }
        return $out;
    }

    private function keyword_profile( $post, $plain ) {
        $focus = (string) get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true );

        // Lightweight top-word frequency (length >= 4, exclude short stopwords).
        $stop = [ 'này','những','được','trong','không','cho','các','một','của','và','là','với','có','khi','để','như','từ','theo','đến','sau','trên','dưới','hoặc','nhưng','cũng','đã','sẽ','the','and','for','with','that','this','from','are','was','will' ];
        $words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $plain, 'UTF-8' ) );
        $freq  = [];
        $total = 0;
        foreach ( (array) $words as $w ) {
            if ( mb_strlen( $w, 'UTF-8' ) < 4 || in_array( $w, $stop, true ) ) {
                continue;
            }
            $freq[ $w ] = ( $freq[ $w ] ?? 0 ) + 1;
            $total++;
        }
        arsort( $freq );
        $top = [];
        foreach ( array_slice( $freq, 0, 15, true ) as $w => $c ) {
            $top[] = [ 'term' => $w, 'count' => $c, 'density' => $total ? round( $c / $total * 100, 2 ) : 0 ];
        }

        $focus_count = 0;
        if ( '' !== $focus ) {
            $focus_count = substr_count( mb_strtolower( $plain, 'UTF-8' ), mb_strtolower( $focus, 'UTF-8' ) );
        }

        return [
            'focus_keyword'    => $focus,
            'focus_occurrences'=> $focus_count,
            'top_keywords'     => $top,
        ];
    }

    private function resolve_knowledge_for_post( $post_id ) {
        if ( ! class_exists( 'Shynh_SEO_Knowledge_Vault' ) ) {
            return [];
        }
        $taxes = get_object_taxonomies( get_post_type( $post_id ) );
        $tax   = in_array( 'category', $taxes, true ) ? 'category' : ( $taxes[0] ?? 'category' );
        return Shynh_SEO_Knowledge_Vault::resolve_inherited_knowledge( 0, $tax, $post_id );
    }

    /**
     * Slice an in-memory list by page/per_page and attach total headers.
     */
    private function paginate( array $items, $request, $max_per_page = 100 ) {
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = min( $max_per_page, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 50 ) );
        $total    = count( $items );
        $slice    = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

        $response = rest_ensure_response( array_values( $slice ) );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );
        return $response;
    }

    /**
     * Prime post + meta caches for a set of IDs to eliminate N+1 queries.
     */
    private function prime_caches( $ids ) {
        $ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
        if ( empty( $ids ) ) {
            return;
        }
        _prime_post_caches( $ids, false, true );
        update_meta_cache( 'post', $ids );
    }

    private function count_score_band( $post_types, $min, $max ) {
        global $wpdb;
        $ph = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $params = array_merge( $post_types, [ (int) $min, (int) $max ] );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_shynh_seo_score'
             WHERE p.post_status='publish' AND p.post_type IN ($ph)
               AND CAST(pm.meta_value AS SIGNED) >= %d AND CAST(pm.meta_value AS SIGNED) <= %d",
            $params
        ) );
    }

    private function band_meta_query( &$meta_query, $meta_key, $min, $max ) {
        $min = ( null === $min || '' === $min ) ? null : (int) $min;
        $max = ( null === $max || '' === $max ) ? null : (int) $max;
        if ( null === $min && null === $max ) {
            return;
        }
        if ( null !== $min ) {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $min, 'type' => 'NUMERIC', 'compare' => '>=' ];
        }
        if ( null !== $max ) {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $max, 'type' => 'NUMERIC', 'compare' => '<=' ];
        }
    }

    private function count_missing_meta( $post_types, $meta_key ) {
        global $wpdb;
        $ph = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_status='publish' AND p.post_type IN ($ph)
               AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm
                 WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value != ''
               )",
            array_merge( $post_types, [ $meta_key ] )
        ) );
    }

    /**
     * Minimal HTML → Markdown conversion for AI consumption (headings, paragraphs,
     * lists, links, emphasis). Falls back to stripped text on failure.
     */
    private function get_post_language( $post_id ) {
        if ( class_exists( 'Shynh_SEO_Cannibalization' ) ) {
            return Shynh_SEO_Cannibalization::get_object_language( $post_id, 'post' );
        }

        return function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id ) : '';
    }

    private function count_language_group_posts( $groups ) {
        $count = 0;
        foreach ( (array) $groups as $ids ) {
            $count += count( (array) $ids );
        }

        return $count;
    }

    private function get_language_scoped_cannibalization_rows( $limit = 0 ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT pm.post_id, LOWER(TRIM(pm.meta_value)) AS kw
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_shynh_seo_focus_keyword'
               AND pm.meta_value != ''
               AND p.post_status = 'publish'"
        );

        $groups = [];
        foreach ( $rows as $row ) {
            $kw = trim( (string) $row->kw );
            if ( '' === $kw ) {
                continue;
            }

            $language = $this->get_post_language( (int) $row->post_id ) ?: '_unknown';
            $key      = $kw . "\0" . $language;

            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = [
                    'kw'       => $kw,
                    'language' => '_unknown' === $language ? '' : $language,
                    'c'        => 0,
                ];
            }

            $groups[ $key ]['c']++;
        }

        $out = array_values( array_filter( $groups, function ( $group ) {
            return (int) $group['c'] > 1;
        } ) );

        usort( $out, function ( $a, $b ) {
            return (int) $b['c'] <=> (int) $a['c'];
        } );

        return $limit > 0 ? array_slice( $out, 0, $limit ) : $out;
    }

    private function html_to_markdown( $html ) {
        $html = trim( (string) $html );
        if ( '' === $html ) {
            return '';
        }

        if ( ! class_exists( 'DOMDocument' ) ) {
            return trim( wp_strip_all_tags( $html ) );
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName( 'div' )->item( 0 );
        if ( ! $body ) {
            return trim( wp_strip_all_tags( $html ) );
        }

        $md = $this->node_to_markdown( $body );
        $md = preg_replace( "/\n{3,}/", "\n\n", $md );
        return trim( $md );
    }

    private function node_to_markdown( $node ) {
        $out = '';
        foreach ( $node->childNodes as $child ) {
            if ( XML_TEXT_NODE === $child->nodeType ) {
                $out .= preg_replace( '/\s+/', ' ', $child->nodeValue );
                continue;
            }
            if ( XML_ELEMENT_NODE !== $child->nodeType ) {
                continue;
            }
            $tag = strtolower( $child->nodeName );
            switch ( $tag ) {
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    $level = (int) substr( $tag, 1 );
                    $out  .= "\n\n" . str_repeat( '#', $level ) . ' ' . trim( $child->textContent ) . "\n\n";
                    break;
                case 'p':
                    $out .= "\n\n" . trim( $this->node_to_markdown( $child ) ) . "\n\n";
                    break;
                case 'br':
                    $out .= "\n";
                    break;
                case 'strong': case 'b':
                    $out .= '**' . trim( $child->textContent ) . '**';
                    break;
                case 'em': case 'i':
                    $out .= '*' . trim( $child->textContent ) . '*';
                    break;
                case 'a':
                    $href = $child->getAttribute( 'href' );
                    $out .= '[' . trim( $child->textContent ) . '](' . $href . ')';
                    break;
                case 'ul': case 'ol':
                    $i = 1;
                    foreach ( $child->childNodes as $li ) {
                        if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
                            $bullet = ( 'ol' === $tag ) ? ( $i++ . '.' ) : '-';
                            $out   .= "\n" . $bullet . ' ' . trim( $this->node_to_markdown( $li ) );
                        }
                    }
                    $out .= "\n";
                    break;
                case 'img':
                    $alt  = $child->getAttribute( 'alt' );
                    $src  = $child->getAttribute( 'src' );
                    $out .= "\n![" . $alt . '](' . $src . ")\n";
                    break;
                default:
                    $out .= $this->node_to_markdown( $child );
                    break;
            }
        }
        return $out;
    }
}
