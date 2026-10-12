<?php
/**
 * Shynh SEO — Core Loader.
 *
 * Singleton class that bootstraps every module of the plugin,
 * registers the top-level admin menu, and provides shared helpers.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Core {

    /** @var self|null Singleton instance. */
    private static $instance = null;

    /**
     * Get or create the singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — load modules and register hooks.
     */
    private function __construct() {
        $this->load_modules();
        $this->init_modules();
        $this->register_hooks();
    }

    /**
     * Initialize singleton instances.
     */
    private function init_modules() {
        if ( class_exists( 'Shynh_SEO_Metabox' ) ) Shynh_SEO_Metabox::get_instance();
        if ( class_exists( 'Shynh_SEO_Taxonomy_Meta' ) ) Shynh_SEO_Taxonomy_Meta::get_instance();
        if ( class_exists( 'Shynh_SEO_Analysis' ) ) Shynh_SEO_Analysis::get_instance();
        if ( class_exists( 'Shynh_SEO_Frontend' ) ) Shynh_SEO_Frontend::get_instance();
        if ( class_exists( 'Shynh_SEO_Schema' ) ) Shynh_SEO_Schema::get_instance();
        if ( class_exists( 'Shynh_SEO_Sitemap' ) ) Shynh_SEO_Sitemap::get_instance();
        if ( class_exists( 'Shynh_SEO_Robots_Txt' ) ) Shynh_SEO_Robots_Txt::get_instance();
        if ( class_exists( 'Shynh_SEO_Internal_Linking' ) ) Shynh_SEO_Internal_Linking::get_instance();
        if ( class_exists( 'Shynh_SEO_Redirects' ) ) Shynh_SEO_Redirects::init();
        if ( class_exists( 'Shynh_SEO_Admin_Columns' ) ) Shynh_SEO_Admin_Columns::init();
        if ( class_exists( 'Shynh_SEO_AI_English_Sync' ) ) Shynh_SEO_AI_English_Sync::get_instance();
        if ( class_exists( 'Shynh_SEO_AI_Assistant' ) ) Shynh_SEO_AI_Assistant::get_instance();
        if ( class_exists( 'Shynh_SEO_Knowledge_Vault' ) ) Shynh_SEO_Knowledge_Vault::get_instance();
        if ( class_exists( 'Shynh_SEO_API' ) ) Shynh_SEO_API::get_instance();
        if ( class_exists( 'Shynh_SEO_API_Admin' ) ) Shynh_SEO_API_Admin::get_instance();
        if ( class_exists( 'Shynh_SEO_Keyword_Expander' ) ) Shynh_SEO_Keyword_Expander::get_instance();
        if ( class_exists( 'Shynh_SEO_Keyword_Map' ) ) Shynh_SEO_Keyword_Map::get_instance();
        if ( class_exists( 'Shynh_SEO_Keyword_Explorer' ) ) Shynh_SEO_Keyword_Explorer::get_instance();
    }

    /**
     * Require every module file.
     */
    private function load_modules() {
        $modules = [
            'inc/class-seo-knowledge-vault.php',
            'inc/class-seo-settings.php',
            'inc/class-seo-rules.php',
            'inc/class-seo-content-extractor.php',
            'inc/class-seo-metabox.php',
            'inc/class-seo-taxonomy-meta.php',
            'inc/class-seo-analysis.php',
            'inc/class-seo-frontend.php',
            'inc/class-seo-schema.php',
            'inc/class-seo-sitemap.php',
            'inc/class-seo-robots-txt.php',
            'inc/class-seo-internal-linking.php',
            'inc/class-seo-redirects.php',
            'inc/class-seo-admin-columns.php',
            'inc/class-seo-report.php',
            'inc/class-seo-cannibalization.php',
            'inc/class-seo-frontend-fetcher.php',
            'inc/class-seo-ai-english-sync.php',
            'inc/class-seo-ai-assistant.php',
            'inc/class-seo-api-keys.php',
            'inc/class-seo-api-openapi.php',
            'inc/class-seo-api.php',
            'inc/class-seo-api-admin.php',
            'inc/class-seo-keyword-expander.php',
            'inc/class-seo-keyword-map.php',
            'inc/class-seo-keyword-explorer.php',
            'inc/class-seo-duplicate-detector.php',
        ];

        foreach ( $modules as $module ) {
            $path = SHYNH_SEO_PATH . $module;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }

    /**
     * Register shared hooks.
     */
    private function register_hooks() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'init', [ __CLASS__, 'register_media_rewrite_rules' ] );
        add_filter( 'post_link_category', [ __CLASS__, 'filter_post_link_category' ], 10, 3 );
        add_filter( 'media_row_actions', [ __CLASS__, 'filter_media_row_actions' ], 10, 2 );
        add_filter( 'attachment_link', [ __CLASS__, 'filter_attachment_link' ], 10, 2 );
        add_filter( 'pre_option_wp_attachment_pages_enabled', [ __CLASS__, 'maybe_enable_attachment_pages' ] );
        add_filter( 'redirect_canonical', [ __CLASS__, 'disable_media_canonical_redirect' ], 10, 2 );
    }

    public static function register_media_rewrite_rules() {
        add_rewrite_rule(
            '^media/([^/]+)/?$',
            'index.php?attachment=$matches[1]',
            'top'
        );

        if ( '1' !== get_option( 'shynh_seo_media_rewrite_flushed' ) ) {
            flush_rewrite_rules( false );
            update_option( 'shynh_seo_media_rewrite_flushed', '1', false );
        }
    }

    public static function filter_attachment_link( $link, $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post || 'attachment' !== $post->post_type || empty( $post->post_name ) ) {
            return $link;
        }

        $settings = self::get_settings();
        $request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path             = wp_parse_url( $request_uri, PHP_URL_PATH );
        $is_media_request = is_string( $path ) && preg_match( '#^/media/[^/]+/?$#', $path );

        if ( empty( $settings['enable_media_seo_pages'] ) && ! $is_media_request ) {
            return $link;
        }

        return home_url( user_trailingslashit( 'media/' . $post->post_name ) );
    }

    public static function maybe_enable_attachment_pages( $pre ) {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path        = wp_parse_url( $request_uri, PHP_URL_PATH );

        if ( is_string( $path ) && preg_match( '#^/media/[^/]+/?$#', $path ) ) {
            return '1';
        }

        $settings = self::get_settings();
        if ( ! empty( $settings['enable_media_seo_pages'] ) ) {
            return '1';
        }

        return $pre;
    }

    public static function disable_media_canonical_redirect( $redirect_url, $requested_url ) {
        $path = wp_parse_url( $requested_url, PHP_URL_PATH );

        if ( is_string( $path ) && preg_match( '#^/media/[^/]+/?$#', $path ) ) {
            return false;
        }

        return $redirect_url;
    }

    public static function filter_media_row_actions( $actions, $post ) {
        $settings = self::get_settings();
        if ( ! empty( $settings['enable_media_seo_pages'] ) ) {
            $permalink = get_attachment_link( $post->ID );
            if ( isset( $actions['view'] ) ) {
                $actions['view'] = sprintf(
                    '<a href="%s" aria-label="%s" rel="bookmark">%s</a>',
                    esc_url( $permalink ),
                    /* translators: %s: Attachment title. */
                    esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $post->post_title ) ),
                    __( 'View' )
                );
            }
        }
        return $actions;
    }

    public static function get_primary_taxonomy( $post_type ) {
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        $fallback   = '';

        foreach ( $taxonomies as $taxonomy ) {
            if ( empty( $taxonomy->hierarchical ) ) {
                continue;
            }

            if ( 'category' === $taxonomy->name ) {
                return $taxonomy->name;
            }

            if ( '' === $fallback ) {
                $fallback = $taxonomy->name;
            }
        }

        return $fallback;
    }

    public static function get_primary_term( $post_id, $taxonomy = '' ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        if ( '' === $taxonomy ) {
            $taxonomy = self::get_primary_taxonomy( $post->post_type );
        }

        if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return null;
        }

        $primary_term_id = absint( get_post_meta( $post_id, '_shynh_seo_primary_category', true ) );
        if ( $primary_term_id ) {
            $term = get_term( $primary_term_id, $taxonomy );
            if ( $term && ! is_wp_error( $term ) && has_term( $term->term_id, $taxonomy, $post_id ) ) {
                return $term;
            }
        }

        $terms = wp_get_post_terms( $post_id, $taxonomy );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return null;
        }

        $deepest_term = $terms[0];
        $max_depth    = -1;

        foreach ( $terms as $term ) {
            $depth = count( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
            if ( $depth > $max_depth ) {
                $max_depth    = $depth;
                $deepest_term = $term;
            }
        }

        return $deepest_term;
    }

    public static function filter_post_link_category( $category, $categories, $post ) {
        if ( ! $post || empty( $post->ID ) ) {
            return $category;
        }

        $primary_term = self::get_primary_term( $post->ID, 'category' );
        return $primary_term ? $primary_term : $category;
    }

    /* ───────────────────────────────────────────────
     *  Admin Menu
     * ─────────────────────────────────────────────── */

    /**
     * Register the top-level "Shynh SEO" menu and sub-menus.
     */
    public function register_admin_menu() {
        // Top-level menu.
        add_menu_page(
            __( 'Shynh SEO', 'shynh-seo' ),
            __( 'Shynh SEO', 'shynh-seo' ),
            'manage_options',
            'shynh-seo',
            [ $this, 'render_dashboard_page' ],
            'dashicons-chart-area',
            80
        );

        // Sub-menu: Dashboard (same slug as parent to avoid duplicate).
        add_submenu_page(
            'shynh-seo',
            __( 'Dashboard', 'shynh-seo' ),
            __( 'Dashboard', 'shynh-seo' ),
            'manage_options',
            'shynh-seo',
            [ $this, 'render_dashboard_page' ]
        );

        // Sub-menu: Settings.
        add_submenu_page(
            'shynh-seo',
            __( 'Cài Đặt', 'shynh-seo' ),
            __( 'Cài Đặt', 'shynh-seo' ),
            'manage_options',
            'shynh-seo-settings',
            [ Shynh_SEO_Settings::get_instance(), 'render_page' ]
        );

        // Sub-menu: SEO Rules.
        add_submenu_page(
            'shynh-seo',
            __( 'SEO Rules', 'shynh-seo' ),
            __( 'SEO Rules', 'shynh-seo' ),
            'manage_options',
            'shynh-seo-rules',
            [ Shynh_SEO_Rules::get_instance(), 'render_page' ]
        );

        // Sub-menu: SEO Report.
        add_submenu_page(
            'shynh-seo',
            __( 'Báo Cáo SEO', 'shynh-seo' ),
            __( 'Báo Cáo SEO', 'shynh-seo' ),
            'manage_options',
            'shynh-seo-report',
            [ Shynh_SEO_Report::get_instance(), 'render_page' ]
        );
    }

    /**
     * Render the main Dashboard page.
     */
    public function render_dashboard_page() {
        $total_posts = wp_count_posts( 'post' );
        $total_pages = wp_count_posts( 'page' );
        $published   = ( $total_posts->publish ?? 0 ) + ( $total_pages->publish ?? 0 );

        // Count posts with SEO data.
        global $wpdb;
        $with_keyword = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_shynh_seo_focus_keyword' AND meta_value != ''"
        );
        $without_keyword = $published - $with_keyword;
        ?>
        <div class="wrap shynh-seo-wrap">
            <h1 class="shynh-seo-page-title">
                <span class="dashicons dashicons-chart-area"></span>
                <?php esc_html_e( 'Shynh SEO — Dashboard', 'shynh-seo' ); ?>
            </h1>

            <div class="shynh-seo-grid">
                <!-- Card: Total Published -->
                <div class="shynh-seo-stat-card">
                    <div class="shynh-seo-stat-icon" style="background: var(--shynh-dark);">
                        <span class="dashicons dashicons-admin-post"></span>
                    </div>
                    <div class="shynh-seo-stat-info">
                        <div class="shynh-seo-stat-num"><?php echo esc_html( $published ); ?></div>
                        <div class="shynh-seo-stat-label"><?php esc_html_e( 'Bài viết đã xuất bản', 'shynh-seo' ); ?></div>
                    </div>
                </div>

                <!-- Card: With Keyword -->
                <div class="shynh-seo-stat-card">
                    <div class="shynh-seo-stat-icon" style="background: var(--shynh-primary);">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="shynh-seo-stat-info">
                        <div class="shynh-seo-stat-num"><?php echo esc_html( $with_keyword ); ?></div>
                        <div class="shynh-seo-stat-label"><?php esc_html_e( 'Đã có Focus Keyword', 'shynh-seo' ); ?></div>
                    </div>
                </div>

                <!-- Card: Without Keyword -->
                <div class="shynh-seo-stat-card">
                    <div class="shynh-seo-stat-icon" style="background: #ef4444;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="shynh-seo-stat-info">
                        <div class="shynh-seo-stat-num"><?php echo esc_html( max( 0, $without_keyword ) ); ?></div>
                        <div class="shynh-seo-stat-label"><?php esc_html_e( 'Chưa có Focus Keyword', 'shynh-seo' ); ?></div>
                    </div>
                </div>

                <!-- Card: Sitemap -->
                <div class="shynh-seo-stat-card">
                    <div class="shynh-seo-stat-icon" style="background: #64748b;">
                        <span class="dashicons dashicons-networking"></span>
                    </div>
                    <div class="shynh-seo-stat-info">
                        <div class="shynh-seo-stat-num" style="font-size: 24px; margin-bottom: 8px;">
                            <a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" style="text-decoration: none; color: inherit;">
                                <?php esc_html_e( 'Xem Sitemap', 'shynh-seo' ); ?> ↗
                            </a>
                        </div>
                        <div class="shynh-seo-stat-label"><?php esc_html_e( 'Sitemap XML tự động', 'shynh-seo' ); ?></div>
                    </div>
                </div>
            </div>

            <div class="shynh-seo-card" style="margin-top: 32px;">
                <h4><?php esc_html_e( 'Liên kết nhanh (Quick Links)', 'shynh-seo' ); ?></h4>
                <div style="display: flex; gap: 16px; margin-top: 20px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=shynh-seo-settings' ) ); ?>" class="button shynh-seo-btn shynh-seo-btn--secondary"><?php esc_html_e( 'Cài đặt tổng', 'shynh-seo' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=shynh-seo-rules' ) ); ?>" class="button shynh-seo-btn shynh-seo-btn--secondary"><?php esc_html_e( 'Quản lý SEO Rules', 'shynh-seo' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=shynh-seo-report' ) ); ?>" class="button shynh-seo-btn shynh-seo-btn--secondary"><?php esc_html_e( 'Báo cáo SEO', 'shynh-seo' ); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /* ───────────────────────────────────────────────
     *  Admin Assets
     * ─────────────────────────────────────────────── */

    /**
     * Enqueue admin CSS and JS on plugin pages and post editors.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Admin CSS on all Shynh SEO pages.
        $is_seo_page = 'toplevel_page_shynh-seo' === $hook_suffix
            || 0 === strpos( (string) $hook_suffix, 'shynh-seo_page_' );
        $is_editor   = in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true );
        $is_term_editor = in_array( $hook_suffix, [ 'term.php', 'edit-tags.php' ], true );
        $admin_css_path = SHYNH_SEO_PATH . 'assets/css/admin.css';
        $admin_css_version = file_exists( $admin_css_path )
            ? SHYNH_SEO_VERSION . '-' . filemtime( $admin_css_path )
            : SHYNH_SEO_VERSION;
        $analysis_js_path = SHYNH_SEO_PATH . 'assets/js/seo-analysis.js';
        $internal_linking_js_path = SHYNH_SEO_PATH . 'assets/js/internal-linking.js';

        if ( $is_seo_page || $is_editor || $is_term_editor ) {
            wp_enqueue_media();
            wp_enqueue_style(
                'shynh-seo-admin',
                SHYNH_SEO_URL . 'assets/css/admin.css',
                [],
                $admin_css_version
            );
        }

        // Editor-specific JS for SEO analysis.
        if ( $is_editor || $is_term_editor ) {
            wp_enqueue_script(
                'shynh-seo-analysis',
                SHYNH_SEO_URL . 'assets/js/seo-analysis.js',
                [ 'jquery', 'jquery-ui-sortable', 'wp-data', 'wp-blocks', 'wp-editor' ],
                file_exists( $analysis_js_path ) ? filemtime( $analysis_js_path ) : SHYNH_SEO_VERSION,
                true
            );

            if ( $is_editor ) {
                wp_enqueue_script(
                    'shynh-seo-internal-linking',
                    SHYNH_SEO_URL . 'assets/js/internal-linking.js',
                    [ 'jquery' ],
                    file_exists( $internal_linking_js_path ) ? filemtime( $internal_linking_js_path ) : SHYNH_SEO_VERSION,
                    true
                );
            }

            $settings = get_option( 'shynh_seo_settings', self::get_default_settings() );
            $sep = $settings['title_separator'];
            $snippetVars = [];

            if ( $is_editor ) {
                $post_id = get_the_ID();
                $post_obj = get_post( $post_id );

                $cat_name = '';
                $primary_term = self::get_primary_term( $post_id );
                if ( $primary_term ) {
                    $cat_name = $primary_term->name;
                }

                $snippetVars = [
                    '%%title%%'    => get_the_title( $post_id ),
                    '%%sitename%%' => get_bloginfo( 'name' ),
                    '%%sitedesc%%' => get_bloginfo( 'description' ),
                    '%%sep%%'      => $sep,
                    '%%page%%'     => '',
                    '%%excerpt%%'  => has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( wp_strip_all_tags( $post_obj->post_content ?? '' ), 30, '...' ),
                    '%%date%%'     => get_the_date( '', $post_id ),
                    '%%modified%%' => get_the_modified_date( '', $post_id ),
                    '%%author%%'   => get_the_author_meta( 'display_name', $post_obj->post_author ?? 0 ),
                    '%%category%%' => $cat_name,
                ];
            } else {
                // Term Editor
                $term_id = isset( $_GET['tag_ID'] ) ? (int) $_GET['tag_ID'] : 0;
                $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';
                $term = get_term( $term_id, $taxonomy );

                $snippetVars = [
                    '%%title%%'    => ( $term && ! is_wp_error( $term ) ) ? $term->name : '',
                    '%%sitename%%' => get_bloginfo( 'name' ),
                    '%%sitedesc%%' => get_bloginfo( 'description' ),
                    '%%sep%%'      => $sep,
                    '%%excerpt%%'  => ( $term && ! is_wp_error( $term ) ) ? wp_strip_all_tags( $term->description ) : '',
                    '%%page%%'     => '',
                ];
            }

            // Pass rules and data to JS.
            $rules = get_option( 'shynh_seo_rules', self::get_default_rules() );
            $is_vi_admin = 0 === strpos( strtolower( get_user_locale() ), 'vi' );
            wp_localize_script( 'shynh-seo-analysis', 'shynhSeoData', [
                'rules'       => $rules,
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'shynh_seo_nonce' ),
                'postId'      => ( $is_editor && ! $is_term_editor ) ? get_the_ID() : 0,
                'termId'      => $is_term_editor ? ( isset( $_GET['tag_ID'] ) ? (int) $_GET['tag_ID'] : ( isset( $_POST['tag_ID'] ) ? (int) $_POST['tag_ID'] : 0 ) ) : 0,
                'taxonomy'    => $is_term_editor ? ( isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ( isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '' ) ) : '',
                'isTerm'      => (bool) $is_term_editor,
                'snippetVars' => $snippetVars,
                'i18n'        => [
                    'good'        => __( 'Tốt', 'shynh-seo' ),
                    'improvement' => __( 'Cần cải thiện', 'shynh-seo' ),
                    'bad'         => __( 'Chưa đạt', 'shynh-seo' ),
                ],
            ] );

            $analysis_i18n = [
                'good'                => $is_vi_admin ? 'Tốt' : 'Good',
                'improvement'         => $is_vi_admin ? 'Cần cải thiện' : 'Needs improvement',
                'bad'                 => $is_vi_admin ? 'Chưa đạt' : 'Needs work',
                'geoAeoScore'         => $is_vi_admin ? 'Điểm GEO/AEO' : 'GEO/AEO Score',
                'aiSearchOverall'     => $is_vi_admin ? 'Tổng điểm AI Search' : 'AI Search Overall',
                'analysisUnavailable' => $is_vi_admin ? 'Không thể phân tích SEO.' : 'Cannot analyze SEO.',
                'analysisError'       => $is_vi_admin ? 'Lỗi khi phân tích SEO.' : 'SEO analysis error.',
                'analyzing'           => $is_vi_admin ? 'Đang phân tích...' : 'Analyzing...',
                'defaultTitle'        => $is_vi_admin ? 'Tiêu đề bài viết' : 'Post title',
                'defaultDescription'  => $is_vi_admin ? 'Vui lòng nhập meta description.' : 'Please add a meta description.',
            ];

            wp_add_inline_script(
                'shynh-seo-analysis',
                'window.shynhSeoData = window.shynhSeoData || {}; window.shynhSeoData.i18n = Object.assign({}, window.shynhSeoData.i18n || {}, ' . wp_json_encode( $analysis_i18n, JSON_UNESCAPED_UNICODE ) . ');',
                'after'
            );

            wp_localize_script( 'shynh-seo-internal-linking', 'shynhSeoLinking', [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'shynh_seo_nonce' ),
                'postId'         => get_the_ID(),
                'relatedContext' => class_exists( 'Shynh_SEO_Internal_Linking' ) ? Shynh_SEO_Internal_Linking::get_related_context( get_the_ID() ) : [],
            ] );
        }
    }

    /* ───────────────────────────────────────────────
     *  Default Options
     * ─────────────────────────────────────────────── */

    /**
     * Return the default plugin settings.
     *
     * @return array
     */
    public static function get_default_robots_txt_lines( $include_sitemap = true ) {
        $lines = [
            '# Shynh SEO default robots.txt',
            '# Keep public assets crawlable so search engines can render pages correctly.',
            'User-agent: *',
            'Disallow: /wp-admin/',
            'Allow: /wp-admin/admin-ajax.php',
            '',
            '# Block low-value internal search URLs.',
            'Disallow: /?s=',
            'Disallow: /search/',
            'Disallow: /*?s=*',
            'Disallow: /*&s=*',
            '',
            '# Block technical WordPress endpoints that should not be indexed.',
            'Disallow: /wp-login.php',
            'Disallow: /xmlrpc.php',
            'Disallow: /?rest_route=',
            'Disallow: /wp-json/oembed/',
            '',
            '# Block preview, reply, trackback, embed, and feed duplicates.',
            'Disallow: /*?preview=true',
            'Disallow: /*?preview_id=',
            'Disallow: /*?preview_nonce=',
            'Disallow: /*?replytocom=',
            'Disallow: /*?embed=true',
            'Disallow: /trackback/',
            'Disallow: /*/trackback/',
            'Disallow: /feed/',
            'Disallow: /*/feed/',
            'Disallow: /comments/feed/',
        ];

        if ( $include_sitemap ) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . home_url( '/sitemap.xml' );
        }

        return $lines;
    }

    public static function get_default_robots_txt_template() {
        return implode( "\n", self::get_default_robots_txt_lines() ) . "\n";
    }

    public static function get_default_settings() {
        return [
            // General.
            'title_pattern'        => '%%title%%',
            'title_separator'      => '|',
            'default_meta_desc'    => '',
            'show_seo_columns'     => true,
            'seo_post_types'       => [ 'post', 'page' ],
            'related_post_types'   => [ 'post', 'page' ],

            // Sitemap.
            'sitemap_post_types'                 => [ 'post', 'page' ],
            'sitemap_taxonomies'                 => [ 'category' ],
            'sitemap_per_page'                   => 1000,
            'sitemap_auto_include_post_types'    => true,
            'sitemap_auto_include_taxonomies'    => true,
            'sitemap_default_post_type_profile'  => 'auto',
            'sitemap_default_taxonomy_profile'   => 'dynamic',
            'sitemap_post_type_profiles'         => [],
            'sitemap_taxonomy_profiles'          => [],
            'sitemap_excluded_post_types'        => [],
            'sitemap_excluded_taxonomies'        => [],

            // Social defaults.
            'default_og_image'     => '',
            'default_social_desc'  => '',
            'facebook_app_id'      => '',
            'twitter_card_type'    => 'summary_large_image',

            // Social profiles.
            'social_facebook'      => '',
            'social_instagram'     => '',
            'social_tiktok'        => '',
            'social_youtube'       => '',
            'social_twitter'       => '',

            // Robots global.
            'sitewide_noindex'         => false,
            'archive_noindex'          => false,
            'search_noindex'           => true,
            'not_found_noindex'        => true,
            'attachment_noindex'       => true,
            'enable_media_seo_pages'   => false, // Media SEO feature toggle
            'media_in_sitemap'         => false,
            'paginated_noindex'        => true,
            'robots_noarchive'         => false,
            'robots_nosnippet'         => false,
            'robots_noimageindex'      => false,
            'robots_max_snippet'       => '',
            'robots_max_image_preview' => 'large',
            'robots_max_video_preview' => '',
            'robots_txt_enabled'       => true,
            'robots_txt_mode'          => 'auto',
            'robots_txt_custom_rules'  => '',
            'robots_txt_manual_content' => self::get_default_robots_txt_template(),

            // GEO/AEO validation.
            'geo_aeo_enabled'                 => false,
            'geo_aeo_auto_include_post_types' => true,
            'geo_aeo_auto_include_taxonomies' => true,
            'geo_aeo_post_types'              => [],
            'geo_aeo_taxonomies'              => [],
            'geo_aeo_excluded_post_types'     => [],
            'geo_aeo_excluded_taxonomies'     => [],
            'geo_aeo_min_score'               => 80,
            'geo_aeo_require_quick_answer'    => true,
            'geo_aeo_require_faq'             => true,
            'geo_aeo_require_entity_signals'  => true,
            'geo_aeo_require_schema'          => true,
            'geo_aeo_require_medical_disclaimer' => true,
            'geo_aeo_require_review_status'   => true,
            'geo_aeo_require_search_eligibility' => true,
            'geo_aeo_require_experience_signals' => true,
            'geo_aeo_require_intent_coverage' => true,
            'geo_aeo_require_media_support'   => true,
            'geo_aeo_require_schema_visibility' => true,
            'geo_aeo_require_trust_signals'   => true,
            'geo_aeo_require_brand_alignment' => true,
            'geo_aeo_require_brand_proof'     => true,
            'geo_aeo_require_brand_tone'      => true,
            'geo_aeo_target_entities'         => '',

            // AI English SEO Sync.
            'ai_sync_enabled'                 => false,
            'ai_sync_force_en_noindex'        => false,
            'ai_sync_auto_include_post_types' => true,
            'ai_sync_auto_include_taxonomies' => true,
            'ai_sync_post_types'              => [],
            'ai_sync_taxonomies'              => [],
            'ai_sync_excluded_post_types'     => [],
            'ai_sync_excluded_taxonomies'     => [],
            'ai_sync_openai_api_key'          => '',
            'ai_sync_openai_model'            => 'gpt-5.6-luna',
            'ai_sync_openai_model_economy'    => 'gpt-5.6-luna',
            'ai_sync_openai_model_important'  => 'gpt-5.6-sol',
            'ai_sync_target_language'         => 'en',
            'ai_sync_target_locale'           => 'en_US',
            'ai_sync_write_yoast'             => false,
            'ai_sync_write_shynh_seo'         => true,
            'ai_sync_translate_terms'         => true,
            'ai_sync_link_polylang'           => true,
            'ai_sync_auto_publish'            => false,
            'ai_sync_preserve_images'         => true,
            'ai_sync_copy_featured_image'     => true,
            'ai_sync_suggest_image_metadata'  => true,
            'ai_sync_auto_apply_image_metadata' => false,
            'ai_sync_suggest_additional_images' => false,
            'ai_sync_allow_ai_generated_images' => false,

            // Shynh Brand Brain (6 Pillars of Brand Intelligence).
            'brand_vision'               => 'Shynh Super Luxury — Viện Thẩm Mỹ & Clinic Hạng Sang 5 Sao dành cho Khách hàng Thượng lưu VIP (Doanh nhân, Phụ nữ trung niên 35-55+, Celeb). Tông giọng: Sang trọng, Quý phái, Tinh tế, Chuẩn Y Khoa.',
            'brand_technologies'         => 'Thermage FLX 2026 (RF đơn cực xóa nhăn), Ultherapy Prime (Nâng cơ SMAS 4.5mm), Laser Picoway (Mờ nám sạm 90%), Tiêm vi điểm Exosome & Botox/Filler Allergan/Galderma chính hãng.',
            'brand_medical_credentials' => '100% Đội ngũ Bác sĩ CKI Chuyên khoa Da liễu, chứng nhận an toàn y tế FDA Hoa Kỳ & CE Châu Âu, vô trùng phòng Lab, 0 ngày nghỉ dưỡng.',
            'brand_market_trends'        => 'Xu hướng 2026: Pre-juvenation (Trẻ hóa sớm), Nâng cơ không phẫu thuật, Thẩm mỹ tái tạo tế bào. Trả lời trực tiếp nỗi đau khách: Hiệu quả sau 1 buổi, nhẹ nhàng không đau, 0 nghỉ dưỡng.',
            'brand_usps'                 => '1. 100% Máy móc nhập khẩu chính hãng check mã QR; 2. Phòng VIP 1:1 bảo mật riêng tư; 3. Phác đồ cá nhân hóa đa tầng; 4. Trải nghiệm Spa 5 sao Thụy Sĩ/Pháp; 5. Bác sĩ riêng đồng hành 365 ngày.',
            'brand_ctas'                 => 'Đặt lịch thăm khám & Soi da AI 3D trực tiếp cùng Bác sĩ Trưởng khoa. Nhận đặc quyền trải nghiệm liệu trình Thượng lưu VIP ngay hôm nay.',

            'brand_technologies'         => 'Approved technologies may include Thermage FLX, Ultherapy, Picoway, Exosome, Botox, or Filler only when present in approved service data, Knowledge Vault, or visible page content.',
            'brand_medical_credentials' => 'Use only verified doctor, specialist, license, training, manufacturer, FDA, CE, or clinical safety credentials. Do not invent credentials or guarantee safety, downtime, or outcomes.',
            'brand_market_trends'        => '2026 themes: pre-juvenation, non-surgical lifting, regenerative aesthetics, personalized consultation, realistic expectations, safety language, aftercare, and answer-first education.',
            'brand_usps'                 => '1. Verified technology and product provenance when available; 2. Private premium consultation experience; 3. Personalized protocol planning; 4. Refined service journey; 5. Transparent aftercare and follow-up guidance.',
            'brand_positioning'          => 'Premium beauty and aesthetic care brand for clients who value privacy, refined experience, expert consultation, and realistic expectations.',
            'brand_mission'              => 'Deliver personalized, refined, and safety-conscious beauty experiences with transparent consultation before, during, and after each service.',
            'brand_values'               => "Tinh te\nAn toan\nChuyen mon\nCa nhan hoa\nMinh bach\nCao cap\nNhan van",
            'brand_tone'                 => "premium\ncalm\nknowledgeable\nwarm\ntrustworthy\nrefined",
            'brand_personas'             => "Female 25-35: wants clear advice, glowing skin, efficient booking.\nFemale 35-50: cares about rejuvenation, safety, downtime, natural results.\nVIP client: values privacy, expert care, discreet premium service.\nFirst-time client: needs reassurance about process, pain, risk, and aftercare.",
            'brand_content_principles'   => "Answer the main question first.\nExplain mechanisms in plain Vietnamese.\nRespect natural beauty and avoid body shaming.\nMention consultation and individual assessment for YMYL topics.\nUse CTAs softly and avoid hard-sell language.",
            'brand_allowed_claims'       => "ho tro cai thien\nco the phu hop\nket qua co the khac nhau tuy co dia\ntu van theo tinh trang ca nhan\nquy trinh duoc danh gia truoc khi thuc hien",
            'brand_banned_claims'        => "cam ket 100%\nhieu qua vinh vien\nkhong rui ro\nkhong bien chung\nan toan tuyet doi\nkhoi hoan toan\ndep ngay lap tuc\ntre hoa 10 tuoi\nso 1 Viet Nam\nre nhat",
            'brand_proof_library'        => 'Use only verified proof: official branch data, approved technology names, visible testimonials, approved doctor or specialist credentials, real before-after cases with consent, press URLs, awards with year and issuer.',
            'brand_source_policy'        => 'AI must only use approved fields, visible page content, Knowledge Vault items, and verified proof library entries. If a fact is missing, return a missing_fields note instead of guessing.',
            'brand_ai_audit_enabled'     => true,
            'brand_ai_entity_lock'       => true,
            'brand_ai_require_source_map' => true,
            'brand_ai_require_claim_map' => true,
            'brand_ai_require_human_review' => true,
            'brand_ai_proof_required'    => true,
            'brand_ai_output_contract'   => true,
            'brand_ai_min_brand_fit'     => 85,
            'brand_ai_min_claim_safety'  => 95,
            'brand_ai_unverified_claim_mode' => 'block',
            'brand_ai_tone_deviation_mode'   => 'review',

            // Schema.
            'schema_enabled'                => true,
            'schema_auto_detect_type'       => true,
            'schema_enable_faq'             => true,
            'schema_enable_service'         => true,
            'schema_enable_medical_web_page' => true,
            'schema_enable_collection_page' => true,
            'schema_require_human_review'   => true,

            // Schema.
            'org_name'             => get_bloginfo( 'name' ),
            'org_logo'             => '',
        ];
    }

    public static function get_settings() {
        $defaults = self::get_default_settings();
        $settings = get_option( 'shynh_seo_settings', $defaults );

        if ( ! is_array( $settings ) ) {
            return $defaults;
        }

        $settings = array_merge( $defaults, $settings );

        if ( empty( $settings['robots_txt_manual_content'] ) ) {
            $settings['robots_txt_manual_content'] = $defaults['robots_txt_manual_content'];
        }

        return $settings;
    }

    public static function get_seo_post_types() {
        $settings   = self::get_settings();
        $post_types = isset( $settings['seo_post_types'] ) && is_array( $settings['seo_post_types'] )
            ? $settings['seo_post_types']
            : [];

        $public_post_types = get_post_types( [ 'public' => true ], 'names' );

        if ( isset( $settings['enable_media_seo_pages'] ) && $settings['enable_media_seo_pages'] ) {
            $allowed_post_types = $public_post_types;
            $allowed_post_types['attachment'] = 'attachment'; // Ensure attachment is included if public is not true by default
        } else {
            $allowed_post_types = array_diff( $public_post_types, [ 'attachment' ] );
        }

        $post_types = array_values( array_intersect( $post_types, $allowed_post_types ) );

        return apply_filters( 'shynh_seo_post_types', $post_types );
    }

    public static function get_related_post_types() {
        $settings   = self::get_settings();
        $post_types = isset( $settings['related_post_types'] ) && is_array( $settings['related_post_types'] )
            ? $settings['related_post_types']
            : [];

        $public_post_types = get_post_types( [ 'public' => true ], 'names' );

        if ( isset( $settings['enable_media_seo_pages'] ) && $settings['enable_media_seo_pages'] ) {
            $allowed_post_types = $public_post_types;
            $allowed_post_types['attachment'] = 'attachment';
        } else {
            $allowed_post_types = array_diff( $public_post_types, [ 'attachment' ] );
        }

        return array_values( array_intersect( $post_types, $allowed_post_types ) );
    }

    public static function get_public_content_post_types() {
        return array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), [ 'attachment' ] ) );
    }

    public static function get_public_content_taxonomies() {
        return array_values( array_diff( get_taxonomies( [ 'public' => true ], 'names' ), [ 'post_format' ] ) );
    }

    public static function get_geo_aeo_post_types() {
        $settings = self::get_settings();
        $public   = self::get_public_content_post_types();

        if ( ! empty( $settings['geo_aeo_auto_include_post_types'] ) ) {
            $excluded = isset( $settings['geo_aeo_excluded_post_types'] ) ? (array) $settings['geo_aeo_excluded_post_types'] : [];
            return array_values( array_diff( $public, array_map( 'sanitize_key', $excluded ) ) );
        }

        $selected = isset( $settings['geo_aeo_post_types'] ) ? (array) $settings['geo_aeo_post_types'] : [];
        return array_values( array_intersect( array_map( 'sanitize_key', $selected ), $public ) );
    }

    public static function get_geo_aeo_taxonomies() {
        $settings = self::get_settings();
        $public   = self::get_public_content_taxonomies();

        if ( ! empty( $settings['geo_aeo_auto_include_taxonomies'] ) ) {
            $excluded = isset( $settings['geo_aeo_excluded_taxonomies'] ) ? (array) $settings['geo_aeo_excluded_taxonomies'] : [];
            return array_values( array_diff( $public, array_map( 'sanitize_key', $excluded ) ) );
        }

        $selected = isset( $settings['geo_aeo_taxonomies'] ) ? (array) $settings['geo_aeo_taxonomies'] : [];
        return array_values( array_intersect( array_map( 'sanitize_key', $selected ), $public ) );
    }

    public static function post_type_supports_geo_aeo( $post_type ) {
        if ( empty( self::get_settings()['geo_aeo_enabled'] ) ) {
            return false;
        }

        return in_array( $post_type, self::get_geo_aeo_post_types(), true );
    }

    public static function taxonomy_supports_geo_aeo( $taxonomy ) {
        if ( empty( self::get_settings()['geo_aeo_enabled'] ) ) {
            return false;
        }

        return in_array( $taxonomy, self::get_geo_aeo_taxonomies(), true );
    }

    /**
     * Return the default SEO rules.
     *
     * @return array
     */
    public static function get_default_rules() {
        return [
            'keyword_in_title' => [
                'label'       => 'Từ khóa trong Tiêu đề',
                'enabled'     => true,
                'weight'      => 15,
                'description' => 'Focus Keyword phải xuất hiện trong tiêu đề bài viết.',
            ],
            'keyword_in_meta_desc' => [
                'label'       => 'Từ khóa trong Meta Description',
                'enabled'     => true,
                'weight'      => 10,
                'description' => 'Focus Keyword phải xuất hiện trong mô tả meta.',
            ],
            'keyword_in_slug' => [
                'label'       => 'Từ khóa trong URL (Slug)',
                'enabled'     => true,
                'weight'      => 10,
                'description' => 'Focus Keyword phải xuất hiện trong đường dẫn bài viết.',
            ],
            'keyword_in_headings' => [
                'label'       => 'Từ khóa trong Heading',
                'enabled'     => true,
                'weight'      => 10,
                'description' => 'Focus Keyword phải xuất hiện ít nhất 1 lần trong heading của bài viết.',
            ],
            'keyword_in_intro' => [
                'label'       => 'Từ khóa trong đoạn mở đầu',
                'enabled'     => true,
                'weight'      => 10,
                'green_max'   => 100,  // within first 100 words.
                'orange_max'  => 200,  // within first 200 words.
                'description' => 'Focus Keyword nên xuất hiện trong 100 từ đầu tiên.',
            ],
            'content_length' => [
                'label'       => 'Độ dài nội dung',
                'enabled'     => true,
                'weight'      => 10,
                'green_min'   => 600,
                'orange_min'  => 300,
                'description' => 'Bài viết nên có ít nhất 600 từ.',
            ],
            'title_length' => [
                'label'       => 'Độ dài SEO Title',
                'enabled'     => true,
                'weight'      => 10,
                'green_min'   => 50,
                'green_max'   => 60,
                'orange_min'  => 30,
                'orange_max'  => 70,
                'description' => 'Tiêu đề SEO nên dài từ 50-60 ký tự.',
            ],
            'meta_desc_length' => [
                'label'       => 'Độ dài Meta Description',
                'enabled'     => true,
                'weight'      => 10,
                'green_min'   => 120,
                'green_max'   => 160,
                'orange_min'  => 80,
                'orange_max'  => 200,
                'description' => 'Mô tả meta nên dài từ 120-160 ký tự.',
            ],
            'image_alt' => [
                'label'       => 'Ảnh có Alt Text',
                'enabled'     => true,
                'weight'      => 5,
                'description' => 'Bài viết phải có ít nhất 1 ảnh và ảnh phải có thuộc tính alt.',
            ],
            'keyword_density' => [
                'label'       => 'Mật độ từ khóa',
                'enabled'     => true,
                'weight'      => 5,
                'green_min'   => 1.0,
                'green_max'   => 3.0,
                'orange_min'  => 0.5,
                'orange_max'  => 4.0,
                'description' => 'Mật độ từ khóa nên nằm trong khoảng 1%-3%.',
            ],
            'internal_links' => [
                'label'       => 'Liên kết nội bộ',
                'enabled'     => true,
                'weight'      => 5,
                'green_min'   => 2,
                'orange_min'  => 1,
                'description' => 'Bài viết nên có ít nhất 2 liên kết nội bộ.',
            ],
            'bold_internal_links' => [
                'label'       => 'Liên kết nội bộ được nhấn mạnh',
                'enabled'     => true,
                'weight'      => 5,
                'green_min'   => 1,
                'orange_min'  => 0,
                'description' => 'Bài viết nên có ít nhất 1 internal link được bold/strong.',
            ],
            'previously_used_keyword' => [
                'label'       => 'Từ khóa lặp (Cannibalization)',
                'enabled'     => true,
                'weight'      => 15,
                'description' => 'Focus Keyword chưa từng được sử dụng cho bất kỳ bài viết nào khác.',
            ],
            'readability' => [
                'label'       => 'Tính dễ đọc (Readability)',
                'enabled'     => true,
                'weight'      => 5,
                'description' => 'Đánh giá độ dài câu, độ dài đoạn văn và tính mạch lạc của bài viết.',
            ],
        ];
    }
}
