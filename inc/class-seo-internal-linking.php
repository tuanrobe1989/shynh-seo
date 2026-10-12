<?php
/**
 * Internal Linking suggestion system.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Internal_Linking {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_shynh_seo_suggest_links', [ $this, 'ajax_suggest_links' ] );
        add_action( 'wp_ajax_shynh_seo_get_linked_posts', [ $this, 'ajax_get_linked_posts' ] );
        add_action( 'wp_ajax_shynh_seo_search_related_posts', [ $this, 'ajax_search_related_posts' ] );
        add_filter( 'the_content', [ $this, 'append_internal_related_posts' ], 25 );
        add_action( 'wp_head', [ $this, 'output_related_posts_styles' ], 30 );
    }

    public function ajax_suggest_links() {
        check_ajax_referer( 'shynh_seo_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        wp_send_json_success( self::suggest_links_data( $keyword, $post_id ) );
    }

    public function ajax_get_linked_posts() {
        check_ajax_referer( 'shynh_seo_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        wp_send_json_success( self::get_linked_posts_data( $post_id ) );
    }

    public function ajax_search_related_posts() {
        check_ajax_referer( 'shynh_seo_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
        $taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $term_id   = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

        wp_send_json_success( self::search_related_posts_data( $post_id, $search, $taxonomy, $term_id, $post_type ) );
    }

    /**
     * Suggest posts that share a focus keyword and could be linked to.
     * Reusable by AJAX and REST.
     *
     * @param string $keyword
     * @param int    $post_id  Current post to exclude.
     * @return array<int,array>
     */
    public static function suggest_links_data( $keyword, $post_id = 0 ) {
        $keyword    = sanitize_text_field( $keyword );
        $post_id    = absint( $post_id );
        $post_types = Shynh_SEO_Core::get_related_post_types();

        if ( empty( $keyword ) || empty( $post_types ) ) {
            return [];
        }

        global $wpdb;

        $like_keyword           = '%' . $wpdb->esc_like( $keyword ) . '%';
        $post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type, pm.meta_value as focus_keyword
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND p.ID != %d
               AND p.post_type IN ({$post_type_placeholders})
               AND pm.meta_key = '_shynh_seo_focus_keyword'
               AND pm.meta_value LIKE %s
             ORDER BY p.post_date DESC
             LIMIT 20",
            array_merge( [ $post_id ], $post_types, [ $like_keyword ] )
        );

        $results = $wpdb->get_results( $query );
        $data    = [];

        if ( $results ) {
            foreach ( $results as $row ) {
                if ( ! self::is_same_language_post( $post_id, (int) $row->ID ) ) {
                    continue;
                }

                $data[] = [
                    'id'        => (int) $row->ID,
                    'title'     => $row->post_title,
                    'url'       => get_permalink( $row->ID ),
                    'keyword'   => $row->focus_keyword,
                    'post_type' => $row->post_type,
                    'language'  => self::get_post_language( (int) $row->ID ),
                ];
            }
        }

        return $data;
    }

    public static function search_related_posts_data( $post_id = 0, $search = '', $taxonomy = '', $term_id = 0, $post_type = '' ) {
        $post_id            = absint( $post_id );
        $search             = sanitize_text_field( $search );
        $taxonomy           = sanitize_key( $taxonomy );
        $term_id            = absint( $term_id );
        $post_type          = sanitize_key( $post_type );
        $allowed_post_types = Shynh_SEO_Core::get_related_post_types();
        $post_types         = $allowed_post_types;

        if ( $post_type && in_array( $post_type, $allowed_post_types, true ) ) {
            $post_types = [ $post_type ];
        }

        if ( empty( $post_types ) ) {
            return [];
        }

        $args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'post__not_in'   => $post_id ? [ $post_id ] : [],
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        $tax_query = self::build_internal_tax_query( $taxonomy, $term_id, $post_types );
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $posts = get_posts( $args );
        $data  = [];

        foreach ( $posts as $post ) {
            if ( ! self::is_same_language_post( $post_id, $post->ID ) ) {
                continue;
            }

            $relation = self::get_relation_score( $post_id, $post->ID );

            $data[] = [
                'id'        => (int) $post->ID,
                'title'     => get_the_title( $post ),
                'url'       => get_permalink( $post ),
                'post_type' => get_post_type( $post ),
                'language'  => self::get_post_language( $post->ID ),
                'color'     => $relation['color'],
                'score'     => $relation['score'],
                'date'      => get_the_date( 'd/m/Y', $post->ID ),
            ];
        }

        return $data;
    }

    public static function get_related_context( $post_id ) {
        $post_id = absint( $post_id );
        $post    = $post_id ? get_post( $post_id ) : null;
        $post_types = Shynh_SEO_Core::get_related_post_types();
        $taxonomies = [];
        $post_type_options = [];

        foreach ( $post_types as $post_type ) {
            $post_type_object = get_post_type_object( $post_type );
            if ( ! $post_type_object ) {
                continue;
            }

            $post_type_options[] = [
                'slug'  => $post_type,
                'label' => $post_type_object->labels->singular_name,
            ];
        }

        $taxonomy_names = [];
        foreach ( $post_types as $post_type ) {
            $taxonomy_names = array_merge( $taxonomy_names, get_object_taxonomies( $post_type, 'names' ) );
        }
        $taxonomy_names = array_values( array_unique( $taxonomy_names ) );

        foreach ( $taxonomy_names as $taxonomy_name ) {
            $taxonomy = get_taxonomy( $taxonomy_name );
            if ( empty( $taxonomy->public ) ) {
                continue;
            }

            $taxonomy_post_types = isset( $taxonomy->object_type ) ? (array) $taxonomy->object_type : [];
            if ( empty( array_intersect( $taxonomy_post_types, $post_types ) ) ) {
                continue;
            }

            $terms = get_terms( [
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
                'number'     => 200,
            ] );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $taxonomies[] = [
                    'taxonomy'   => $taxonomy->name,
                    'term_id'    => (int) $term->term_id,
                    'label'      => $taxonomy->labels->singular_name . ': ' . $term->name,
                    'post_types' => array_values( array_intersect( $taxonomy_post_types, $post_types ) ),
                ];
            }
        }

        return [
            'postType'   => $post ? $post->post_type : '',
            'postTypes'  => $post_type_options,
            'taxonomies' => $taxonomies,
        ];
    }

    public static function get_internal_link_items( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return [];
        }

        $items = get_post_meta( $post_id, '_shynh_seo_internal_link_posts', true );
        if ( is_string( $items ) ) {
            $decoded = json_decode( html_entity_decode( $items ), true );
            $items   = is_array( $decoded ) ? $decoded : [];
        }

        if ( empty( $items ) || ! is_array( $items ) ) {
            $legacy_items = get_post_meta( $post_id, '_shynh_seo_manual_related_posts', true );
            if ( is_string( $legacy_items ) ) {
                $decoded      = json_decode( html_entity_decode( $legacy_items ), true );
                $legacy_items = is_array( $decoded ) ? $decoded : [];
            }

            if ( is_array( $legacy_items ) && ! empty( $legacy_items ) ) {
                $items = $legacy_items;
            }
        }

        $clean = [];
        foreach ( (array) $items as $item ) {
            $target_id = absint( $item['post_id'] ?? $item['id'] ?? 0 );
            if ( ! $target_id || $target_id === $post_id || 'publish' !== get_post_status( $target_id ) ) {
                continue;
            }
            if ( ! self::is_same_language_post( $post_id, $target_id ) ) {
                continue;
            }

            $custom_title = sanitize_text_field( $item['title'] ?? '' );
            $url          = esc_url_raw( $item['url'] ?? '' );
            $permalink    = get_permalink( $target_id );

            $clean[] = [
                'post_id'   => $target_id,
                'title'     => $custom_title ?: get_the_title( $target_id ),
                'url'       => $url ?: $permalink,
                'post_type' => get_post_type( $target_id ),
                'language'  => self::get_post_language( $target_id ),
            ];
        }

        return array_slice( $clean, 0, 8 );
    }

    public static function get_manual_related_items( $post_id ) {
        return self::get_internal_link_items( $post_id );
    }

    public static function sanitize_internal_link_items( $raw, $post_id = 0 ) {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( wp_unslash( $raw ), true );
            $raw     = is_array( $decoded ) ? $decoded : [];
        }

        $items = [];
        foreach ( (array) $raw as $item ) {
            $target_id = absint( $item['post_id'] ?? $item['id'] ?? 0 );
            if ( ! $target_id || $target_id === absint( $post_id ) || 'publish' !== get_post_status( $target_id ) ) {
                continue;
            }
            if ( $post_id && ! self::is_same_language_post( $post_id, $target_id ) ) {
                continue;
            }

            $items[] = [
                'post_id' => $target_id,
                'title'   => sanitize_text_field( $item['title'] ?? '' ),
                'url'     => esc_url_raw( $item['url'] ?? '' ),
            ];
        }

        return array_slice( $items, 0, 8 );
    }

    public static function sanitize_manual_related_items( $raw, $post_id = 0 ) {
        return self::sanitize_internal_link_items( $raw, $post_id );
    }

    private static function build_current_tax_query( $post_id, $taxonomy = '', $term_id = 0 ) {
        $post_id  = absint( $post_id );
        $taxonomy = sanitize_key( $taxonomy );
        $term_id  = absint( $term_id );

        if ( ! $post_id ) {
            return [];
        }

        $queries = [];

        if ( $taxonomy && $term_id ) {
            $taxonomy_object = get_taxonomy( $taxonomy );
            $post_type       = get_post_type( $post_id );
            if ( $taxonomy_object && in_array( $post_type, (array) $taxonomy_object->object_type, true ) ) {
                $queries[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [ $term_id ],
                ];
            }
        } else {
            foreach ( get_object_taxonomies( get_post_type( $post_id ), 'objects' ) as $taxonomy_object ) {
                if ( empty( $taxonomy_object->public ) ) {
                    continue;
                }

                $term_ids = wp_get_post_terms( $post_id, $taxonomy_object->name, [ 'fields' => 'ids' ] );
                if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
                    continue;
                }

                $queries[] = [
                    'taxonomy' => $taxonomy_object->name,
                    'field'    => 'term_id',
                    'terms'    => array_map( 'absint', $term_ids ),
                ];
            }
        }

        if ( empty( $queries ) ) {
            return [];
        }

        if ( count( $queries ) <= 1 ) {
            return $queries;
        }

        return array_merge( [ 'relation' => 'OR' ], $queries );
    }

    private static function build_internal_tax_query( $taxonomy = '', $term_id = 0, $post_types = [] ) {
        $taxonomy   = sanitize_key( $taxonomy );
        $term_id    = absint( $term_id );
        $post_types = array_filter( array_map( 'sanitize_key', (array) $post_types ) );

        if ( ! $taxonomy || ! $term_id ) {
            return [];
        }

        $taxonomy_object = get_taxonomy( $taxonomy );
        if ( ! $taxonomy_object || empty( $taxonomy_object->public ) ) {
            return [];
        }

        $taxonomy_post_types = isset( $taxonomy_object->object_type ) ? (array) $taxonomy_object->object_type : [];
        if ( ! empty( $post_types ) && empty( array_intersect( $taxonomy_post_types, $post_types ) ) ) {
            return [];
        }

        return [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => [ $term_id ],
            ],
        ];
    }

    private static function get_relation_score( $source_id, $target_id ) {
        $source_keyword = mb_strtolower( trim( (string) get_post_meta( $source_id, '_shynh_seo_focus_keyword', true ) ), 'UTF-8' );
        $target_keyword = mb_strtolower( trim( (string) get_post_meta( $target_id, '_shynh_seo_focus_keyword', true ) ), 'UTF-8' );
        $target_title   = mb_strtolower( get_the_title( $target_id ), 'UTF-8' );

        if ( '' !== $source_keyword && ( $source_keyword === $target_keyword || false !== mb_strpos( $target_title, $source_keyword, 0, 'UTF-8' ) ) ) {
            return [ 'color' => 'green', 'score' => 3 ];
        }

        foreach ( get_object_taxonomies( get_post_type( $source_id ), 'names' ) as $taxonomy ) {
            $source_terms = wp_get_post_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
            $target_terms = wp_get_post_terms( $target_id, $taxonomy, [ 'fields' => 'ids' ] );
            if ( is_wp_error( $source_terms ) || is_wp_error( $target_terms ) ) {
                continue;
            }
            if ( array_intersect( array_map( 'absint', $source_terms ), array_map( 'absint', $target_terms ) ) ) {
                return [ 'color' => 'orange', 'score' => 2 ];
            }
        }

        return [ 'color' => 'red', 'score' => 1 ];
    }

    public function append_internal_related_posts( $content ) {
        if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id || ! in_array( get_post_type( $post_id ), Shynh_SEO_Core::get_related_post_types(), true ) ) {
            return $content;
        }

        if ( false !== strpos( $content, 'shynh-seo-manual-related' ) || false !== strpos( $content, 'kp-related-posts' ) ) {
            return $content;
        }

        $items = self::get_internal_link_items( $post_id );
        if ( empty( $items ) ) {
            return $content;
        }

        return $content . $this->render_internal_related_posts( $content, $items );
    }

    private function render_internal_related_posts( $content, $items ) {
        $heading_tag = $this->get_related_heading_tag( $content );
        $label       = $this->get_related_heading_label();

        $html  = '<section class="shynh-seo-manual-related" aria-label="' . esc_attr( $label ) . '">';
        $html .= '<div class="shynh-seo-manual-related__divider" aria-hidden="true"></div>';
        $html .= '<' . tag_escape( $heading_tag ) . ' class="shynh-seo-manual-related__title">' . esc_html( $label ) . '</' . tag_escape( $heading_tag ) . '>';
        $html .= '<ul class="shynh-seo-manual-related__list">';

        foreach ( $items as $item ) {
            $html .= '<li class="shynh-seo-manual-related__item">';
            $html .= '<a class="shynh-seo-manual-related__link" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul></section>';

        return $html;
    }

    private function get_related_heading_tag( $content ) {
        if ( preg_match_all( '/<h([2-5])(?:\s[^>]*)?>.*?<\/h\1>/is', (string) $content, $matches ) && ! empty( $matches[1] ) ) {
            $last_level = (int) end( $matches[1] );
            return 'h' . min( 6, $last_level + 1 );
        }

        return 'h3';
    }

    private function get_related_heading_label() {
        $lang = function_exists( 'pll_current_language' ) ? strtolower( str_replace( '_', '-', (string) pll_current_language() ) ) : '';
        if ( 'en' === $lang || 0 === strpos( $lang, 'en-' ) ) {
            return 'Related Articles';
        }

        return 'Nội dung liên quan';
    }

    public function output_related_posts_styles() {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id || empty( self::get_internal_link_items( $post_id ) ) ) {
            return;
        }
        ?>
        <style id="shynh-seo-manual-related-css">
            .shynh-seo-manual-related{margin-top:clamp(28px,4vw,48px);margin-bottom:clamp(18px,3vw,32px)}
            .shynh-seo-manual-related__divider{border-top:1px dashed rgba(15,23,42,.24);opacity:.72;margin-bottom:18px}
            .shynh-seo-manual-related__title{font-size:clamp(18px,2vw,24px);line-height:1.25;margin:0 0 14px;color:inherit}
            .shynh-seo-manual-related__list{list-style:none;margin:0;padding:0;display:grid;gap:10px}
            .shynh-seo-manual-related__item{margin:0;padding:0}
            .shynh-seo-manual-related__link{display:inline-flex;align-items:baseline;gap:8px;text-decoration:none;font-weight:600}
            .shynh-seo-manual-related__link:before{content:"";width:18px;border-top:1px solid currentColor;opacity:.45;transform:translateY(-.25em)}
            .shynh-seo-manual-related__link:hover{text-decoration:underline;text-underline-offset:4px}
        </style>
        <?php
    }

    private static function get_post_language( $post_id ) {
        return function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id ) : '';
    }

    private static function is_same_language_post( $source_id, $target_id ) {
        $source_id = absint( $source_id );
        $target_id = absint( $target_id );

        if ( ! $source_id || ! $target_id ) {
            return true;
        }

        $source_lang = self::get_post_language( $source_id );
        $target_lang = self::get_post_language( $target_id );

        return '' === $source_lang || '' === $target_lang || $source_lang === $target_lang;
    }

    /**
     * Get posts that currently link to the given post (incoming internal links).
     * Reusable by AJAX and REST.
     *
     * @param int $post_id
     * @return array<int,array>
     */
    public static function get_linked_posts_data( $post_id ) {
        $post_id    = absint( $post_id );
        $post_types = Shynh_SEO_Core::get_related_post_types();

        if ( ! $post_id || empty( $post_types ) ) {
            return [];
        }

        $permalink = get_permalink( $post_id );
        $keys      = class_exists( 'Shynh_SEO_Content_Extractor' )
            ? Shynh_SEO_Content_Extractor::get_internal_link_index_keys( $permalink )
            : [ rtrim( $permalink, '/' ) ];

        $meta_query = [ 'relation' => 'OR' ];
        foreach ( $keys as $key ) {
            $meta_query[] = [
                'key'     => '_shynh_seo_internal_link_index',
                'value'   => "\n" . $key . "\n",
                'compare' => 'LIKE',
            ];
        }

        $results = [];
        if ( count( $meta_query ) > 1 ) {
            $results = get_posts( [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'post__not_in'   => [ $post_id ],
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => $meta_query,
            ] );
        }

        if ( empty( $results ) ) {
            global $wpdb;
            $like_url               = '%' . $wpdb->esc_like( rtrim( $permalink, '/' ) ) . '%';
            $post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

            $query = $wpdb->prepare(
                "SELECT ID, post_title, post_type
                 FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND ID != %d
                   AND post_type IN ({$post_type_placeholders})
                   AND post_content LIKE %s
                 ORDER BY post_date DESC
                 LIMIT 20",
                array_merge( [ $post_id ], $post_types, [ $like_url ] )
            );

            $results = $wpdb->get_results( $query );
        }

        $data = [];
        if ( $results ) {
            foreach ( $results as $row ) {
                $data[] = [
                    'id'        => (int) $row->ID,
                    'title'     => $row->post_title,
                    'url'       => get_permalink( $row->ID ),
                    'post_type' => $row->post_type,
                ];
            }
        }

        return $data;
    }
}
