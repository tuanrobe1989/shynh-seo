<?php
/**
 * Plugin Name: Shynh SEO
 * Plugin URI:  https://shynhgroup.com
 * Description: Lightweight SEO Optimization System — Sitemap, Meta Tags, Social Open Graph, SEO Score Analysis, Internal Linking & Keyword Report.
 * Version:     1.1.6
 * Author:      Shynh Group
 * Author URI:  https://shynhgroup.com
 * Text Domain: shynh-seo
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 */
define( 'SHYNH_SEO_VERSION', '1.1.6' );
define( 'SHYNH_SEO_FILE', __FILE__ );
define( 'SHYNH_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHYNH_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'SHYNH_SEO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the core class and initialize the plugin.
 */
require_once SHYNH_SEO_PATH . 'inc/class-seo-core.php';

/**
 * Initialize the plugin on `plugins_loaded`.
 */
function shynh_seo_init() {
    Shynh_SEO_Core::get_instance();
}
add_action( 'plugins_loaded', 'shynh_seo_init' );

/**
 * Migrate legacy title templates to the title-only format.
 */
function shynh_seo_maybe_migrate_title_format() {
    $migration_version = '1';

    if ( $migration_version === get_option( 'shynh_seo_title_format_migration' ) ) {
        return;
    }

    $settings = get_option( 'shynh_seo_settings', [] );
    global $wpdb;

    $landing_page = get_page_by_path( 'she-plastic-surgery-clinic-the-art-of-timeless-beauty' );
    $backup = [
        'created_at'             => current_time( 'mysql' ),
        'title_pattern'          => is_array( $settings ) && isset( $settings['title_pattern'] ) ? $settings['title_pattern'] : '',
        'term_titles'            => $wpdb->get_results(
            $wpdb->prepare(
                "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s",
                '_shynh_seo_title'
            ),
            ARRAY_A
        ),
        'landing_page_id'        => $landing_page ? $landing_page->ID : 0,
        'landing_page_seo_title' => $landing_page ? get_post_meta( $landing_page->ID, '_shynh_seo_title', true ) : '',
    ];
    update_option( 'shynh_seo_title_format_migration_backup', $backup, false );

    if ( is_array( $settings ) && isset( $settings['title_pattern'] ) ) {
        $legacy_patterns = [
            '%%title%% - %%sitename%%',
            '%%title%% %%sep%% %%sitename%%',
        ];

        if ( in_array( trim( $settings['title_pattern'] ), $legacy_patterns, true ) ) {
            $settings['title_pattern'] = '%%title%%';
            update_option( 'shynh_seo_settings', $settings );
        }
    }

    $legacy_term_patterns = [
        '%%term_title%% %%page%% %%sep%% %%sitename%%',
        '%%title%% %%page%% %%sep%% %%sitename%%',
    ];

    foreach ( $legacy_term_patterns as $legacy_pattern ) {
        $wpdb->update(
            $wpdb->termmeta,
            [ 'meta_value' => '%%term_title%% %%page%%' ],
            [
                'meta_key'   => '_shynh_seo_title',
                'meta_value' => $legacy_pattern,
            ],
            [ '%s' ],
            [ '%s', '%s' ]
        );
    }

    if ( $landing_page ) {
        $current_title = get_post_meta( $landing_page->ID, '_shynh_seo_title', true );
        if ( 'SHE Plastic Surgery Clinic – Thẩm Mỹ Hàn Quốc Chuẩn Quốc Tế' === $current_title ) {
            update_post_meta( $landing_page->ID, '_shynh_seo_title', 'SHE by SHYNH Plastic Surgery' );
        }
    }

    update_option( 'shynh_seo_title_format_migration', $migration_version, false );
}
add_action( 'plugins_loaded', 'shynh_seo_maybe_migrate_title_format', 20 );

/**
 * Activation hook — flush rewrite rules for sitemap endpoints.
 */
function shynh_seo_activate() {
    // Set default options on first activation.
    if ( false === get_option( 'shynh_seo_settings' ) ) {
        update_option( 'shynh_seo_settings', Shynh_SEO_Core::get_default_settings() );
    }
    if ( false === get_option( 'shynh_seo_rules' ) ) {
        update_option( 'shynh_seo_rules', Shynh_SEO_Core::get_default_rules() );
    }

    // API is disabled by default until an admin enables it and creates a key.
    if ( false === get_option( 'shynh_seo_api_settings' ) ) {
        update_option( 'shynh_seo_api_settings', [ 'enabled' => false ] );
    }

    // Flush rewrite rules so sitemap endpoints work.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'shynh_seo_activate' );

/**
 * Deactivation hook — cleanup rewrite rules + scheduled events.
 */
function shynh_seo_deactivate() {
    if ( class_exists( 'Shynh_SEO_Keyword_Expander' ) ) {
        Shynh_SEO_Keyword_Expander::unschedule();
    }
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'shynh_seo_deactivate' );
