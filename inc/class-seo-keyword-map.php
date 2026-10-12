<?php
/**
 * Shynh SEO — Keyword Map admin page.
 *
 * Interactive keyword graph (Cytoscape.js, bundled offline) that consumes the
 * REST `keyword-map` endpoint via the admin REST nonce. Shows covered keywords,
 * their posts, topic clusters, cannibalization, and optional gap/suggested keywords.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Keyword_Map {

    const MENU_SLUG = 'shynh-seo-keyword-map';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 21 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'shynh-seo',
            __( 'Bản đồ Từ khóa', 'shynh-seo' ),
            __( 'Bản đồ Từ khóa', 'shynh-seo' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
            return;
        }
        $ver = defined( 'SHYNH_SEO_VERSION' ) ? SHYNH_SEO_VERSION : '1.0.0';

        wp_enqueue_script( 'shynh-seo-cytoscape', SHYNH_SEO_URL . 'assets/vendor/cytoscape/cytoscape.min.js', [], '3.30.2', true );
        wp_enqueue_script( 'shynh-seo-keyword-map', SHYNH_SEO_URL . 'assets/js/keyword-map.js', [ 'shynh-seo-cytoscape' ], $ver, true );

        $taxonomies = [];
        foreach ( Shynh_SEO_Core::get_public_content_taxonomies() as $tx ) {
            $obj = get_taxonomy( $tx );
            $taxonomies[] = [ 'name' => $tx, 'label' => $obj ? ( $obj->labels->name ?? $tx ) : $tx ];
        }

        wp_localize_script( 'shynh-seo-keyword-map', 'ShynhSeoKwMap', [
            'restBase'   => untrailingslashit( rest_url( Shynh_SEO_API::NAMESPACE ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'taxonomies' => $taxonomies,
            'lastRun'    => Shynh_SEO_Keyword_Expander::last_run(),
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap shynh-seo-kwmap font-sans min-h-screen -ml-5 -mt-2 p-6 md:p-8 bg-[#F9F9F9]">
            <div class="max-w-[1400px] mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-serif font-bold text-gray-900 flex items-center gap-3 mb-3">
                        <span class="dashicons dashicons-networking text-primary" style="font-size: 32px; width: 32px; height: 32px;"></span>
                        <?php esc_html_e( 'Bản đồ Từ khóa', 'shynh-seo' ); ?>
                    </h1>
                    <p class="text-sm text-gray-500 font-medium mt-1">
                        <?php esc_html_e( 'Xanh = từ khóa đã phủ · Đỏ = trùng lặp (cannibalization) · Cam = gợi ý mở rộng (gap) · Xanh dương = bài viết · Xám = chủ đề.', 'shynh-seo' ); ?>
                    </p>
                </div>

                <div class="kwmap-toolbar flex flex-wrap gap-4 items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 relative z-10">
                    <select id="kwmap-taxonomy" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300 h-[38px]"><option value=""><?php esc_html_e( 'Tất cả chủ đề', 'shynh-seo' ); ?></option></select>
                    
                    <div class="flex items-center gap-6 px-5 py-2 bg-gray-50 rounded-lg border border-gray-100 h-[38px]">
                        <label class="kwmap-chk flex items-center gap-2 text-sm text-gray-700 font-medium cursor-pointer hover:text-gray-900 transition-colors m-0">
                            <input type="checkbox" id="kwmap-suggested" class="rounded border-gray-300 text-primary focus:ring-primary bg-white m-0"> 
                            <?php esc_html_e( 'Hiện từ khóa gợi ý', 'shynh-seo' ); ?>
                        </label>
                        <div class="w-px h-5 bg-gray-200"></div>
                        <label class="flex items-center gap-3 text-sm text-gray-700 font-medium m-0">
                            <?php esc_html_e( 'Tối thiểu', 'shynh-seo' ); ?> 
                            <input type="number" id="kwmap-minusage" value="1" min="1" class="w-16 border border-gray-200 bg-white rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm py-1 m-0 hover:border-gray-300 transition-all"> 
                            <?php esc_html_e( 'bài', 'shynh-seo' ); ?>
                        </label>
                    </div>

                    <div class="flex gap-4 ml-auto">
                        <button id="kwmap-reload" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-8 py-2 rounded-lg text-sm font-bold tracking-wide hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md h-[38px] flex items-center justify-center gap-2 border-none">
                            <span class="dashicons dashicons-update-alt" style="font-size:16px;width:16px;height:16px;margin-top:2px;"></span>
                            <?php esc_html_e( 'Vẽ lại', 'shynh-seo' ); ?>
                        </button>
                        <button id="kwmap-expand" class="bg-white text-gray-700 border border-gray-200 px-8 py-2 rounded-lg text-sm font-semibold tracking-wide hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm h-[38px] flex items-center justify-center gap-2">
                            <span class="dashicons dashicons-admin-links" style="font-size:16px;width:16px;height:16px;margin-top:2px;"></span>
                            <?php esc_html_e( 'Chạy mở rộng', 'shynh-seo' ); ?>
                        </button>
                    </div>
                    
                    <span id="kwmap-status" class="kwmap-status w-full mt-2 text-sm text-gray-500 font-medium italic transition-opacity"></span>
                </div>

                <div class="relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="absolute top-5 right-5 z-10 flex gap-2 pointer-events-none">
                        <span class="inline-flex items-center justify-center px-3 py-1.5 bg-emerald-50/90 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-100 shadow-sm backdrop-blur-sm">Đã phủ</span>
                        <span class="inline-flex items-center justify-center px-3 py-1.5 bg-rose-50/90 text-rose-700 text-xs font-semibold rounded-full border border-rose-100 shadow-sm backdrop-blur-sm">Trùng lặp</span>
                        <span class="inline-flex items-center justify-center px-3 py-1.5 bg-amber-50/90 text-amber-700 text-xs font-semibold rounded-full border border-amber-100 shadow-sm backdrop-blur-sm">Gợi ý</span>
                    </div>
                    <div id="kwmap-graph" class="kwmap-graph w-full cursor-grab active:cursor-grabbing" style="height: 680px; background: #fdfdfd;"></div>
                </div>

                <div id="kwmap-detail" class="kwmap-detail hidden mt-6 p-6 bg-white border border-gray-100 rounded-2xl shadow-sm transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                </div>
            </div>
        </div>

        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                important: '.shynh-seo-kwmap',
                corePlugins: {
                    preflight: false,
                },
                theme: {
                    extend: {
                        colors: {
                            primary: '#D4AF37',
                        }
                    }
                }
            }
        </script>
        <style>
        /* Scoped WP Admin Resets */
        .shynh-seo-kwmap button {
            appearance: none !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            outline: none !important;
            font-family: inherit;
        }
        .shynh-seo-kwmap select,
        .shynh-seo-kwmap input[type="text"],
        .shynh-seo-kwmap input[type="search"],
        .shynh-seo-kwmap input[type="number"] {
            appearance: none !important;
            box-shadow: none !important;
            outline: none !important;
            min-height: 38px;
            font-family: inherit;
        }
        .shynh-seo-kwmap input[type="checkbox"] {
            margin: 0 !important;
            box-shadow: none !important;
        }

        .shynh-seo-kwmap .kwmap-detail { animation: ssrFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .shynh-seo-kwmap .kwmap-detail h3 { font-size: 1.125rem; font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif; font-weight: 700; color: #111827; margin-bottom: 0.75rem; margin-top: 0; }
        .shynh-seo-kwmap .kwmap-detail p { color: #4b5563; font-size: 0.875rem; margin-bottom: 0.5rem; line-height: 1.625; margin-top: 0; }
        .shynh-seo-kwmap .kwmap-detail ul { list-style-type: none; font-size: 0.875rem; color: #374151; margin-top: 0.75rem; padding-left: 0; }
        .shynh-seo-kwmap .kwmap-detail ul > li { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; }
        .shynh-seo-kwmap .kwmap-detail li::before { content: "•"; color: var(--wp-admin-theme-color, #D4AF37); font-weight: 700; }
        .shynh-seo-kwmap .kwmap-detail a { color: var(--wp-admin-theme-color, #D4AF37); font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .shynh-seo-kwmap .kwmap-detail a:hover { opacity: 0.8; text-decoration: underline; }
        
        @keyframes ssrFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Subtle dot grid for graph background */
        #kwmap-graph { background-image: radial-gradient(#e2e8f0 1.5px, transparent 1.5px); background-size: 24px 24px; }
        </style>
        <?php
    }
}
