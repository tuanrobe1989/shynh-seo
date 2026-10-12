<?php
/**
 * Shynh SEO — Anti-Cannibalization & Unique Content Engine.
 *
 * Prevents duplicate focus keywords and SEO titles across posts and taxonomies.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Cannibalization {

    /**
     * Get all active Focus Keywords used across posts or terms.
     *
     * @param int    $exclude_id  ID of current post/term to exclude.
     * @param string $object_type 'post' or 'term'.
     * @return array Array of normalized lowercase focus keywords.
     */
    public static function get_existing_keywords( $exclude_id = 0, $object_type = 'post', $same_language_only = true ) {
        global $wpdb;
        $keywords = [];
        $current_language = $same_language_only ? self::get_object_language( $exclude_id, $object_type ) : '';

        if ( 'term' === $object_type ) {
            $query = $wpdb->prepare(
                "SELECT term_id AS object_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = '_shynh_seo_focus_keyword' AND term_id != %d AND meta_value != ''",
                $exclude_id
            );
            $results = $wpdb->get_results( $query );
        } else {
            $query = $wpdb->prepare(
                "SELECT post_id AS object_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_shynh_seo_focus_keyword' AND post_id != %d AND meta_value != ''",
                $exclude_id
            );
            $results = $wpdb->get_results( $query );
        }

        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                if ( $current_language && ! self::is_same_language_or_unknown( $current_language, self::get_object_language( (int) $row->object_id, $object_type ) ) ) {
                    continue;
                }

                $clean = mb_strtolower( trim( (string) $row->meta_value ), 'UTF-8' );
                if ( '' !== $clean ) {
                    $keywords[] = $clean;
                }
            }
        }

        return array_values( array_unique( $keywords ) );
    }

    /**
     * Get all custom SEO Titles used across posts or terms.
     *
     * @param int    $exclude_id  ID of current post/term to exclude.
     * @param string $object_type 'post' or 'term'.
     * @return array Array of lowercase SEO titles.
     */
    public static function get_existing_titles( $exclude_id = 0, $object_type = 'post', $same_language_only = true ) {
        global $wpdb;
        $titles = [];
        $current_language = $same_language_only ? self::get_object_language( $exclude_id, $object_type ) : '';

        if ( 'term' === $object_type ) {
            $query = $wpdb->prepare(
                "SELECT term_id AS object_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = '_shynh_seo_title' AND term_id != %d AND meta_value != ''",
                $exclude_id
            );
            $results = $wpdb->get_results( $query );
        } else {
            $query = $wpdb->prepare(
                "SELECT post_id AS object_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_shynh_seo_title' AND post_id != %d AND meta_value != ''",
                $exclude_id
            );
            $results = $wpdb->get_results( $query );
        }

        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                if ( $current_language && ! self::is_same_language_or_unknown( $current_language, self::get_object_language( (int) $row->object_id, $object_type ) ) ) {
                    continue;
                }

                $clean = mb_strtolower( trim( (string) $row->meta_value ), 'UTF-8' );
                if ( '' !== $clean ) {
                    $titles[] = $clean;
                }
            }
        }

        return array_values( array_unique( $titles ) );
    }

    /**
     * Check if a focus keyword is unique on the site.
     *
     * @param string $keyword     Keyword to check.
     * @param int    $exclude_id  Current object ID.
     * @param string $object_type 'post' or 'term'.
     * @return bool True if unique, false if already taken.
     */
    public static function is_keyword_unique( $keyword, $exclude_id = 0, $object_type = 'post' ) {
        $clean = mb_strtolower( trim( $keyword ), 'UTF-8' );
        if ( empty( $clean ) ) {
            return true;
        }

        $existing = self::get_existing_keywords( $exclude_id, $object_type );
        return ! in_array( $clean, $existing, true );
    }

    public static function get_object_language( $object_id, $object_type = 'post' ) {
        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return '';
        }

        if ( 'term' === $object_type ) {
            return function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( $object_id ) : '';
        }

        return function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $object_id ) : '';
    }

    public static function is_same_language_or_unknown( $current_language, $candidate_language ) {
        return '' === $current_language || '' === $candidate_language || $current_language === $candidate_language;
    }

    public static function is_cross_language_pair( $a_id, $b_id, $object_type = 'post' ) {
        $a_language = self::get_object_language( $a_id, $object_type );
        $b_language = self::get_object_language( $b_id, $object_type );

        return '' !== $a_language && '' !== $b_language && $a_language !== $b_language;
    }

    public static function count_keyword_collisions( $keyword, $exclude_id = 0, $object_type = 'post' ) {
        $keyword = trim( (string) $keyword );
        if ( '' === $keyword ) {
            return 0;
        }

        global $wpdb;
        $current_language = self::get_object_language( $exclude_id, $object_type );
        $count            = 0;

        $post_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id AS object_id FROM {$wpdb->postmeta} WHERE meta_key = '_shynh_seo_focus_keyword' AND meta_value = %s AND post_id != %d",
            $keyword,
            'post' === $object_type ? absint( $exclude_id ) : 0
        ) );

        foreach ( $post_rows as $row ) {
            if ( ! $current_language || self::is_same_language_or_unknown( $current_language, self::get_object_language( (int) $row->object_id, 'post' ) ) ) {
                $count++;
            }
        }

        $term_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT term_id AS object_id FROM {$wpdb->termmeta} WHERE meta_key = '_shynh_seo_focus_keyword' AND meta_value = %s AND term_id != %d",
            $keyword,
            'term' === $object_type ? absint( $exclude_id ) : 0
        ) );

        foreach ( $term_rows as $row ) {
            if ( ! $current_language || self::is_same_language_or_unknown( $current_language, self::get_object_language( (int) $row->object_id, 'term' ) ) ) {
                $count++;
            }
        }

        return $count;
    }
}
