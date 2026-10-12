<?php
/**
 * Auto Redirect Manager (Giống Yoast Premium)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Redirects {

    public static function init() {
        add_action( 'post_updated', [ __CLASS__, 'check_slug_change' ], 10, 3 );
        add_action( 'template_redirect', [ __CLASS__, 'process_redirects' ], 1 );
    }

    public static function check_slug_change( $post_id, $post_after, $post_before ) {
        if ( wp_is_post_revision( $post_id ) ) return;
        
        // Only track published posts changing slug
        if ( $post_after->post_status !== 'publish' ) return;

        $old_slug = $post_before->post_name;
        $new_slug = $post_after->post_name;

        if ( $old_slug && $new_slug && $old_slug !== $new_slug ) {
            $new_permalink = get_permalink( $post_id );
            // Estimate old permalink by replacing the slug
            $old_permalink = str_replace( '/' . $new_slug . '/', '/' . $old_slug . '/', $new_permalink );
            
            // Fallback if the slug is at the very end without trailing slash
            if ( $old_permalink === $new_permalink ) {
                $old_permalink = preg_replace( '/' . preg_quote($new_slug, '/') . '$/', $old_slug, $new_permalink );
            }

            $old_path = self::normalize_path( $old_permalink );
            $new_path = self::normalize_path( $new_permalink );

            if ( $old_path && $new_path && $old_path !== $new_path ) {
                $redirects = get_option( 'shynh_seo_redirects', [] );
                
                // Remove a direct reverse redirect before storing the new route.
                if ( isset( $redirects[ $new_path ] ) && self::normalize_path( $redirects[ $new_path ] ) === $old_path ) {
                    unset( $redirects[ $new_path ] );
                }

                $redirects[ $old_path ] = $new_path;
                update_option( 'shynh_seo_redirects', $redirects );
            }
        }
    }

    /**
     * Normalize a local URL or request path for exact redirect matching.
     *
     * @param string $url_or_path URL or path to normalize.
     * @return string
     */
    public static function normalize_path( $url_or_path ) {
        $path = wp_parse_url( html_entity_decode( (string) $url_or_path, ENT_QUOTES, 'UTF-8' ), PHP_URL_PATH );

        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        $path = '/' . ltrim( preg_replace( '#/+#', '/', $path ), '/' );

        return '/' === $path ? '/' : trailingslashit( $path );
    }

    /**
     * Resolve an exact redirect without depending on WordPress returning a 404.
     *
     * @param string $request_uri Current request URI.
     * @return string Absolute internal target URL, or an empty string.
     */
    public static function get_redirect_target( $request_uri ) {
        $current_path = self::normalize_path( $request_uri );
        $redirects    = get_option( 'shynh_seo_redirects', array() );

        if ( '' === $current_path || empty( $redirects ) || ! is_array( $redirects ) ) {
            return '';
        }

        $target = '';
        foreach ( $redirects as $source => $candidate ) {
            if ( self::normalize_path( $source ) === $current_path ) {
                $target = (string) $candidate;
                break;
            }
        }

        if ( '' === $target ) {
            return '';
        }

        $target_host = strtolower( (string) wp_parse_url( $target, PHP_URL_HOST ) );
        $home_host   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

        if ( $target_host && preg_replace( '/^www\./', '', $target_host ) !== preg_replace( '/^www\./', '', $home_host ) ) {
            return '';
        }

        $target_path = self::normalize_path( $target );
        if ( '' === $target_path || $target_path === $current_path ) {
            return '';
        }

        return home_url( $target_path );
    }

    public static function process_redirects() {
        if (
            is_admin()
            || wp_doing_ajax()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( defined( 'DOING_CRON' ) && DOING_CRON )
        ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $target_url  = self::get_redirect_target( $request_uri );

        if ( $target_url ) {
            wp_safe_redirect( $target_url, 301, 'Shynh SEO' );
            exit;
        }
    }
}
