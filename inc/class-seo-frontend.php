<?php
/**
 * SEO Frontend Output (Meta tags).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Remove default WP canonical
        remove_action( 'wp_head', 'rel_canonical' );
        remove_action( 'wp_head', 'wp_robots', 1 );
        
        // Output our tags early
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 2 );

        // Search pages should return a valid 200 response even when no posts match.
        add_filter( 'pre_handle_404', [ $this, 'prevent_search_404' ], 10, 2 );
        
        // Filter document title
        add_filter( 'pre_get_document_title', [ $this, 'get_custom_title' ], 15 );
    }

    public static function replace_variables( $string, $post_id = 0, $term_id = 0 ) {
        if ( empty( $string ) ) return $string;

        $settings = Shynh_SEO_Core::get_settings();
        $sep      = $settings['title_separator'];

        $replacements = [
            '%%sitename%%' => get_bloginfo( 'name' ),
            '%%sitedesc%%' => get_bloginfo( 'description' ),
            '%%sep%%'      => $sep,
            '%%term_title%%' => '',
            '%%term_description%%' => '',
        ];

        // Page number
        global $page, $paged;
        $current_page = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
        $replacements['%%page%%'] = $current_page > 1 ? sprintf( __( 'Trang %d', 'shynh-seo' ), $current_page ) : '';

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $replacements['%%title%%']    = get_the_title( $post_id );
                $replacements['%%excerpt%%']  = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
                $replacements['%%date%%']     = get_the_date( '', $post_id );
                $replacements['%%modified%%'] = get_the_modified_date( '', $post_id );
                $replacements['%%author%%']   = get_the_author_meta( 'display_name', $post->post_author );

                // Category
                $cat_name = '';
                if ( class_exists( 'Shynh_SEO_Core' ) ) {
                    $primary_term = Shynh_SEO_Core::get_primary_term( $post_id );
                    if ( $primary_term ) {
                        $cat_name = $primary_term->name;
                    }
                }
                $replacements['%%category%%'] = $cat_name;
            }
        } elseif ( $term_id ) {
            $term = get_term( $term_id );
            if ( $term && ! is_wp_error( $term ) ) {
                $replacements['%%title%%']    = $term->name;
                $replacements['%%term_title%%'] = $term->name;
                $replacements['%%excerpt%%']  = wp_strip_all_tags( $term->description );
                $replacements['%%term_description%%'] = wp_strip_all_tags( $term->description );
                $replacements['%%category%%'] = '';
                $replacements['%%date%%']     = '';
                $replacements['%%modified%%'] = '';
                $replacements['%%author%%']   = '';
            }
        } elseif ( is_archive() ) {
            $replacements['%%title%%'] = self::get_archive_title();
        } else {
            $replacements['%%title%%'] = get_bloginfo( 'name' );
        }

        $string = str_replace( array_keys( $replacements ), array_values( $replacements ), $string );

        // Clean up empty separator usages (e.g. if %%page%% is empty)
        $string = preg_replace( '/\s*' . preg_quote( $sep, '/' ) . '\s*' . preg_quote( $sep, '/' ) . '/', ' ' . $sep . ' ', $string );
        $string = preg_replace( '/\s*' . preg_quote( $sep, '/' ) . '\s*$/', '', $string ); // trim trailing sep
        $string = preg_replace( '/^\s*' . preg_quote( $sep, '/' ) . '\s*/', '', $string ); // trim leading sep
        $string = preg_replace( '/\s+' . preg_quote( $sep, '/' ) . '\s+/', ' ' . $sep . ' ', $string );
        $string = preg_replace( '/\s{2,}/', ' ', $string );

        return trim( $string );
    }

    public function get_custom_title( $title ) {
        if ( is_front_page() ) {
            return get_bloginfo( 'name' );
        }

        if ( is_search() ) {
            $search_query = get_search_query( false );
            $title = $search_query
                ? sprintf( __( 'Kết quả tìm kiếm cho "%s"', 'shynh-seo' ), $search_query )
                : __( 'Kết quả tìm kiếm', 'shynh-seo' );

            return $this->append_page_number_to_text( $title );
        }

        if ( is_404() ) {
            return __( 'Không tìm thấy trang', 'shynh-seo' );
        }

        if ( is_singular() ) {
            $post_id   = get_queried_object_id();
            $seo_title = get_post_meta( $post_id, '_shynh_seo_title', true );
            if ( $seo_title ) {
                return $this->append_page_number_to_text( self::replace_variables( $seo_title, $post_id ) );
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term_id = get_queried_object_id();
            $seo_title = get_term_meta( $term_id, '_shynh_seo_title', true );
            if ( $seo_title ) {
                return $this->append_page_number_to_text( self::replace_variables( $seo_title, 0, $term_id ) );
            }
        }
        
        $settings = Shynh_SEO_Core::get_settings();
        $pattern  = $settings['title_pattern'];
        
        $post_id = is_singular() ? get_queried_object_id() : 0;
        $term_id = ( is_category() || is_tag() || is_tax() ) ? get_queried_object_id() : 0;
        return $this->append_page_number_to_text( self::replace_variables( $pattern, $post_id, $term_id ) );
    }

    private static function get_archive_title() {
        if ( is_post_type_archive() ) {
            return post_type_archive_title( '', false );
        }

        if ( is_category() ) {
            return single_cat_title( '', false );
        }

        if ( is_tag() ) {
            return single_tag_title( '', false );
        }

        if ( is_tax() ) {
            return single_term_title( '', false );
        }

        if ( is_author() ) {
            $author = get_queried_object();
            return $author instanceof WP_User ? $author->display_name : '';
        }

        if ( is_year() ) {
            return get_the_date( _x( 'Y', 'yearly archives date format', 'shynh-seo' ) );
        }

        if ( is_month() ) {
            return get_the_date( _x( 'F Y', 'monthly archives date format', 'shynh-seo' ) );
        }

        if ( is_day() ) {
            return get_the_date( _x( 'F j, Y', 'daily archives date format', 'shynh-seo' ) );
        }

        return get_bloginfo( 'name' );
    }

    public function prevent_search_404( $preempt, $wp_query ) {
        if ( $wp_query && $wp_query->is_search() ) {
            $wp_query->is_404 = false;
            status_header( 200 );

            return true;
        }

        return $preempt;
    }

    private function get_current_page_number() {
        return max( 1, absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );
    }

    private function append_page_number_to_text( $text ) {
        $page_number = $this->get_current_page_number();
        $text        = trim( (string) $text );

        if ( $page_number <= 1 || '' === $text ) {
            return $text;
        }

        $page_label = sprintf( __( 'Trang %d', 'shynh-seo' ), $page_number );

        if ( false !== stripos( $text, $page_label ) ) {
            return $text;
        }

        return $text . ' - ' . $page_label;
    }

    private function get_current_clean_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $parts       = wp_parse_url( $request_uri );
        $path        = isset( $parts['path'] ) ? $parts['path'] : '/';

        return $this->normalize_canonical_url( home_url( '/' . ltrim( $path, '/' ), 'https' ) );
    }

    private function is_paginated_request_path() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $parts       = wp_parse_url( $request_uri );
        $path        = isset( $parts['path'] ) ? $parts['path'] : '/';

        return (bool) preg_match( '#/page/[1-9][0-9]*/?$#i', $path );
    }

    private function normalize_canonical_url( $url, $preserve_query = false, $collapse_pagination = true ) {
        if ( empty( $url ) || is_wp_error( $url ) ) {
            return '';
        }

        $parts      = wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
        $home_parts = wp_parse_url( home_url( '/', 'https' ) );

        if ( ! is_array( $parts ) || ! is_array( $home_parts ) || empty( $home_parts['host'] ) ) {
            return '';
        }

        $home_host = strtolower( $home_parts['host'] );
        $url_host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : $home_host;
        $home_key  = preg_replace( '/^www\./', '', $home_host );
        $url_key   = preg_replace( '/^www\./', '', $url_host );
        $internal  = $home_key === $url_key;
        $host      = $internal ? $home_parts['host'] : $url_host;
        $port      = '';

        if ( $internal && ! empty( $home_parts['port'] ) ) {
            $port = ':' . absint( $home_parts['port'] );
        } elseif ( ! $internal && ! empty( $parts['port'] ) ) {
            $port = ':' . absint( $parts['port'] );
        }

        $path = isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
        $path = '/' . ltrim( $path, '/' );

        if ( $collapse_pagination ) {
            $path = preg_replace( '#/page/[0-9]+/?$#i', '/', $path );
        }

        $query = '';
        if ( $preserve_query && ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query_args );
            unset( $query_args['page'], $query_args['paged'] );

            foreach ( array_keys( $query_args ) as $query_key ) {
                if ( preg_match( '/^(?:utm_.+|gclid|fbclid|msclkid)$/i', (string) $query_key ) ) {
                    unset( $query_args[ $query_key ] );
                }
            }

            if ( $query_args ) {
                $query = '?' . http_build_query( $query_args, '', '&', PHP_QUERY_RFC3986 );
            }
        }

        return esc_url_raw( 'https://' . $host . $port . $path . $query );
    }

    private function is_same_canonical_url( $canonical, $self_url ) {
        if ( empty( $canonical ) || empty( $self_url ) ) {
            return true;
        }

        $canonical = untrailingslashit( strtolower( esc_url_raw( $canonical ) ) );
        $self_url  = untrailingslashit( strtolower( esc_url_raw( $self_url ) ) );

        return $canonical === $self_url;
    }

    private function should_force_noindex( $settings ) {
        if ( ! empty( $settings['sitewide_noindex'] ) ) {
            return true;
        }

        // Tags remain available for editorial classification, but their thin
        // archive pages must never compete with category and article URLs.
        if ( is_tag() ) {
            return true;
        }

        if ( is_404() && ! empty( $settings['not_found_noindex'] ) ) {
            return true;
        }

        if ( is_search() && ! empty( $settings['search_noindex'] ) ) {
            return true;
        }

        if ( is_attachment() && ! empty( $settings['attachment_noindex'] ) ) {
            return true;
        }

        if ( $this->get_current_page_number() > 1 && ! empty( $settings['paginated_noindex'] ) ) {
            return true;
        }

        if ( is_archive() && ! empty( $settings['archive_noindex'] ) ) {
            return true;
        }

        return false;
    }

    private function get_meta_value( $object_type, $object_id, $key ) {
        if ( 'term' === $object_type ) {
            return get_term_meta( $object_id, $key, true );
        }

        if ( 'post' === $object_type ) {
            return get_post_meta( $object_id, $key, true );
        }

        return '';
    }

    private function build_robots_content( $settings, $object_type = '', $object_id = 0, $index = 'index', $follow = 'follow' ) {
        $directives = [];
        $index      = in_array( $index, [ 'index', 'noindex' ], true ) ? $index : 'index';
        $follow     = in_array( $follow, [ 'follow', 'nofollow' ], true ) ? $follow : 'follow';

        if ( $this->should_force_noindex( $settings ) ) {
            $index = 'noindex';
        }

        if ( ! empty( $settings['ai_sync_force_en_noindex'] ) && function_exists( 'pll_current_language' ) && 'en' === pll_current_language() ) {
            $index  = 'noindex';
            $follow = 'nofollow';
        }

        $directives[] = $index;
        $directives[] = $follow;

        $noarchive    = ! empty( $settings['robots_noarchive'] );
        $nosnippet    = ! empty( $settings['robots_nosnippet'] );
        $noimageindex = ! empty( $settings['robots_noimageindex'] );
        $max_snippet  = isset( $settings['robots_max_snippet'] ) ? trim( (string) $settings['robots_max_snippet'] ) : '';
        $max_image    = isset( $settings['robots_max_image_preview'] ) ? sanitize_key( $settings['robots_max_image_preview'] ) : '';
        $max_video    = isset( $settings['robots_max_video_preview'] ) ? trim( (string) $settings['robots_max_video_preview'] ) : '';

        if ( $object_type && $object_id ) {
            $noarchive    = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_noarchive' ) ? true : $noarchive;
            $nosnippet    = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_nosnippet' ) ? true : $nosnippet;
            $noimageindex = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_noimageindex' ) ? true : $noimageindex;

            $meta_max_snippet = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_max_snippet' );
            $meta_max_image   = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_max_image_preview' );
            $meta_max_video   = $this->get_meta_value( $object_type, $object_id, '_shynh_seo_robots_max_video_preview' );

            if ( '' !== $meta_max_snippet ) {
                $max_snippet = (string) intval( $meta_max_snippet );
            }
            if ( '' !== $meta_max_image ) {
                $max_image = sanitize_key( $meta_max_image );
            }
            if ( '' !== $meta_max_video ) {
                $max_video = (string) intval( $meta_max_video );
            }
        }

        if ( $noarchive ) {
            $directives[] = 'noarchive';
        }
        if ( $nosnippet ) {
            $directives[] = 'nosnippet';
        } elseif ( '' !== $max_snippet ) {
            $directives[] = 'max-snippet:' . intval( $max_snippet );
        }
        if ( $noimageindex ) {
            $directives[] = 'noimageindex';
        }
        if ( in_array( $max_image, [ 'none', 'standard', 'large' ], true ) ) {
            $directives[] = 'max-image-preview:' . $max_image;
        }
        if ( '' !== $max_video ) {
            $directives[] = 'max-video-preview:' . intval( $max_video );
        }

        return implode( ', ', array_unique( array_filter( $directives ) ) );
    }

    private function clean_description( $description ) {
        $description = wp_strip_all_tags( (string) $description );
        $description = preg_replace( '/\s+/', ' ', $description );

        return trim( $description );
    }

    private function get_default_social_description( $settings, $post_id = 0, $term_id = 0 ) {
        $description = ! empty( $settings['default_social_desc'] )
            ? self::replace_variables( $settings['default_social_desc'], $post_id, $term_id )
            : '';

        if ( empty( $description ) && ! empty( $settings['default_meta_desc'] ) ) {
            $description = self::replace_variables( $settings['default_meta_desc'], $post_id, $term_id );
        }

        if ( empty( $description ) ) {
            $description = get_bloginfo( 'description' );
        }

        return $this->clean_description( $description );
    }

    private function get_search_description( $settings ) {
        $search_query = get_search_query( false );

        if ( $search_query ) {
            $description = sprintf(
                __( 'Kết quả tìm kiếm cho "%1$s" trên %2$s.', 'shynh-seo' ),
                $search_query,
                get_bloginfo( 'name' )
            );
        } else {
            $description = $this->get_default_social_description( $settings );
        }

        return $this->append_page_number_to_text( $this->clean_description( $description ) );
    }

    private function output_rel_prev_next() {
        if ( is_singular() ) {
            return;
        }

        global $wp_query;

        $current_page = $this->get_current_page_number();
        $max_pages    = isset( $wp_query->max_num_pages ) ? absint( $wp_query->max_num_pages ) : 0;

        if ( $max_pages <= 1 ) {
            return;
        }

        if ( $current_page > 1 ) {
            $previous_url = $this->normalize_canonical_url( get_pagenum_link( $current_page - 1 ), true, false );
            echo '<link rel="prev" href="' . esc_url( $previous_url ) . '" />' . "\n";
        }

        if ( $current_page < $max_pages ) {
            $next_url = $this->normalize_canonical_url( get_pagenum_link( $current_page + 1 ), true, false );
            echo '<link rel="next" href="' . esc_url( $next_url ) . '" />' . "\n";
        }
    }

    public function output_meta_tags() {
        $settings = Shynh_SEO_Core::get_settings();
        
        echo "\n<!-- Shynh SEO -->\n";

        // Singular specific logic
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $post    = get_post( $post_id );

            // Descriptions
            $meta_desc = get_post_meta( $post_id, '_shynh_seo_meta_desc', true );
            if ( $meta_desc ) {
                $meta_desc = self::replace_variables( $meta_desc, $post_id );
            } else {
                if ( is_attachment() ) {
                    $meta_desc = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 30, '...' ); // Caption
                    if ( empty( $meta_desc ) ) {
                        $meta_desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' ); // Description
                    }
                    if ( empty( $meta_desc ) ) {
                        $meta_desc = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
                    }
                    if ( empty( $meta_desc ) && $post->post_parent ) {
                        $parent = get_post( $post->post_parent );
                        if ( $parent ) {
                            $meta_desc = wp_trim_words( $parent->post_excerpt ?: wp_strip_all_tags( $parent->post_content ), 30, '...' );
                        }
                    }
                } elseif ( 'shynh_author' === get_post_type( $post_id ) ) {
                    $meta_desc = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 30, '...' );
                } else {
                    $meta_desc = self::replace_variables( $settings['default_meta_desc'], $post_id );
                }
            }
            if ( empty( $meta_desc ) ) {
                $meta_desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
            }

            // Canonical
            if ( is_front_page() ) {
                $canonical = $this->get_current_clean_url();
            } else {
                $canonical = get_post_meta( $post_id, '_shynh_seo_canonical', true );
                if ( empty( $canonical ) ) {
                    $canonical = is_attachment() ? get_attachment_link( $post_id ) : get_permalink( $post_id );
                }
            }
            $canonical = $this->normalize_canonical_url( $canonical );

            // Robots
            $robots_index   = get_post_meta( $post_id, '_shynh_seo_robots_index', true ) ?: 'index';
            $robots_follow  = get_post_meta( $post_id, '_shynh_seo_robots_follow', true ) ?: 'follow';
            $robots_content = $this->build_robots_content( $settings, 'post', $post_id, $robots_index, $robots_follow );

            // Open Graph
            $fb_title = get_post_meta( $post_id, '_shynh_seo_fb_title', true );
            $og_title = $fb_title ? self::replace_variables( $fb_title, $post_id ) : $this->get_custom_title( '' );

            $fb_desc = get_post_meta( $post_id, '_shynh_seo_fb_desc', true );
            $og_desc = $fb_desc ? self::replace_variables( $fb_desc, $post_id ) : $meta_desc;
            if ( empty( $og_desc ) ) {
                $og_desc = $this->get_default_social_description( $settings, $post_id );
            }

            $og_image = get_post_meta( $post_id, '_shynh_seo_fb_image', true );

            if ( empty( $og_image ) ) {
                if ( is_attachment() && wp_attachment_is_image( $post_id ) ) {
                    $og_image = wp_get_attachment_url( $post_id );
                } elseif ( has_post_thumbnail( $post_id ) ) {
                    $og_image = get_the_post_thumbnail_url( $post_id, 'full' );
                }
            }
            if ( empty( $og_image ) ) {
                $og_image = $settings['default_og_image'];
            }

            // Twitter Card
            $tw_title_meta = get_post_meta( $post_id, '_shynh_seo_tw_title', true );
            $tw_title = $tw_title_meta ? self::replace_variables( $tw_title_meta, $post_id ) : $og_title;
            
            $tw_desc_meta = get_post_meta( $post_id, '_shynh_seo_tw_desc', true );
            $tw_desc  = $tw_desc_meta ? self::replace_variables( $tw_desc_meta, $post_id ) : $og_desc;
            if ( empty( $tw_desc ) ) {
                $tw_desc = $this->get_default_social_description( $settings, $post_id );
            }
            
            $tw_image = get_post_meta( $post_id, '_shynh_seo_tw_image', true ) ?: $og_image;

            // Output Basic Meta
            if ( $meta_desc ) echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta name="robots" content="' . esc_attr( $robots_content ) . '" />' . "\n";

            // Output OG
            $og_type = 'shynh_author' === get_post_type( $post_id ) ? 'profile' : 'article';
            echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $og_image ) echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            if ( is_attachment() && wp_attachment_is_image( $post_id ) ) {
                $meta_data = wp_get_attachment_metadata( $post_id );
                if ( ! empty( $meta_data['width'] ) ) {
                    echo '<meta property="og:image:width" content="' . esc_attr( $meta_data['width'] ) . '" />' . "\n";
                }
                if ( ! empty( $meta_data['height'] ) ) {
                    echo '<meta property="og:image:height" content="' . esc_attr( $meta_data['height'] ) . '" />' . "\n";
                }
                $alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
                if ( $alt ) {
                    echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n";
                }
            }
            echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            // Output Twitter
            echo '<meta name="twitter:card" content="' . esc_attr( $settings['twitter_card_type'] ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $tw_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $tw_desc ) . '" />' . "\n";
            if ( $tw_image ) echo '<meta name="twitter:image" content="' . esc_url( $tw_image ) . '" />' . "\n";

        } elseif ( is_category() || is_tag() || is_tax() ) {
            // Taxonomy Term logic
            $term_id = get_queried_object_id();
            $term    = get_term( $term_id );

            $meta_desc = get_term_meta( $term_id, '_shynh_seo_meta_desc', true );
            if ( $meta_desc ) {
                $meta_desc = self::replace_variables( $meta_desc, 0, $term_id );
            } else {
                $meta_desc = self::replace_variables( $settings['default_meta_desc'], 0, $term_id );
            }
            if ( empty( $meta_desc ) && $term && ! is_wp_error( $term ) ) {
                $meta_desc = wp_trim_words( wp_strip_all_tags( $term->description ), 30, '...' );
            }

            $canonical = get_term_meta( $term_id, '_shynh_seo_canonical', true );
            if ( empty( $canonical ) ) {
                $canonical = get_term_link( $term );
            }
            $canonical = $this->normalize_canonical_url( $canonical );

            $robots_index   = get_term_meta( $term_id, '_shynh_seo_robots_index', true ) ?: 'index';
            $robots_follow  = get_term_meta( $term_id, '_shynh_seo_robots_follow', true ) ?: 'follow';
            $robots_content = $this->build_robots_content( $settings, 'term', $term_id, $robots_index, $robots_follow );

            // Open Graph
            $fb_title = get_term_meta( $term_id, '_shynh_seo_fb_title', true );
            $og_title = $fb_title ? self::replace_variables( $fb_title, 0, $term_id ) : $this->get_custom_title( '' );

            $fb_desc = get_term_meta( $term_id, '_shynh_seo_fb_desc', true );
            $og_desc = $fb_desc ? self::replace_variables( $fb_desc, 0, $term_id ) : $meta_desc;
            if ( empty( $og_desc ) ) {
                $og_desc = $this->get_default_social_description( $settings, 0, $term_id );
            }

            $og_image = get_term_meta( $term_id, '_shynh_seo_fb_image', true );
            if ( empty( $og_image ) ) {
                $og_image = $settings['default_og_image'];
            }

            // Twitter Card
            $tw_title_meta = get_term_meta( $term_id, '_shynh_seo_tw_title', true );
            $tw_title = $tw_title_meta ? self::replace_variables( $tw_title_meta, 0, $term_id ) : $og_title;

            $tw_desc_meta = get_term_meta( $term_id, '_shynh_seo_tw_desc', true );
            $tw_desc  = $tw_desc_meta ? self::replace_variables( $tw_desc_meta, 0, $term_id ) : $og_desc;
            if ( empty( $tw_desc ) ) {
                $tw_desc = $this->get_default_social_description( $settings, 0, $term_id );
            }

            $tw_image = get_term_meta( $term_id, '_shynh_seo_tw_image', true ) ?: $og_image;

            // Output Basic Meta
            if ( $meta_desc ) echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta name="robots" content="' . esc_attr( $robots_content ) . '" />' . "\n";

            // Output OG
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $og_image ) echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            // Output Twitter
            echo '<meta name="twitter:card" content="' . esc_attr( $settings['twitter_card_type'] ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $tw_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $tw_desc ) . '" />' . "\n";
            if ( $tw_image ) echo '<meta name="twitter:image" content="' . esc_url( $tw_image ) . '" />' . "\n";

        } elseif ( is_search() ) {
            $meta_desc      = $this->get_search_description( $settings );
            $canonical      = $this->normalize_canonical_url( get_search_link( get_search_query( false ) ), true );
            $robots_content = $this->build_robots_content( $settings );
            $og_title       = $this->get_custom_title( '' );
            $og_desc        = $meta_desc ?: $this->get_default_social_description( $settings );

            if ( $meta_desc ) echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            if ( empty( $settings['search_noindex'] ) ) {
                echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            }
            echo '<meta name="robots" content="' . esc_attr( $robots_content ) . '" />' . "\n";

            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            if ( $og_desc ) echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta property="og:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            echo '<meta name="twitter:card" content="' . esc_attr( $settings['twitter_card_type'] ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            if ( $og_desc ) echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta name="twitter:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";

        } elseif ( is_404() ) {
            $meta_desc      = __( 'Trang bạn đang tìm kiếm không tồn tại hoặc đã được di chuyển.', 'shynh-seo' );
            $current_url    = $this->get_current_clean_url();
            $robots_content = $this->build_robots_content( $settings );
            $og_title       = $this->get_custom_title( '' );

            echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            if ( $this->is_paginated_request_path() ) {
                echo '<link rel="canonical" href="' . esc_url( $current_url ) . '" />' . "\n";
            }
            echo '<meta name="robots" content="' . esc_attr( $robots_content ) . '" />' . "\n";

            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta property="og:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $current_url ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            echo '<meta name="twitter:card" content="' . esc_attr( $settings['twitter_card_type'] ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta name="twitter:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";

        } else {
            // Archive / Home logic
            $meta_desc = self::replace_variables( $settings['default_meta_desc'], 0 );
            if ( empty( $meta_desc ) ) {
                $meta_desc = $this->get_default_social_description( $settings );
            }
            $meta_desc = $this->append_page_number_to_text( $this->clean_description( $meta_desc ) );
            $og_title = $this->get_custom_title( '' );
            $og_desc  = $this->append_page_number_to_text( $this->get_default_social_description( $settings ) );
            $canonical = $this->get_current_clean_url();
            $robots_content = $this->build_robots_content( $settings );

            if ( $meta_desc ) echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            if ( ! is_404() && ! is_search() ) {
                echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            }
            echo '<meta name="robots" content="' . esc_attr( $robots_content ) . '" />' . "\n";
            
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            if ( $og_desc ) echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta property="og:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            echo '<meta name="twitter:card" content="' . esc_attr( $settings['twitter_card_type'] ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            if ( $og_desc ) echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            if ( $settings['default_og_image'] ) echo '<meta name="twitter:image" content="' . esc_url( $settings['default_og_image'] ) . '" />' . "\n";
        }

        $this->output_rel_prev_next();

        // Facebook App ID
        if ( ! empty( $settings['facebook_app_id'] ) ) {
            echo '<meta property="fb:app_id" content="' . esc_attr( $settings['facebook_app_id'] ) . '" />' . "\n";
        }

        // Polylang Hreflang Tags
        if ( function_exists( 'pll_the_languages' ) ) {
            $translations = pll_the_languages( [ 'raw' => 1 ] );
            if ( ! empty( $translations ) ) {
                foreach ( $translations as $lang ) {
                    if ( ! empty( $lang['url'] ) ) {
                        $hreflang = $this->normalize_hreflang_code( $lang );
                        echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $lang['url'] ) . '" />' . "\n";
                    }
                }
                // Determine x-default (usually default language)
                $default_lang = pll_default_language();
                if ( isset( $translations[ $default_lang ] ) && ! empty( $translations[ $default_lang ]['url'] ) ) {
                    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $translations[ $default_lang ]['url'] ) . '" />' . "\n";
                }
            }
        }

        echo "<!-- /Shynh SEO -->\n";
    }

    private function normalize_hreflang_code( $lang ) {
        $code = '';

        if ( is_array( $lang ) ) {
            $code = ! empty( $lang['locale'] ) ? (string) $lang['locale'] : (string) ( $lang['slug'] ?? '' );
        }

        $code = trim( str_replace( '_', '-', $code ) );
        if ( '' === $code ) {
            return '';
        }

        $parts = explode( '-', $code );
        $parts[0] = strtolower( $parts[0] );
        if ( isset( $parts[1] ) ) {
            $parts[1] = strtoupper( $parts[1] );
        }

        return implode( '-', $parts );
    }

    /**
     * Helper to output Yoast-like Breadcrumbs
     * Usage: if ( class_exists('Shynh_SEO_Frontend') ) { Shynh_SEO_Frontend::get_breadcrumb(); }
     */
    public static function get_breadcrumb() {
        if ( is_front_page() || is_home() ) return;
        
        echo '<div class="shynh-seo-breadcrumbs" style="font-size: 14px; margin-bottom: 20px;">';
        echo '<a href="' . esc_url( home_url( '/' ) ) . '">Trang chủ</a> &raquo; ';
        
        if ( is_singular() ) {
            $post_id = get_the_ID();
            
            $term = null;
            if ( class_exists( 'Shynh_SEO_Core' ) ) {
                $term = Shynh_SEO_Core::get_primary_term( $post_id );
            }

            if ( ! $term ) {
                $taxonomies = get_object_taxonomies( get_post_type(), 'objects' );
                $primary_tax = null;
                foreach ( $taxonomies as $tax ) {
                    if ( $tax->hierarchical ) {
                        $primary_tax = $tax->name;
                        if ( $tax->name === 'category' ) break;
                    }
                }
                if ( $primary_tax ) {
                    $terms = wp_get_post_terms( $post_id, $primary_tax );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        $term = $terms[0];
                    }
                }
            }
            
            if ( $term && ! is_wp_error( $term ) ) {
                if ( $term->taxonomy === 'category' ) {
                    echo wp_kses_post( get_category_parents( $term->term_id, true, ' &raquo; ' ) );
                } else {
                    echo '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a> &raquo; ';
                }
            }
            
            // Custom Breadcrumb Title or Post Title
            $breadcrumb_title = get_post_meta( $post_id, '_shynh_seo_breadcrumb_title', true );
            $title = $breadcrumb_title ? $breadcrumb_title : get_the_title();
            echo '<strong>' . esc_html( $title ) . '</strong>';
            
        } elseif ( is_category() ) {
            echo '<strong>' . single_cat_title( '', false ) . '</strong>';
        } elseif ( is_tag() ) {
            echo '<strong>' . single_tag_title( '', false ) . '</strong>';
        } elseif ( is_archive() ) {
            echo '<strong>' . get_the_archive_title() . '</strong>';
        } elseif ( is_search() ) {
            echo '<strong>Kết quả tìm kiếm</strong>';
        } elseif ( is_404() ) {
            echo '<strong>Lỗi 404</strong>';
        }
        
        echo '</div>';
    }
}
