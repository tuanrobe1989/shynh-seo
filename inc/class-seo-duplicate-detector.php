<?php
/**
 * Shynh SEO — Content Duplicate Detector.
 *
 * Computes a SimHash fingerprint of each post's body text and finds near-duplicate
 * pairs, then classifies WHY they are duplicated (near-identical body, shared focus
 * keyword, shared meta, thin/boilerplate, or template variant).
 *
 * Fingerprinting runs in bounded batches (anti-hang); the pair report is built once
 * per scan and cached in an option that the API reads cheaply.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Duplicate_Detector {

    const SIMHASH_META = '_shynh_seo_content_simhash';
    const TIME_META    = '_shynh_seo_simhash_time';
    const REPORT_OPT   = 'shynh_seo_duplicate_report';

    /** Similarity threshold (%) at/above which a pair is reported. */
    const MIN_SIMILARITY = 82;
    /** Cap posts compared to keep pairing bounded. */
    const MAX_POSTS = 1000;
    const MAX_PAIRS = 500;

    /**
     * Fingerprint a bounded batch of posts. When the last batch is processed,
     * (re)builds the pair report. Returns progress for a browser-driven loop.
     */
    public static function scan_batch( $offset, $limit ) {
        $types = Shynh_SEO_Core::get_public_content_post_types();

        $query = new WP_Query( [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );
        $total = (int) $query->found_posts;
        $ids   = $query->posts;

        $processed = 0;
        foreach ( $ids as $pid ) {
            self::fingerprint_post( $pid );
            $processed++;
        }

        $next_offset = $offset + $limit;
        $done        = $next_offset >= $total;

        $pairs_found = null;
        if ( $done ) {
            $pairs_found = self::build_pairs();
        }

        return [
            'processed'   => $processed,
            'total'       => $total,
            'next_offset' => $next_offset,
            'done'        => $done,
            'pairs_found' => $pairs_found,
        ];
    }

    /**
     * Compute & store the SimHash for one post (skips very short content).
     */
    public static function fingerprint_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $plain = '';
        if ( class_exists( 'Shynh_SEO_Content_Extractor' ) ) {
            $ctx   = Shynh_SEO_Content_Extractor::extract( $post_id, $post->post_content );
            $plain = (string) ( $ctx['plain_text'] ?? '' );
        }
        if ( '' === $plain ) {
            $plain = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        }

        $words = self::tokenize( $plain );
        if ( count( $words ) < 40 ) {
            delete_post_meta( $post_id, self::SIMHASH_META );
            update_post_meta( $post_id, self::TIME_META, time() );
            return;
        }

        $simhash = self::simhash( $words );
        update_post_meta( $post_id, self::SIMHASH_META, $simhash );
        update_post_meta( $post_id, self::TIME_META, time() );
    }

    private static function tokenize( $text ) {
        $text  = mb_strtolower( (string) $text, 'UTF-8' );
        $text  = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
        $words = preg_split( '/\s+/u', trim( $text ) );
        return array_values( array_filter( (array) $words ) );
    }

    /**
     * 64-bit SimHash over 3-word shingles, returned as a 64-char bit string.
     */
    private static function simhash( array $words ) {
        $v = array_fill( 0, 64, 0 );
        $n = count( $words );
        for ( $i = 0; $i < $n - 2; $i++ ) {
            $shingle = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
            $bits    = self::hash_bits( $shingle );
            for ( $b = 0; $b < 64; $b++ ) {
                $v[ $b ] += ( '1' === $bits[ $b ] ) ? 1 : -1;
            }
        }
        $out = '';
        for ( $b = 0; $b < 64; $b++ ) {
            $out .= ( $v[ $b ] > 0 ) ? '1' : '0';
        }
        return $out;
    }

    /** First 64 bits of md5 as a bit string. */
    private static function hash_bits( $s ) {
        $hex  = substr( md5( $s ), 0, 16 );
        $bits = '';
        for ( $i = 0; $i < 16; $i++ ) {
            $bits .= str_pad( base_convert( $hex[ $i ], 16, 2 ), 4, '0', STR_PAD_LEFT );
        }
        return $bits;
    }

    private static function similarity( $a, $b ) {
        if ( strlen( $a ) !== 64 || strlen( $b ) !== 64 ) {
            return 0;
        }
        $diff = 0;
        for ( $i = 0; $i < 64; $i++ ) {
            if ( $a[ $i ] !== $b[ $i ] ) {
                $diff++;
            }
        }
        return (int) round( ( 64 - $diff ) / 64 * 100 );
    }

    /**
     * Compare all fingerprinted posts pairwise (bounded) and store the report.
     */
    private static function build_pairs() {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, pm.meta_value AS simhash
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_status = 'publish' AND pm.meta_value != ''
             ORDER BY p.ID ASC
             LIMIT %d",
            self::SIMHASH_META,
            self::MAX_POSTS
        ) );

        // Preload lightweight signals for reason classification.
        $ids = wp_list_pluck( $rows, 'ID' );
        if ( function_exists( '_prime_post_caches' ) && ! empty( $ids ) ) {
            _prime_post_caches( $ids, false, true );
            update_meta_cache( 'post', $ids );
        }

        $meta = [];
        foreach ( $rows as $r ) {
            $meta[ $r->ID ] = [
                'type'    => get_post_type( $r->ID ),
                'keyword' => mb_strtolower( trim( (string) get_post_meta( $r->ID, '_shynh_seo_focus_keyword', true ) ), 'UTF-8' ),
                'title'   => mb_strtolower( trim( (string) get_post_meta( $r->ID, '_shynh_seo_title', true ) ), 'UTF-8' ),
                'desc'    => mb_strtolower( trim( (string) get_post_meta( $r->ID, '_shynh_seo_meta_desc', true ) ), 'UTF-8' ),
                'words'   => (int) get_post_meta( $r->ID, '_shynh_seo_heading_count', true ),
                'headc'   => count( (array) get_post_meta( $r->ID, '_shynh_seo_heading_summary', true ) ),
            ];
        }

        $n     = count( $rows );
        $pairs = [];
        for ( $i = 0; $i < $n; $i++ ) {
            for ( $j = $i + 1; $j < $n; $j++ ) {
                $sim = self::similarity( $rows[ $i ]->simhash, $rows[ $j ]->simhash );
                $a   = (int) $rows[ $i ]->ID;
                $b   = (int) $rows[ $j ]->ID;

                if ( class_exists( 'Shynh_SEO_Cannibalization' ) && Shynh_SEO_Cannibalization::is_cross_language_pair( $a, $b, 'post' ) ) {
                    continue;
                }

                $reasons = self::classify( $sim, $meta[ $a ], $meta[ $b ] );
                if ( empty( $reasons ) ) {
                    continue;
                }

                $pairs[] = [
                    'a'          => $a,
                    'b'          => $b,
                    'similarity' => $sim,
                    'reasons'    => $reasons,
                ];
            }
        }

        usort( $pairs, function ( $x, $y ) { return $y['similarity'] <=> $x['similarity']; } );
        $pairs = array_slice( $pairs, 0, self::MAX_PAIRS );

        update_option( self::REPORT_OPT, [
            'generated_at' => time(),
            'pairs'        => $pairs,
        ], false );

        return count( $pairs );
    }

    /**
     * Decide whether a pair is a duplicate and label WHY.
     *
     * @return array<int,array{code:string,label:string}>
     */
    private static function classify( $similarity, $a, $b ) {
        $reasons = [];

        $body_dup = $similarity >= self::MIN_SIMILARITY;
        if ( $body_dup ) {
            $reasons[] = [ 'code' => 'body_near_duplicate', 'label' => sprintf( 'Nội dung thân bài giống ~%d%%', $similarity ) ];
        }

        // Shared focus keyword.
        if ( '' !== $a['keyword'] && $a['keyword'] === $b['keyword'] ) {
            $reasons[] = [ 'code' => 'same_focus_keyword', 'label' => 'Trùng focus keyword' ];
        }
        // Shared SEO title.
        if ( '' !== $a['title'] && $a['title'] === $b['title'] ) {
            $reasons[] = [ 'code' => 'same_meta_title', 'label' => 'Trùng meta title' ];
        }
        // Shared meta description.
        if ( '' !== $a['desc'] && $a['desc'] === $b['desc'] ) {
            $reasons[] = [ 'code' => 'same_meta_desc', 'label' => 'Trùng meta description' ];
        }

        // Only classify further when there is body similarity or a shared meta signal.
        if ( empty( $reasons ) ) {
            return [];
        }

        // Template variant: same post type + similar heading structure + high similarity.
        if ( $body_dup && $a['type'] === $b['type'] && abs( $a['headc'] - $b['headc'] ) <= 1 && $similarity >= 90 ) {
            $reasons[] = [ 'code' => 'template_variant', 'label' => 'Nghi biến thể template / bản sao (PC-Mobile?)' ];
        }

        // Thin/boilerplate: near-identical but little heading structure.
        if ( $body_dup && $a['headc'] <= 1 && $b['headc'] <= 1 ) {
            $reasons[] = [ 'code' => 'thin_or_boilerplate', 'label' => 'Nội dung mỏng / boilerplate' ];
        }

        return $reasons;
    }

    /**
     * @return array<int,array> stored duplicate pairs
     */
    public static function get_report() {
        $report = get_option( self::REPORT_OPT, [] );
        return ( is_array( $report ) && ! empty( $report['pairs'] ) ) ? $report['pairs'] : [];
    }

    public static function last_run() {
        $report = get_option( self::REPORT_OPT, [] );
        return is_array( $report ) && isset( $report['generated_at'] ) ? (int) $report['generated_at'] : 0;
    }
}
