<?php
/**
 * Shynh SEO — Potential Keyword Expander.
 *
 * Runs on WP-Cron (daily by default) to grow a pool of potential/gap keywords
 * from the site's own content (headings, FAQ, focus keywords, service terms)
 * combined with intent modifiers, plus optional external autocomplete.
 *
 * All work is bounded per run to stay lightweight (anti-hang).
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Keyword_Expander {

    const OPTION_KEY = 'shynh_seo_keyword_suggestions';
    const CRON_HOOK  = 'shynh_seo_keyword_expansion';

    /** Vietnamese intent modifiers valuable for AEO/GEO question keywords. */
    const INTENT_MODIFIERS = [
        'giá', 'bảng giá', 'bao nhiêu tiền', 'chi phí', 'review', 'đánh giá',
        'có tốt không', 'có hại không', 'có đau không', 'có an toàn không',
        'ở đâu', 'địa chỉ', 'quy trình', 'bao lâu', 'sau bao lâu', 'kiêng gì',
        'trước và sau', 'giữ được bao lâu',
    ];

    /** Curated facet vocabulary for L2 supporting-keyword expansion. */
    const FACETS = [
        'method'   => [ 'Hifu', 'chỉ', 'laser', 'RF', 'meso', 'Exion', 'Thermage', 'Ultherapy', 'tiêm', 'cấy', 'công nghệ cao' ],
        'area'     => [ 'mặt', 'cằm', 'cổ', 'trán', 'má', 'bụng', 'đùi', 'cánh tay', 'vùng mắt', 'toàn thân' ],
        'benefit'  => [ 'không phẫu thuật', 'an toàn', 'không đau', 'nhanh', 'tự nhiên', 'trẻ hóa', 'không xâm lấn' ],
        'audience' => [ 'cho nam', 'cho nữ', 'sau sinh', 'tuổi 40', 'tuổi 50', 'da nhạy cảm' ],
    ];

    /** Comparison / question qualifiers for L4 micro long-tail. */
    const COMPARE_QUALIFIERS = [
        'có nên', 'loại nào tốt', 'cái nào tốt hơn', 'giữ được bao lâu',
        'bao lâu có hiệu quả', 'ở đâu tốt', 'có hiệu quả không',
    ];

    /** Caps per run to avoid heavy cron. */
    const MAX_SEEDS        = 60;
    const MAX_CONTENT_SCAN = 40;
    const MAX_STORE        = 600;
    const MAX_EXTERNAL     = 10;

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ __CLASS__, 'maybe_schedule' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
    }

    /**
     * Ensure the recurring event is scheduled with the configured frequency.
     */
    public static function maybe_schedule() {
        $settings  = Shynh_SEO_Core::get_settings();
        $frequency = isset( $settings['keyword_expansion_frequency'] ) ? $settings['keyword_expansion_frequency'] : 'daily';
        if ( ! in_array( $frequency, [ 'hourly', 'twicedaily', 'daily', 'weekly' ], true ) ) {
            $frequency = 'daily';
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    /**
     * Main expansion pass (bounded). Returns stats.
     */
    public static function run() {
        $settings = Shynh_SEO_Core::get_settings();
        $seeds    = self::collect_seeds();
        $covered  = array_map(
            function ( $k ) { return mb_strtolower( trim( $k ), 'UTF-8' ); },
            Shynh_SEO_Cannibalization::get_existing_keywords( 0, 'post' )
        );
        $covered_lookup = array_flip( $covered );

        $candidates = [];

        // 1. seed × intent modifier.
        foreach ( array_slice( $seeds, 0, self::MAX_SEEDS ) as $seed => $weight ) {
            foreach ( self::INTENT_MODIFIERS as $mod ) {
                self::add_candidate( $candidates, $seed . ' ' . $mod, $weight + 2, 'intent', $seed, $covered_lookup );
            }
        }

        // 2. Questions from FAQ + headings (bounded content scan).
        self::scan_content_questions( $candidates, $covered_lookup );

        // 3. Optional external autocomplete.
        if ( ! empty( $settings['keyword_expansion_external_enabled'] ) ) {
            self::add_external( $candidates, array_slice( array_keys( $seeds ), 0, self::MAX_EXTERNAL ), $covered_lookup );
        }

        // Merge with existing pool (preserve first_seen, refresh last_seen/score).
        $existing = self::get_all();
        $now      = time();
        $pool     = [];
        foreach ( $existing as $row ) {
            $pool[ $row['candidate'] ] = $row;
        }
        foreach ( $candidates as $key => $c ) {
            if ( isset( $pool[ $key ] ) ) {
                $pool[ $key ]['score']     = max( $pool[ $key ]['score'], $c['score'] );
                $pool[ $key ]['last_seen'] = $now;
                $pool[ $key ]['sources']   = array_values( array_unique( array_merge( $pool[ $key ]['sources'], $c['sources'] ) ) );
                $pool[ $key ]['covered']   = $c['covered'];
            } else {
                $pool[ $key ] = [
                    'candidate'  => $c['candidate'],
                    'score'      => $c['score'],
                    'sources'    => $c['sources'],
                    'seeds'      => $c['seeds'],
                    'covered'    => $c['covered'],
                    'first_seen' => $now,
                    'last_seen'  => $now,
                ];
            }
        }

        // Sort by score desc, cap.
        $pool = array_values( $pool );
        usort( $pool, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
        $pool = array_slice( $pool, 0, self::MAX_STORE );

        update_option( self::OPTION_KEY, $pool, false );
        update_option( 'shynh_seo_keyword_expansion_last_run', $now, false );

        return [ 'candidates' => count( $candidates ), 'stored' => count( $pool ), 'ran_at' => $now ];
    }

    /**
     * Seeds = focus keywords (weighted by usage) + service term names.
     *
     * @return array<string,int> seed => weight
     */
    private static function collect_seeds() {
        $seeds = [];

        foreach ( Shynh_SEO_Cannibalization::get_existing_keywords( 0, 'post' ) as $kw ) {
            $key = mb_strtolower( trim( $kw ), 'UTF-8' );
            if ( '' !== $key ) {
                $seeds[ $key ] = ( $seeds[ $key ] ?? 0 ) + 3;
            }
        }

        $terms = get_terms( [
            'taxonomy'   => Shynh_SEO_Core::get_public_content_taxonomies(),
            'hide_empty' => true,
            'number'     => self::MAX_SEEDS,
        ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $key = mb_strtolower( trim( $t->name ), 'UTF-8' );
                if ( '' !== $key ) {
                    $seeds[ $key ] = ( $seeds[ $key ] ?? 0 ) + (int) $t->count + 1;
                }
            }
        }

        arsort( $seeds );
        return $seeds;
    }

    private static function scan_content_questions( &$candidates, $covered_lookup ) {
        // FAQ questions from post meta.
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_shynh_seo_schema_faq' AND meta_value != '' LIMIT 100"
        );
        foreach ( $rows as $row ) {
            $decoded = json_decode( html_entity_decode( (string) $row->meta_value ), true );
            if ( ! is_array( $decoded ) ) {
                continue;
            }
            foreach ( $decoded as $item ) {
                if ( ! empty( $item['question'] ) ) {
                    self::add_candidate( $candidates, $item['question'], 4, 'faq', '', $covered_lookup );
                }
            }
        }

        // H2/H3 headings from a bounded set of recent posts.
        $posts = get_posts( [
            'post_type'      => Shynh_SEO_Core::get_public_content_post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_CONTENT_SCAN,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );
        foreach ( $posts as $post ) {
            if ( ! class_exists( 'Shynh_SEO_Content_Extractor' ) ) {
                break;
            }
            $ctx = Shynh_SEO_Content_Extractor::extract( $post->ID, $post->post_content );
            foreach ( (array) ( $ctx['headings'] ?? [] ) as $h ) {
                $level = (int) ( $h['level'] ?? 0 );
                $text  = trim( wp_strip_all_tags( $h['text'] ?? '' ) );
                if ( ( 2 === $level || 3 === $level ) && mb_strlen( $text, 'UTF-8' ) >= 8 && mb_strlen( $text, 'UTF-8' ) <= 80 ) {
                    self::add_candidate( $candidates, $text, 2, 'heading', '', $covered_lookup );
                }
            }
        }
    }

    private static function add_external( &$candidates, $seeds, $covered_lookup ) {
        foreach ( $seeds as $seed ) {
            $cache_key = 'shynh_seo_ac_' . md5( $seed );
            $sugg      = get_transient( $cache_key );
            if ( false === $sugg ) {
                $url  = 'https://suggestqueries.google.com/complete/search?client=firefox&hl=vi&q=' . rawurlencode( $seed );
                $resp = wp_remote_get( $url, [ 'timeout' => 8 ] );
                $sugg = [];
                if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
                    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
                    if ( isset( $data[1] ) && is_array( $data[1] ) ) {
                        $sugg = array_slice( $data[1], 0, 10 );
                    }
                }
                set_transient( $cache_key, $sugg, DAY_IN_SECONDS );
            }
            foreach ( (array) $sugg as $s ) {
                self::add_candidate( $candidates, $s, 5, 'external', $seed, $covered_lookup );
            }
        }
    }

    private static function add_candidate( &$candidates, $raw, $score, $source, $seed, $covered_lookup ) {
        $candidate = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $raw ) ) );
        $key       = mb_strtolower( $candidate, 'UTF-8' );
        if ( mb_strlen( $key, 'UTF-8' ) < 6 || mb_strlen( $key, 'UTF-8' ) > 90 ) {
            return;
        }

        $covered = isset( $covered_lookup[ $key ] );
        if ( isset( $candidates[ $key ] ) ) {
            $candidates[ $key ]['score']   += $score;
            $candidates[ $key ]['sources']  = array_values( array_unique( array_merge( $candidates[ $key ]['sources'], [ $source ] ) ) );
            if ( $seed ) {
                $candidates[ $key ]['seeds'] = array_values( array_unique( array_merge( $candidates[ $key ]['seeds'], [ $seed ] ) ) );
            }
            return;
        }
        $candidates[ $key ] = [
            'candidate' => $candidate,
            'score'     => $score,
            'sources'   => [ $source ],
            'seeds'     => $seed ? [ $seed ] : [],
            'covered'   => $covered,
        ];
    }

    /**
     * @return array<int,array>
     */
    public static function get_all() {
        $rows = get_option( self::OPTION_KEY, [] );
        return is_array( $rows ) ? $rows : [];
    }

    public static function last_run() {
        return (int) get_option( 'shynh_seo_keyword_expansion_last_run', 0 );
    }

    /* =====================================================================
     * Hierarchical (tiered) expansion for the Keyword Explorer
     * ===================================================================== */

    /**
     * Seeds a user can expand: existing focus keywords + service term names,
     * enriched with facets so the Explorer can offer a flexible filter UI.
     *
     * @return array<int,array{keyword:string,covered:bool,usage:int,source:string,cannibalized:bool,taxonomy:string,topic:string}>
     */
    public static function get_seeds() {
        $seeds = [];

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT pm.post_id, LOWER(TRIM(pm.meta_value)) kw
             FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key='_shynh_seo_focus_keyword' AND pm.meta_value!='' AND p.post_status='publish'
             LIMIT 300"
        );
        $keyword_groups = [];
        foreach ( $rows as $r ) {
            $kw = trim( (string) $r->kw );
            if ( '' === $kw ) {
                continue;
            }

            $language = class_exists( 'Shynh_SEO_Cannibalization' )
                ? Shynh_SEO_Cannibalization::get_object_language( (int) $r->post_id, 'post' )
                : '';
            $language = $language ?: '_unknown';

            if ( ! isset( $keyword_groups[ $kw ] ) ) {
                $keyword_groups[ $kw ] = [ 'total' => 0, 'languages' => [] ];
            }

            $keyword_groups[ $kw ]['total']++;
            $keyword_groups[ $kw ]['languages'][ $language ] = ( $keyword_groups[ $kw ]['languages'][ $language ] ?? 0 ) + 1;
        }

        uasort( $keyword_groups, function ( $a, $b ) {
            return (int) $b['total'] <=> (int) $a['total'];
        } );

        foreach ( $keyword_groups as $kw => $group ) {
            $max_language_usage = ! empty( $group['languages'] ) ? max( $group['languages'] ) : (int) $group['total'];
            $seeds[ $kw ] = [
                'keyword'      => $kw,
                'covered'      => true,
                'usage'        => (int) $group['total'],
                'source'       => 'keyword',
                'cannibalized' => $max_language_usage > 1,
                'taxonomy'     => '',
                'topic'        => '',
            ];
        }

        $taxonomies = Shynh_SEO_Core::get_public_content_taxonomies();
        $terms = get_terms( [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'number'     => 300,
        ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $key = mb_strtolower( trim( $t->name ), 'UTF-8' );
                if ( '' === $key ) {
                    continue;
                }
                $tax_obj    = get_taxonomy( $t->taxonomy );
                $tax_label  = $tax_obj ? ( $tax_obj->labels->singular_name ?? $t->taxonomy ) : $t->taxonomy;
                $parent     = $t->parent ? get_term( $t->parent, $t->taxonomy ) : null;
                $topic      = ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : $tax_label;

                if ( isset( $seeds[ $key ] ) ) {
                    // Focus keyword that is also a term name → tag its topic/taxonomy.
                    $seeds[ $key ]['taxonomy'] = $t->taxonomy;
                    $seeds[ $key ]['topic']    = $topic;
                    continue;
                }
                $seeds[ $key ] = [
                    'keyword'      => $t->name,
                    'covered'      => (int) $t->count > 0 ? false : false, // term itself has no focus-keyword post yet
                    'usage'        => (int) $t->count,
                    'source'       => 'service',
                    'cannibalized' => false,
                    'taxonomy'     => $t->taxonomy,
                    'topic'        => $topic,
                ];
            }
        }

        // Newest/strongest first by usage.
        $out = array_values( $seeds );
        usort( $out, function ( $a, $b ) { return $b['usage'] <=> $a['usage']; } );
        return $out;
    }

    /**
     * Expand a single seed keyword into a tiered tree (L1→L4) of nodes + edges.
     * Bounded to stay fast (anti-hang).
     *
     * @param string $seed
     * @param int    $depth      2..4
     * @param int    $max_nodes
     * @return array{seed:string,nodes:array,edges:array}
     */
    public static function expand_seed( $seed, $depth = 4, $max_nodes = 120 ) {
        $seed  = trim( preg_replace( '/\s+/u', ' ', (string) $seed ) );
        $depth = min( 4, max( 2, (int) $depth ) );
        if ( '' === $seed ) {
            return [ 'seed' => '', 'nodes' => [], 'edges' => [] ];
        }

        $covered_set = self::existing_keywords_set();
        $facets      = self::facets_for_seed( $seed );

        $nodes = [];
        $edges = [];
        $seen  = [];

        $add = function ( $label, $level, $parent_id, $facet, $intent ) use ( &$nodes, &$edges, &$seen, $covered_set ) {
            $label = trim( preg_replace( '/\s+/u', ' ', $label ) );
            $key   = mb_strtolower( $label, 'UTF-8' );
            $id    = 'kw:' . md5( $key );
            if ( ! isset( $seen[ $id ] ) ) {
                $covered = isset( $covered_set[ $key ] );
                $seen[ $id ] = true;
                $nodes[ $id ] = [
                    'id'          => $id,
                    'label'       => $label,
                    'level'       => $level,
                    'facet'       => $facet,
                    'intent'      => $intent,
                    'covered'     => $covered,
                    'opportunity' => self::opportunity( $intent, $covered, $facet ),
                ];
            }
            if ( $parent_id && $parent_id !== $id ) {
                $edges[] = [ 'source' => $parent_id, 'target' => $id ];
            }
            return $id;
        };

        // L1 seed.
        $root = $add( $seed, 1, '', 'seed', 'root' );

        // L2 supporting keywords (seed + facet term).
        $l2_ids = [];
        foreach ( $facets as $facet_type => $terms ) {
            foreach ( array_slice( $terms, 0, 4 ) as $term ) {
                if ( count( $nodes ) >= $max_nodes ) {
                    break 2;
                }
                $intent = ( 'benefit' === $facet_type ) ? 'informational' : 'commercial';
                $l2_ids[] = [ 'id' => $add( $seed . ' ' . $term, 2, $root, $facet_type, $intent ), 'label' => $seed . ' ' . $term ];
            }
        }

        if ( $depth >= 3 ) {
            foreach ( $l2_ids as $l2 ) {
                foreach ( array_slice( self::INTENT_MODIFIERS, 0, 4 ) as $mod ) {
                    if ( count( $nodes ) >= $max_nodes ) {
                        break 2;
                    }
                    $add( $l2['label'] . ' ' . $mod, 3, $l2['id'], 'intent', self::classify_intent( $mod ) );
                }
            }
        }

        if ( $depth >= 4 ) {
            foreach ( $l2_ids as $l2 ) {
                foreach ( array_slice( self::COMPARE_QUALIFIERS, 0, 3 ) as $q ) {
                    if ( count( $nodes ) >= $max_nodes ) {
                        break 2;
                    }
                    $add( $l2['label'] . ' ' . $q, 4, $l2['id'], 'compare', 'question' );
                }
            }
        }

        return [ 'seed' => $seed, 'nodes' => array_values( $nodes ), 'edges' => $edges ];
    }

    /**
     * Merge curated facets with facet-like terms actually used near the seed on
     * this site (grounding → relevance). Bounded scan.
     */
    private static function facets_for_seed( $seed ) {
        $facets  = self::FACETS;
        $learned = [];

        $posts = get_posts( [
            'post_type'      => Shynh_SEO_Core::get_public_content_post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            's'              => $seed,
        ] );
        $seed_l = mb_strtolower( $seed, 'UTF-8' );
        foreach ( $posts as $post ) {
            $words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( wp_strip_all_tags( $post->post_content ), 'UTF-8' ) );
            foreach ( (array) $words as $w ) {
                if ( mb_strlen( $w, 'UTF-8' ) >= 4 && false === mb_strpos( $seed_l, $w ) ) {
                    $learned[ $w ] = ( $learned[ $w ] ?? 0 ) + 1;
                }
            }
        }
        arsort( $learned );
        $top = array_slice( array_keys( $learned ), 0, 6 );
        if ( ! empty( $top ) ) {
            $facets['site'] = $top; // site-learned relevant modifiers
        }

        return $facets;
    }

    public static function classify_intent( $text ) {
        $t = mb_strtolower( $text, 'UTF-8' );
        if ( preg_match( '/(giá|bảng giá|bao nhiêu|chi phí|ở đâu|địa chỉ|đặt|booking)/u', $t ) ) {
            return 'transactional';
        }
        if ( preg_match( '/(review|đánh giá|tốt không|có nên|so với|loại nào)/u', $t ) ) {
            return 'commercial';
        }
        if ( preg_match( '/(quy trình|bao lâu|kiêng|cách|hiệu quả|trước và sau|có hại)/u', $t ) ) {
            return 'informational';
        }
        return 'informational';
    }

    private static function opportunity( $intent, $covered, $facet ) {
        $weights = [ 'transactional' => 4, 'commercial' => 3, 'question' => 2, 'informational' => 2, 'root' => 0 ];
        $score   = $weights[ $intent ] ?? 2;
        if ( ! $covered ) {
            $score += 3; // gap opportunity
        }
        if ( 'site' === $facet ) {
            $score += 1; // grounded in real site content
        }
        return $score;
    }

    private static $kw_set_cache = null;
    private static function existing_keywords_set() {
        if ( null !== self::$kw_set_cache ) {
            return self::$kw_set_cache;
        }
        $set = [];
        foreach ( Shynh_SEO_Cannibalization::get_existing_keywords( 0, 'post' ) as $kw ) {
            $set[ mb_strtolower( trim( $kw ), 'UTF-8' ) ] = true;
        }
        self::$kw_set_cache = $set;
        return $set;
    }
}
