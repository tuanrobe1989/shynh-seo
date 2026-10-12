<?php
/**
 * Extract normalized SEO signals from block content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Content_Extractor {

    public static function extract( $post_id, $content ) {
        $content = (string) $content;
        $html    = self::render_content( $post_id, $content );

        $context = [
            'html'                => $html,
            'plain_text'          => '',
            'headings'            => [],
            'links'               => [],
            'internal_links'      => [],
            'bold_internal_links' => [],
            'images'              => [],
            'internal_link_index' => [],
        ];

        $html_context = self::extract_from_html( $html );
        $block_context = self::extract_from_blocks( $content );

        $context['plain_text'] = trim( $html_context['plain_text'] );
        if ( '' === $context['plain_text'] ) {
            $context['plain_text'] = trim( wp_strip_all_tags( $content . ' ' . $block_context['plain_text'] ) );
        }

        $context['headings'] = self::dedupe_items(
            array_merge( $html_context['headings'], $block_context['headings'] ),
            [ 'level', 'text' ]
        );

        $context['links'] = self::merge_links( array_merge( $html_context['links'], $block_context['links'] ) );

        $context['images'] = self::dedupe_items( $html_context['images'], [ 'src', 'alt' ] );

        foreach ( $context['links'] as $link ) {
            if ( empty( $link['is_internal'] ) ) {
                continue;
            }

            $context['internal_links'][] = $link;

            if ( ! empty( $link['is_bold'] ) ) {
                $context['bold_internal_links'][] = $link;
            }

            foreach ( self::get_internal_link_index_keys( $link['href'] ) as $key ) {
                $context['internal_link_index'][] = $key;
            }
        }

        $context['internal_link_index'] = array_values( array_unique( array_filter( $context['internal_link_index'] ) ) );

        return $context;
    }

    public static function get_internal_link_index_keys( $url ) {
        $url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );

        if ( '' === $url || 0 === strpos( $url, '#' ) ) {
            return [];
        }

        if ( preg_match( '/^(mailto|tel|sms|javascript):/i', $url ) ) {
            return [];
        }

        $home_parts = wp_parse_url( home_url() );
        $home_host  = isset( $home_parts['host'] ) ? strtolower( $home_parts['host'] ) : '';
        $parts      = wp_parse_url( $url );

        if ( false === $parts ) {
            return [];
        }

        $host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        if ( $host && $home_host && $host !== $home_host ) {
            return [];
        }

        $path = isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
        $path = '/' . ltrim( $path, '/' );
        $path = untrailingslashit( $path );
        if ( '' === $path ) {
            $path = '/';
        }

        $keys = [ $path ];

        if ( $home_host ) {
            $keys[] = $home_host . $path;
        }

        return array_values( array_unique( $keys ) );
    }

    private static function render_content( $post_id, $content ) {
        $previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
        $post          = $post_id ? get_post( $post_id ) : null;

        if ( $post ) {
            $GLOBALS['post'] = $post;
            setup_postdata( $post );
        }

        try {
            $html = function_exists( 'do_blocks' ) ? do_blocks( $content ) : $content;

            if ( function_exists( 'do_shortcode' ) ) {
                $html = do_shortcode( $html );
            }
        } catch ( Throwable $e ) {
            $html = $content;
        }

        if ( $post ) {
            wp_reset_postdata();
            if ( $previous_post ) {
                $GLOBALS['post'] = $previous_post;
            }
        }

        return (string) $html;
    }

    private static function extract_from_html( $html ) {
        $context = [
            'plain_text' => '',
            'headings'   => [],
            'links'      => [],
            'images'     => [],
        ];

        if ( '' === trim( (string) $html ) ) {
            return $context;
        }

        if ( ! class_exists( 'DOMDocument' ) ) {
            return self::extract_from_html_regex( $html );
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $flags = 0;
        if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><div id="shynh-seo-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return self::extract_from_html_regex( $html );
        }

        $xpath = new DOMXPath( $doc );
        foreach ( $xpath->query( '//script|//style|//noscript' ) as $node ) {
            if ( $node->parentNode ) {
                $node->parentNode->removeChild( $node );
            }
        }

        $root = $doc->getElementById( 'shynh-seo-root' );
        $context['plain_text'] = $root ? trim( preg_replace( '/\s+/', ' ', $root->textContent ) ) : '';

        self::walk_dom( $root ?: $doc, $context );

        return $context;
    }

    private static function walk_dom( $node, &$context ) {
        if ( ! $node || ! $node->childNodes ) {
            return;
        }

        foreach ( $node->childNodes as $child ) {
            if ( XML_ELEMENT_NODE === $child->nodeType ) {
                $tag = strtolower( $child->nodeName );

                if ( preg_match( '/^h([1-9]|1[0-6])$/', $tag, $matches ) ) {
                    $context['headings'][] = [
                        'level'  => (int) $matches[1],
                        'text'   => trim( preg_replace( '/\s+/', ' ', $child->textContent ) ),
                        'source' => 'html',
                    ];
                }

                if ( 'a' === $tag ) {
                    $href = $child->getAttribute( 'href' );
                    $is_internal = ! empty( self::get_internal_link_index_keys( $href ) );
                    $context['links'][] = [
                        'href'        => $href,
                        'text'        => trim( preg_replace( '/\s+/', ' ', $child->textContent ) ),
                        'is_internal' => $is_internal,
                        'is_bold'     => self::node_is_bold( $child ),
                        'source'      => 'html',
                    ];
                }

                if ( 'img' === $tag ) {
                    $context['images'][] = [
                        'src' => $child->getAttribute( 'src' ),
                        'alt' => $child->getAttribute( 'alt' ),
                    ];
                }
            }

            self::walk_dom( $child, $context );
        }
    }

    private static function node_is_bold( $node ) {
        if ( self::node_tree_has_bold_marker( $node ) ) {
            return true;
        }

        while ( $node && XML_ELEMENT_NODE === $node->nodeType ) {
            if ( self::element_has_bold_marker( $node ) ) {
                return true;
            }

            $node = $node->parentNode;
        }

        return false;
    }

    private static function node_tree_has_bold_marker( $node ) {
        if ( ! $node ) {
            return false;
        }

        if ( XML_ELEMENT_NODE === $node->nodeType && self::element_has_bold_marker( $node ) ) {
            return true;
        }

        if ( ! $node->childNodes ) {
            return false;
        }

        foreach ( $node->childNodes as $child ) {
            if ( self::node_tree_has_bold_marker( $child ) ) {
                return true;
            }
        }

        return false;
    }

    private static function element_has_bold_marker( $node ) {
        $tag = strtolower( $node->nodeName );
        if ( in_array( $tag, [ 'strong', 'b' ], true ) ) {
            return true;
        }

        $class = strtolower( (string) $node->getAttribute( 'class' ) );
        if ( preg_match( '/\b(font-semibold|font-bold|font-extrabold|font-black|has-bold-font-weight)\b/', $class ) ) {
            return true;
        }

        $style = strtolower( (string) $node->getAttribute( 'style' ) );
        return false !== strpos( $style, 'font-weight' ) && preg_match( '/font-weight\s*:\s*(bold|[6-9]00|[1-9]\d{3,})/', $style );
    }

    private static function extract_from_html_regex( $html ) {
        $context = [
            'plain_text' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ),
            'headings'   => [],
            'links'      => [],
            'images'     => [],
        ];

        preg_match_all( '/<(h([1-9]|1[0-6]))[^>]*>(.*?)<\/\1>/is', $html, $heading_matches, PREG_SET_ORDER );
        foreach ( $heading_matches as $match ) {
            $context['headings'][] = [
                'level'  => (int) $match[2],
                'text'   => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $match[3] ) ) ),
                'source' => 'html',
            ];
        }

        preg_match_all( '/<a\b([^>]*)href=[\'"]([^\'"]+)[\'"]([^>]*)>(.*?)<\/a>/is', $html, $link_matches, PREG_SET_ORDER );
        foreach ( $link_matches as $match ) {
            $href = $match[2];
            $link_html = $match[0];
            $is_internal = ! empty( self::get_internal_link_index_keys( $href ) );
            $context['links'][] = [
                'href'        => $href,
                'text'        => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $match[4] ) ) ),
                'is_internal' => $is_internal,
                'is_bold'     => (bool) preg_match( '/<(strong|b)\b|font-(semibold|bold|extrabold|black)|font-weight\s*:\s*(bold|[6-9]00)/i', $link_html ),
                'source'      => 'html',
            ];
        }

        preg_match_all( '/<img\b[^>]*>/is', $html, $image_matches );
        foreach ( $image_matches[0] as $image_html ) {
            preg_match( '/\bsrc=[\'"]([^\'"]*)[\'"]/i', $image_html, $src_match );
            preg_match( '/\balt=[\'"]([^\'"]*)[\'"]/i', $image_html, $alt_match );
            $context['images'][] = [
                'src' => $src_match[1] ?? '',
                'alt' => $alt_match[1] ?? '',
            ];
        }

        return $context;
    }

    private static function extract_from_blocks( $content ) {
        $context = [
            'plain_text' => '',
            'headings'   => [],
            'links'      => [],
        ];

        if ( ! function_exists( 'parse_blocks' ) || ! has_blocks( $content ) ) {
            return $context;
        }

        $blocks = parse_blocks( $content );
        self::walk_blocks( $blocks, $context );
        $context['plain_text'] = trim( preg_replace( '/\s+/', ' ', $context['plain_text'] ) );

        return $context;
    }

    private static function walk_blocks( $blocks, &$context ) {
        foreach ( (array) $blocks as $block ) {
            $name  = $block['blockName'] ?? '';
            $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

            if ( 'core/heading' === $name && ! empty( $attrs['content'] ) ) {
                $context['headings'][] = [
                    'level'  => isset( $attrs['level'] ) ? absint( $attrs['level'] ) : 2,
                    'text'   => trim( wp_strip_all_tags( $attrs['content'] ) ),
                    'source' => 'block',
                ];
            }

            self::scan_attributes( $attrs, $context );

            if ( ! empty( $block['innerBlocks'] ) ) {
                self::walk_blocks( $block['innerBlocks'], $context );
            }
        }
    }

    private static function scan_attributes( $value, &$context, $key_path = '' ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                $next_key = '' === $key_path ? (string) $key : $key_path . '.' . $key;
                self::scan_attributes( $child, $context, $next_key );
            }
            return;
        }

        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return;
        }

        if ( self::is_link_key( $key_path ) ) {
            $context['links'][] = [
                'href'        => $value,
                'text'        => '',
                'is_internal' => ! empty( self::get_internal_link_index_keys( $value ) ),
                'is_bold'     => false,
                'source'      => 'block_attr',
            ];
        }

        if ( self::is_heading_key( $key_path ) ) {
            $context['headings'][] = [
                'level'  => 0,
                'text'   => trim( wp_strip_all_tags( $value ) ),
                'source' => 'block_attr',
            ];
        }

        if ( self::is_text_key( $key_path ) ) {
            $context['plain_text'] .= ' ' . wp_strip_all_tags( $value );
        }

        if ( false !== strpos( $value, '<' ) && false !== strpos( $value, '>' ) ) {
            $html_context = self::extract_from_html( $value );
            $context['headings'] = array_merge( $context['headings'], $html_context['headings'] );
            $context['links']    = array_merge( $context['links'], $html_context['links'] );
        }
    }

    private static function is_heading_key( $key ) {
        return (bool) preg_match( '/(^|\.|_)(heading|headline|title|subtitle|sectiontitle|question|name|label)(\.|_|$)/i', $key );
    }

    private static function is_text_key( $key ) {
        if ( preg_match( '/(class|style|color|background|image|img|src|icon|logo|id)$/i', $key ) ) {
            return false;
        }

        return (bool) preg_match( '/(^|\.|_)(content|text|description|excerpt|answer|caption|alt|kicker|eyebrow|summary|label)(\.|_|$)/i', $key );
    }

    private static function is_link_key( $key ) {
        if ( preg_match( '/(class|style|image|img|src|background|media|logo|icon|video|file)/i', $key ) ) {
            return false;
        }

        return (bool) preg_match( '/(url|href|link|permalink)/i', $key );
    }

    private static function dedupe_items( $items, $keys ) {
        $seen = [];
        $out  = [];

        foreach ( $items as $item ) {
            $fingerprint_parts = [];
            foreach ( $keys as $key ) {
                $fingerprint_parts[] = isset( $item[ $key ] ) ? strtolower( (string) $item[ $key ] ) : '';
            }

            $fingerprint = implode( '|', $fingerprint_parts );
            if ( isset( $seen[ $fingerprint ] ) ) {
                continue;
            }

            $seen[ $fingerprint ] = true;
            $out[] = $item;
        }

        return $out;
    }

    private static function merge_links( $links ) {
        $merged = [];

        foreach ( $links as $link ) {
            $href = isset( $link['href'] ) ? trim( (string) $link['href'] ) : '';
            if ( '' === $href ) {
                continue;
            }

            $fingerprint = strtolower( html_entity_decode( $href, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' ) );

            if ( ! isset( $merged[ $fingerprint ] ) ) {
                $merged[ $fingerprint ] = $link;
                continue;
            }

            if ( empty( $merged[ $fingerprint ]['text'] ) && ! empty( $link['text'] ) ) {
                $merged[ $fingerprint ]['text'] = $link['text'];
            }

            $merged[ $fingerprint ]['is_internal'] = ! empty( $merged[ $fingerprint ]['is_internal'] ) || ! empty( $link['is_internal'] );
            $merged[ $fingerprint ]['is_bold'] = ! empty( $merged[ $fingerprint ]['is_bold'] ) || ! empty( $link['is_bold'] );
        }

        return array_values( $merged );
    }
}
