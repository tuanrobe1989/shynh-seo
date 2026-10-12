<?php
/**
 * Shynh SEO — Frontend Rendered Content Fetcher & Parser.
 *
 * Fetches the actual rendered Frontend page HTML or Markdown representation
 * (via Markdown for AI Agents plugin or DOM parser) to analyze real page layout,
 * headings, images, word count, and internal links for accurate SEO & GEO scoring.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Frontend_Fetcher {

    /**
     * Fetch and parse rendered Frontend content for a post or term.
     *
     * @param int    $object_id   Post or Term ID.
     * @param string $object_type 'post' or 'term'.
     * @param string $taxonomy    Taxonomy slug if object_type is 'term'.
     * @return array|WP_Error Parsed FE content array or WP_Error.
     */
    public static function fetch_content( $object_id, $object_type = 'post', $taxonomy = '' ) {
        $url = '';

        if ( 'term' === $object_type ) {
            if ( empty( $taxonomy ) && $object_id ) {
                $term_obj = get_term( $object_id );
                if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                    $taxonomy = $term_obj->taxonomy;
                }
            }
            $term_link = get_term_link( (int) $object_id, $taxonomy );
            if ( $term_link && ! is_wp_error( $term_link ) ) {
                $url = $term_link;
            }
        } else {
            $permalink = get_permalink( $object_id );
            if ( $permalink && ! is_wp_error( $permalink ) ) {
                $url = $permalink;
            }
        }

        if ( empty( $url ) ) {
            return new WP_Error( 'no_url', __( 'Không thể xác định đường dẫn trang Frontend.', 'shynh-seo' ) );
        }

        // Make HTTP request with Accept: text/markdown header first to utilize Markdown for AI Agents
        $response = wp_remote_get( $url, [
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => [
                'Accept'     => 'text/markdown, text/html, */*',
                'User-Agent' => 'Shynh-SEO-Analyzer/1.0 (+http://' . ( $_SERVER['HTTP_HOST'] ?? 'localhost' ) . ')',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $body         = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', __( 'Trang Frontend trả về nội dung rỗng.', 'shynh-seo' ) );
        }

        // If response is Markdown (from Markdown for AI Agents plugin)
        if ( strpos( $content_type, 'text/markdown' ) !== false || false !== strpos( $body, '# ' ) ) {
            return self::parse_markdown( $body, $url );
        }

        // Fallback to HTML parsing
        return self::parse_html( $body, $url );
    }

    /**
     * Parse Markdown output into structured analysis arrays.
     */
    private static function parse_markdown( $markdown, $url ) {
        $lines    = explode( "\n", $markdown );
        $headings = [];
        $images   = [];
        $links    = [];
        $clean_text_lines = [];

        $site_host = wp_parse_url( get_home_url(), PHP_URL_HOST );

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Check headings (# H1, ## H2, ### H3, #### H4)
            if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $m ) ) {
                $level = strlen( $m[1] );
                $headings[] = [
                    'tag'  => 'h' . $level,
                    'text' => trim( wp_strip_all_tags( $m[2] ) ),
                ];
                continue;
            }

            // Check Markdown images ![alt](url)
            if ( preg_match_all( '/!\[([^\]]*)\]\(([^)]+)\)/', $trimmed, $img_matches, PREG_SET_ORDER ) ) {
                foreach ( $img_matches as $img ) {
                    $images[] = [
                        'alt' => trim( $img[1] ),
                        'src' => trim( $img[2] ),
                    ];
                }
            }

            // Check Markdown links [text](url)
            if ( preg_match_all( '/\[([^\]]+)\]\(([^)]+)\)/', $trimmed, $link_matches, PREG_SET_ORDER ) ) {
                foreach ( $link_matches as $lnk ) {
                    $link_url = trim( $lnk[2] );
                    $link_text = trim( $lnk[1] );
                    $link_host = wp_parse_url( $link_url, PHP_URL_HOST );

                    // If internal link
                    if ( empty( $link_host ) || $link_host === $site_host ) {
                        $links[] = [
                            'url'     => $link_url,
                            'text'    => $link_text,
                            'is_bold' => (bool) preg_match( '/\*\*|\_\_/', $trimmed ),
                        ];
                    }
                }
            }

            // Strip Markdown links & images syntax for clean text
            $clean_line = preg_replace( '/!\[[^\]]*\]\([^)]+\)/', '', $line );
            $clean_line = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $clean_line );
            $clean_line = wp_strip_all_tags( $clean_line );
            if ( '' !== trim( $clean_line ) ) {
                $clean_text_lines[] = $clean_line;
            }
        }

        $plain_text = implode( ' ', $clean_text_lines );
        $words      = preg_split( '/\s+/u', trim( $plain_text ), -1, PREG_SPLIT_NO_EMPTY );

        return [
            'url'            => $url,
            'word_count'     => count( $words ),
            'plain_text'     => $plain_text,
            'headings'       => $headings,
            'images'         => $images,
            'internal_links' => $links,
        ];
    }

    /**
     * Parse raw HTML output into structured analysis arrays.
     */
    private static function parse_html( $html, $url ) {
        // Strip scripts, styles, header, footer, nav
        $html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
        $html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );
        $html = preg_replace( '/<header\b[^>]*>(.*?)<\/header>/is', '', $html );
        $html = preg_replace( '/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html );
        $html = preg_replace( '/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html );

        // Extract main container if present (.site-main, .entry-content, article, #content, .term-description)
        if ( preg_match( '/<(main|article|div)\b[^>]*class="[^"]*(site-main|entry-content|term-description|content)[^"]*"[^>]*>(.*?)<\/\1>/is', $html, $m ) ) {
            $main_html = $m[0];
        } else {
            $main_html = $html;
        }

        // Extract Headings
        $headings = [];
        if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $main_html, $h_matches, PREG_SET_ORDER ) ) {
            foreach ( $h_matches as $h ) {
                $headings[] = [
                    'tag'  => 'h' . $h[1],
                    'text' => trim( wp_strip_all_tags( $h[2] ) ),
                ];
            }
        }

        // Extract Images
        $images = [];
        if ( preg_match_all( '/<img\b[^>]*>/is', $main_html, $img_tags ) ) {
            foreach ( $img_tags[0] as $img_tag ) {
                $alt = '';
                $src = '';
                if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $img_tag, $m_alt ) ) {
                    $alt = trim( $m_alt[1] );
                }
                if ( preg_match( '/src=["\']([^"\']*)["\']/i', $img_tag, $m_src ) ) {
                    $src = trim( $m_src[1] );
                }
                $images[] = [
                    'alt' => $alt,
                    'src' => $src,
                ];
            }
        }

        // Extract Internal Links
        $links     = [];
        $site_host = wp_parse_url( get_home_url(), PHP_URL_HOST );

        if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $main_html, $a_matches, PREG_SET_ORDER ) ) {
            foreach ( $a_matches as $a ) {
                $link_url  = trim( $a[1] );
                $link_text = trim( wp_strip_all_tags( $a[2] ) );
                $link_host = wp_parse_url( $link_url, PHP_URL_HOST );

                if ( empty( $link_host ) || $link_host === $site_host ) {
                    $is_bold = (bool) preg_match( '/<(strong|b)\b[^>]*>/i', $a[2] );
                    $links[] = [
                        'url'     => $link_url,
                        'text'    => $link_text,
                        'is_bold' => $is_bold,
                    ];
                }
            }
        }

        $plain_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $main_html ) ) );
        $words      = preg_split( '/\s+/u', $plain_text, -1, PREG_SPLIT_NO_EMPTY );

        return [
            'url'            => $url,
            'word_count'     => count( $words ),
            'plain_text'     => $plain_text,
            'headings'       => $headings,
            'images'         => $images,
            'internal_links' => $links,
        ];
    }
}
