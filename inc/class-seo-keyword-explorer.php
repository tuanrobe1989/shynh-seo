<?php
/**
 * Shynh SEO — Keyword Explorer admin page.
 *
 * Interactive tiered keyword expansion (L1→L4) for a chosen seed, rendered as a
 * Cytoscape tree + a grouped table. Consumes REST `keywords/seeds` and
 * `keywords/expand-seed` via the admin nonce.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Keyword_Explorer {

    const MENU_SLUG = 'shynh-seo-keyword-explorer';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 22 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'shynh-seo',
            __( 'Khám phá Từ khóa', 'shynh-seo' ),
            __( 'Khám phá Từ khóa', 'shynh-seo' ),
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
        wp_enqueue_script( 'shynh-seo-keyword-explorer', SHYNH_SEO_URL . 'assets/js/keyword-explorer.js', [ 'shynh-seo-cytoscape' ], $ver, true );

        wp_localize_script( 'shynh-seo-keyword-explorer', 'ShynhSeoKwExp', [
            'restBase' => untrailingslashit( rest_url( Shynh_SEO_API::NAMESPACE ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap shynh-seo-kwexp font-sans min-h-screen -ml-5 -mt-2 p-6 md:p-8 bg-[#F9F9F9]">
            <div class="max-w-[1500px] mx-auto">
                <div class="mb-6">
                    <h1 class="text-3xl font-serif font-bold text-gray-900 flex items-center gap-3 mb-2">
                        <span class="dashicons dashicons-search text-primary" style="font-size:32px;width:32px;height:32px;"></span>
                        <?php esc_html_e( 'Khám phá Từ khóa', 'shynh-seo' ); ?>
                    </h1>
                    <p class="text-sm text-gray-500 font-medium"><?php esc_html_e( 'Chọn 1 từ khóa gốc → gợi ý từ khóa bổ trợ theo cấp độ L2 · L3 · L4. Xanh = đã có bài · Cam = chưa có (cơ hội).', 'shynh-seo' ); ?></p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
                    <!-- Seed picker with flexible faceted filter -->
                    <aside class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 h-fit">
                        <div class="relative mb-3">
                            <input type="search" id="kwexp-seed-search" placeholder="<?php esc_attr_e( 'Tìm từ khóa gốc…', 'shynh-seo' ); ?>" class="w-full border border-gray-200 bg-white rounded-lg text-sm pl-9 pr-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm">
                            <span class="dashicons dashicons-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></span>
                        </div>

                        <!-- Status chips -->
                        <div id="kwexp-chips" class="flex flex-wrap gap-1.5 mb-2">
                            <button type="button" class="kwexp-chip is-active px-2.5 py-1 rounded-full text-xs font-medium border border-gray-200 bg-gray-50" data-status=""><?php esc_html_e( 'Tất cả', 'shynh-seo' ); ?></button>
                            <button type="button" class="kwexp-chip px-2.5 py-1 rounded-full text-xs font-medium border border-gray-200 bg-white" data-status="covered"><?php esc_html_e( 'Đã có bài', 'shynh-seo' ); ?></button>
                            <button type="button" class="kwexp-chip px-2.5 py-1 rounded-full text-xs font-medium border border-gray-200 bg-white" data-status="gap"><?php esc_html_e( 'Cơ hội', 'shynh-seo' ); ?></button>
                            <button type="button" class="kwexp-chip px-2.5 py-1 rounded-full text-xs font-medium border border-gray-200 bg-white" data-status="cannibal"><?php esc_html_e( 'Trùng lặp', 'shynh-seo' ); ?></button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <select id="kwexp-f-source" class="border border-gray-200 bg-white rounded-lg px-2 py-1.5 text-xs shadow-sm">
                                <option value=""><?php esc_html_e( 'Mọi nguồn', 'shynh-seo' ); ?></option>
                                <option value="keyword"><?php esc_html_e( 'Focus keyword', 'shynh-seo' ); ?></option>
                                <option value="service"><?php esc_html_e( 'Dịch vụ', 'shynh-seo' ); ?></option>
                            </select>
                            <select id="kwexp-f-topic" class="border border-gray-200 bg-white rounded-lg px-2 py-1.5 text-xs shadow-sm">
                                <option value=""><?php esc_html_e( 'Mọi chủ đề', 'shynh-seo' ); ?></option>
                            </select>
                        </div>
                        <select id="kwexp-f-sort" class="w-full border border-gray-200 bg-white rounded-lg px-2 py-1.5 text-xs shadow-sm mb-3">
                            <option value="usage"><?php esc_html_e( 'Sắp xếp: nhiều bài nhất', 'shynh-seo' ); ?></option>
                            <option value="alpha"><?php esc_html_e( 'Sắp xếp: A → Z', 'shynh-seo' ); ?></option>
                            <option value="least"><?php esc_html_e( 'Sắp xếp: ít bài nhất', 'shynh-seo' ); ?></option>
                        </select>

                        <div id="kwexp-seed-count" class="text-xs text-gray-400 mb-1"></div>
                        <ul id="kwexp-seed-list" class="space-y-1 max-h-[520px] overflow-auto text-sm"></ul>
                    </aside>

                    <!-- Graph + table -->
                    <main>
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <h2 id="kwexp-current" class="text-lg font-serif font-bold text-gray-800 mr-auto"><?php esc_html_e( 'Chọn một từ khóa gốc bên trái', 'shynh-seo' ); ?></h2>
                            <select id="kwexp-f-level" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm shadow-sm">
                                <option value=""><?php esc_html_e( 'Mọi cấp độ', 'shynh-seo' ); ?></option>
                                <option value="2">Level 2</option>
                                <option value="3">Level 3</option>
                                <option value="4">Level 4</option>
                            </select>
                            <select id="kwexp-f-intent" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm shadow-sm">
                                <option value=""><?php esc_html_e( 'Mọi ý định', 'shynh-seo' ); ?></option>
                                <option value="transactional">Transactional</option>
                                <option value="commercial">Commercial</option>
                                <option value="informational">Informational</option>
                                <option value="question">Question</option>
                            </select>
                            <label class="flex items-center gap-2 text-sm text-gray-700 font-medium"><input type="checkbox" id="kwexp-f-gap"> <?php esc_html_e( 'Chỉ cơ hội (gap)', 'shynh-seo' ); ?></label>
                            <span id="kwexp-stats" class="text-sm text-gray-500 italic"></span>
                        </div>

                        <div class="relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                            <div class="absolute top-4 right-4 z-10 flex gap-2 pointer-events-none">
                                <span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-100">Đã có</span>
                                <span class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-semibold rounded-full border border-amber-100">Cơ hội</span>
                            </div>
                            <div id="kwexp-graph" style="height:460px;background:#fdfdfd;"></div>
                        </div>

                        <div id="kwexp-detail" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-4 hidden"></div>
                        <div id="kwexp-table" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                    </main>
                </div>
            </div>
        </div>

        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                important: '.shynh-seo-kwexp',
                corePlugins: { preflight: false },
                theme: { extend: { colors: { primary: '#D4AF37' } } }
            };
        </script>
        <style>
            .shynh-seo-kwexp button { appearance:none !important; border:none !important; box-shadow:none !important; border-radius:0 !important; outline:none !important; font-family:inherit; }
            .shynh-seo-kwexp input, .shynh-seo-kwexp select { appearance:none !important; box-shadow:none !important; outline:none !important; font-family:inherit; }
            .shynh-seo-kwexp .kwexp-seed.is-active { background:#FBF7E9; border-color:#D4AF37; }
        </style>
        <?php
    }
}
