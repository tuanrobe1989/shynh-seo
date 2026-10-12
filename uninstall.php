<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Shynh_SEO
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete options
delete_option( 'shynh_seo_settings' );
delete_option( 'shynh_seo_rules' );

// 2. Delete post meta
global $wpdb;

$meta_keys = [
    '_shynh_seo_focus_keyword',
    '_shynh_seo_title',
    '_shynh_seo_meta_desc',
    '_shynh_seo_fb_title',
    '_shynh_seo_fb_desc',
    '_shynh_seo_fb_image',
    '_shynh_seo_tw_title',
    '_shynh_seo_tw_desc',
    '_shynh_seo_tw_image',
    '_shynh_seo_canonical',
    '_shynh_seo_robots_index',
    '_shynh_seo_robots_follow',
    '_shynh_seo_robots_noarchive',
    '_shynh_seo_robots_nosnippet',
    '_shynh_seo_robots_noimageindex',
    '_shynh_seo_robots_max_snippet',
    '_shynh_seo_robots_max_image_preview',
    '_shynh_seo_robots_max_video_preview',
    '_shynh_seo_breadcrumb_title',
    '_shynh_seo_cornerstone',
    '_shynh_seo_primary_category',
    '_shynh_seo_score',
    '_shynh_seo_internal_link_posts',
    '_shynh_seo_internal_links',
    '_shynh_seo_manual_related_posts',
];

foreach ( $meta_keys as $key ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s", $key ) );
}

// 3. Clear rewrite rules
flush_rewrite_rules();
