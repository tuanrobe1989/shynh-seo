<?php
/**
 * Admin Columns Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Admin_Columns {

    public static function init() {
        $settings = Shynh_SEO_Core::get_settings();
        
        // Mặc định là bật nếu không tồn tại setting
        $show = isset( $settings['show_seo_columns'] ) ? $settings['show_seo_columns'] : true;
        
        if ( ! $show ) {
            return;
        }

        // Hook cho mọi public post type
        add_action( 'admin_init', [ __CLASS__, 'setup_columns' ] );
        add_action( 'admin_head', [ __CLASS__, 'admin_styles' ] );
    }

    public static function setup_columns() {
        $post_types = Shynh_SEO_Core::get_seo_post_types();
        foreach ( $post_types as $post_type ) {
            add_filter( "manage_{$post_type}_posts_columns", [ __CLASS__, 'add_columns' ] );
            add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'render_columns' ], 10, 2 );
        }

        $taxonomies = array_diff( get_taxonomies( [ 'public' => true ], 'names' ), [ 'post_format' ] );
        foreach ( $taxonomies as $taxonomy ) {
            add_filter( "manage_edit-{$taxonomy}_columns", [ __CLASS__, 'add_term_columns' ] );
            add_filter( "manage_{$taxonomy}_custom_column", [ __CLASS__, 'render_term_columns' ], 10, 3 );
        }

        add_filter( 'term_description', [ __CLASS__, 'truncate_admin_term_description' ], 10, 2 );
    }

    public static function truncate_admin_term_description( $description, $term_id ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( $screen && 'edit-tags' === $screen->base ) {
                $plain = wp_strip_all_tags( $description );
                if ( ! empty( $plain ) ) {
                    $words = preg_split( '/\s+/u', trim( $plain ) );
                    if ( count( $words ) > 20 ) {
                        return esc_html( implode( ' ', array_slice( $words, 0, 20 ) ) . '...' );
                    }
                    return esc_html( $plain );
                }
            }
        }
        return $description;
    }

    public static function add_columns( $columns ) {
        // Chèn vào trước cột Date nếu có
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            if ( $key === 'date' ) {
                $new_columns['shynh_seo_score']   = __( 'Điểm SEO', 'shynh-seo' );
                $new_columns['shynh_seo_keyword'] = __( 'Từ khóa chính', 'shynh-seo' );
            }
            $new_columns[$key] = $title;
        }
        
        // Fallback nếu không có cột Date
        if ( ! isset( $new_columns['shynh_seo_score'] ) ) {
            $new_columns['shynh_seo_score']   = __( 'Điểm SEO', 'shynh-seo' );
            $new_columns['shynh_seo_keyword'] = __( 'Từ khóa chính', 'shynh-seo' );
        }

        return $new_columns;
    }

    public static function add_term_columns( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            if ( $key === 'posts' || $key === 'slug' ) {
                $new_columns['shynh_seo_score']   = __( 'Điểm SEO', 'shynh-seo' );
                $new_columns['shynh_seo_keyword'] = __( 'Từ khóa chính', 'shynh-seo' );
            }
            $new_columns[$key] = $title;
        }

        if ( ! isset( $new_columns['shynh_seo_score'] ) ) {
            $new_columns['shynh_seo_score']   = __( 'Điểm SEO', 'shynh-seo' );
            $new_columns['shynh_seo_keyword'] = __( 'Từ khóa chính', 'shynh-seo' );
        }

        return $new_columns;
    }

    public static function render_columns( $column_name, $post_id ) {
        if ( $column_name === 'shynh_seo_score' ) {
            $score = get_post_meta( $post_id, '_shynh_seo_score', true );
            
            if ( $score === '' ) {
                echo '<div class="shynh-seo-score-badge shynh-seo-na" title="Chưa phân tích"></div>';
                return;
            }

            $score = (int) $score;
            $class = 'shynh-seo-red';
            if ( $score >= 70 ) {
                $class = 'shynh-seo-green';
            } elseif ( $score >= 40 ) {
                $class = 'shynh-seo-orange';
            }

            echo '<div class="shynh-seo-score-badge ' . esc_attr( $class ) . '" title="Điểm: ' . esc_attr( $score ) . '/100">' . esc_html( $score ) . '</div>';
        }

        if ( $column_name === 'shynh_seo_keyword' ) {
            $keyword = get_post_meta( $post_id, '_shynh_seo_focus_keyword', true );
            if ( $keyword ) {
                echo '<strong>' . esc_html( $keyword ) . '</strong>';
            } else {
                echo '<span style="color: #94a3b8;">—</span>';
            }
        }
    }

    public static function render_term_columns( $content, $column_name, $term_id ) {
        if ( $column_name === 'shynh_seo_score' ) {
            $score = get_term_meta( $term_id, '_shynh_seo_score', true );
            if ( $score === '' ) {
                return '<div class="shynh-seo-score-badge shynh-seo-na" title="Chưa phân tích"></div>';
            }
            $score = (int) $score;
            $class = 'shynh-seo-red';
            if ( $score >= 70 ) {
                $class = 'shynh-seo-green';
            } elseif ( $score >= 40 ) {
                $class = 'shynh-seo-orange';
            }
            return '<div class="shynh-seo-score-badge ' . esc_attr( $class ) . '" title="Điểm: ' . esc_attr( $score ) . '/100">' . esc_html( $score ) . '</div>';
        }

        if ( $column_name === 'shynh_seo_keyword' ) {
            $keyword = get_term_meta( $term_id, '_shynh_seo_focus_keyword', true );
            if ( $keyword ) {
                return '<strong>' . esc_html( $keyword ) . '</strong>';
            }
            return '<span style="color: #94a3b8;">—</span>';
        }

        return $content;
    }

    public static function admin_styles() {
        ?>
        <style>
            th.column-shynh_seo_score { width: 85px; text-align: center; }
            th.column-shynh_seo_keyword { width: 15%; }
            td.column-shynh_seo_score { text-align: center; vertical-align: middle; }
            
            .shynh-seo-score-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                font-weight: bold;
                font-size: 13px;
                color: #fff;
                margin: 0 auto;
            }
            .shynh-seo-score-badge.shynh-seo-green { background-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2); }
            .shynh-seo-score-badge.shynh-seo-orange { background-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
            .shynh-seo-score-badge.shynh-seo-red { background-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
            .shynh-seo-score-badge.shynh-seo-na { 
                background-color: transparent; 
                border: 3px solid #cbd5e1; 
                color: transparent; 
                width: 16px; 
                height: 16px; 
            }
        </style>
        <?php
    }
}
