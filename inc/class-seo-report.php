<?php
/**
 * SEO Report Dashboard.
 *
 * A lightweight in-admin app that consumes the Shynh SEO REST API
 * (`shynh-seo/v1/report/*`) via the logged-in admin's REST nonce. All heavy
 * work is paginated or chunked to stay responsive on large sites.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Report {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'shynh-seo-report' ) ) {
            return;
        }

        $ver = defined( 'SHYNH_SEO_VERSION' ) ? SHYNH_SEO_VERSION : '1.0.0';
        wp_enqueue_script(
            'shynh-seo-report-dashboard',
            SHYNH_SEO_URL . 'assets/js/report-dashboard.js',
            [],
            $ver,
            true
        );

        $post_types = [];
        foreach ( Shynh_SEO_Core::get_public_content_post_types() as $pt ) {
            $obj = get_post_type_object( $pt );
            $post_types[] = [ 'name' => $pt, 'label' => $obj ? ( $obj->labels->name ?? $pt ) : $pt ];
        }

        wp_localize_script( 'shynh-seo-report-dashboard', 'ShynhSeoReport', [
            'restBase' => untrailingslashit( rest_url( Shynh_SEO_API::NAMESPACE ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'postTypes' => $post_types,
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap shynh-seo-report-app font-sans min-h-screen -ml-5 -mt-2 p-6 md:p-8 bg-[#F9F9F9]">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 flex items-center gap-3">
                    <span class="dashicons dashicons-chart-bar text-primary" style="font-size: 32px; width: 32px; height: 32px; margin-right: 8px;"></span>
                    <?php esc_html_e( 'Báo Cáo SEO (Report Dashboard)', 'shynh-seo' ); ?>
                </h1>

                <nav class="ssr-tabs flex gap-8 mb-8 border-b border-gray-200">
                    <button class="ssr-tab is-active pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="overview"><?php esc_html_e( 'Tổng quan', 'shynh-seo' ); ?></button>
                    <button class="ssr-tab pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="posts"><?php esc_html_e( 'Bài viết', 'shynh-seo' ); ?></button>
                    <button class="ssr-tab pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="keywords"><?php esc_html_e( 'Từ khóa', 'shynh-seo' ); ?></button>
                    <button class="ssr-tab pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="geo"><?php esc_html_e( 'GEO / AEO', 'shynh-seo' ); ?></button>
                    <button class="ssr-tab pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="fixes"><?php esc_html_e( 'Cần Sửa', 'shynh-seo' ); ?></button>
                    <button class="ssr-tab pb-4 text-sm font-semibold tracking-wide text-gray-500 hover:text-gray-900 transition-colors uppercase" data-tab="duplicates"><?php esc_html_e( 'Trùng lặp', 'shynh-seo' ); ?></button>
                </nav>

                <section class="ssr-panel is-active" data-panel="overview">
                    <div class="ssr-toolbar flex flex-wrap gap-4 items-center mb-8 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <button id="ssr-rescan" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-8 py-2 rounded-lg text-sm font-bold tracking-wide hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md h-[38px] flex items-center justify-center border-none"><?php esc_html_e( 'Chấm điểm lại (theo lô)', 'shynh-seo' ); ?></button>
                        <button id="ssr-refresh-summary" class="bg-white text-gray-700 border border-gray-200 px-8 py-2 rounded-lg text-sm font-semibold tracking-wide hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm h-[38px] flex items-center justify-center"><?php esc_html_e( 'Làm mới số liệu', 'shynh-seo' ); ?></button>
                        <div id="ssr-rescan-progress" class="ssr-progress flex items-center gap-3 w-64 ml-auto" hidden>
                            <div class="ssr-track w-full bg-gray-100 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="ssr-progress-bar bg-primary h-full transition-all duration-300" style="width:0"></div>
                            </div>
                            <span class="ssr-progress-text text-sm text-gray-500 font-medium whitespace-nowrap"></span>
                        </div>
                    </div>
                    <div id="ssr-kpis" class="ssr-kpis grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <p class="ssr-loading text-gray-400 italic font-medium px-2"><?php esc_html_e( 'Đang tải…', 'shynh-seo' ); ?></p>
                    </div>
                </section>

                <section class="ssr-panel hidden" data-panel="posts">
                    <div class="ssr-filters flex flex-wrap gap-4 items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <select id="ssr-f-posttype" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300"><option value=""><?php esc_html_e( 'Tất cả loại', 'shynh-seo' ); ?></option></select>
                        <select id="ssr-f-score" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300">
                            <option value=""><?php esc_html_e( 'Điểm SEO: tất cả', 'shynh-seo' ); ?></option>
                            <option value="70:"><?php esc_html_e( 'Tốt (≥70)', 'shynh-seo' ); ?></option>
                            <option value="40:69"><?php esc_html_e( 'Khá (40–69)', 'shynh-seo' ); ?></option>
                            <option value=":39"><?php esc_html_e( 'Kém (<40)', 'shynh-seo' ); ?></option>
                        </select>
                        <select id="ssr-f-geo" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300">
                            <option value=""><?php esc_html_e( 'GEO: tất cả', 'shynh-seo' ); ?></option>
                            <option value="80:"><?php esc_html_e( 'GEO tốt (≥80)', 'shynh-seo' ); ?></option>
                            <option value=":49"><?php esc_html_e( 'GEO yếu (<50)', 'shynh-seo' ); ?></option>
                        </select>
                        <select id="ssr-f-missing" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300">
                            <option value=""><?php esc_html_e( 'Thiếu: —', 'shynh-seo' ); ?></option>
                            <option value="focus_keyword"><?php esc_html_e( 'Thiếu focus keyword', 'shynh-seo' ); ?></option>
                            <option value="meta_desc"><?php esc_html_e( 'Thiếu meta description', 'shynh-seo' ); ?></option>
                            <option value="meta_title"><?php esc_html_e( 'Thiếu meta title', 'shynh-seo' ); ?></option>
                        </select>
                        <div class="flex gap-5 px-3 py-2 bg-gray-50 rounded-lg border border-gray-100">
                            <label class="flex items-center gap-2 text-sm text-gray-700 font-medium cursor-pointer hover:text-gray-900 transition-colors"><input type="checkbox" id="ssr-f-faq" class="rounded border-gray-300 text-primary focus:ring-primary bg-white"> FAQ</label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 font-medium cursor-pointer hover:text-gray-900 transition-colors"><input type="checkbox" id="ssr-f-schema" class="rounded border-gray-300 text-primary focus:ring-primary bg-white"> Schema</label>
                        </div>
                        <div class="relative ml-auto flex-grow md:flex-grow-0 min-w-[250px]">
                            <span class="dashicons dashicons-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 z-10" style="margin-top:-2px"></span>
                            <input type="search" id="ssr-f-search" placeholder="<?php esc_attr_e( 'Tìm kiếm bài viết…', 'shynh-seo' ); ?>" class="w-full border border-gray-200 bg-white rounded-lg text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary pl-10 pr-4 py-2 shadow-sm transition-all h-[38px] hover:border-gray-300">
                        </div>
                        <button id="ssr-apply" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-8 py-2 rounded-lg text-sm font-bold tracking-wide hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md h-[38px] flex items-center justify-center border-none"><?php esc_html_e( 'Lọc', 'shynh-seo' ); ?></button>
                    </div>
                    <div id="ssr-posts" class="ssr-table-wrap bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                </section>

                <section class="ssr-panel hidden" data-panel="keywords">
                    <div class="ssr-filters flex gap-4 items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <div class="relative w-full max-w-md">
                            <input type="search" id="ssr-kw-search" placeholder="<?php esc_attr_e( 'Tìm từ khóa…', 'shynh-seo' ); ?>" class="w-full border-gray-200 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary pl-10 shadow-sm py-2">
                            <span class="dashicons dashicons-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></span>
                        </div>
                        <button id="ssr-kw-apply" class="bg-primary text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition-all shadow-sm"><?php esc_html_e( 'Lọc', 'shynh-seo' ); ?></button>
                    </div>
                    <div id="ssr-keywords" class="ssr-table-wrap bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                </section>

                <section class="ssr-panel hidden" data-panel="geo">
                    <div id="ssr-geo" class="ssr-table-wrap bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                </section>

                <section class="ssr-panel hidden" data-panel="fixes">
                    <div id="ssr-fixes-summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6"></div>
                    <div class="ssr-filters flex flex-wrap gap-4 items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <select id="ssr-fx-posttype" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all hover:border-gray-300"><option value=""><?php esc_html_e( 'Tất cả loại', 'shynh-seo' ); ?></option></select>
                        <span id="ssr-fx-active-filter" class="text-sm text-gray-500 italic"></span>
                        <button id="ssr-fx-apply" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-8 py-2 rounded-lg text-sm font-bold tracking-wide hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md h-[38px] flex items-center justify-center border-none ml-auto"><?php esc_html_e( 'Lọc', 'shynh-seo' ); ?></button>
                    </div>
                    <div id="ssr-fixes" class="ssr-table-wrap bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                </section>

                <section class="ssr-panel hidden" data-panel="duplicates">
                    <div class="ssr-toolbar flex flex-wrap gap-4 items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <button id="ssr-dup-scan" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-8 py-2 rounded-lg text-sm font-bold tracking-wide hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md h-[38px] flex items-center justify-center border-none"><?php esc_html_e( 'Quét trùng lặp (theo lô)', 'shynh-seo' ); ?></button>
                        <div id="ssr-dup-progress" class="ssr-progress flex items-center gap-3 w-64" hidden>
                            <div class="ssr-track w-full bg-gray-100 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="ssr-progress-bar bg-primary h-full transition-all duration-300" style="width:0"></div>
                            </div>
                            <span class="ssr-progress-text text-sm text-gray-500 font-medium whitespace-nowrap"></span>
                        </div>
                    </div>
                    <div id="ssr-duplicates" class="ssr-table-wrap bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"></div>
                </section>
            </div>
        </div>

        <?php $this->inline_styles(); ?>
        <?php
    }

    private function inline_styles() {
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                important: '.shynh-seo-report-app',
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
        .shynh-seo-report-app button {
            appearance: none !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            outline: none !important;
            font-family: inherit;
        }
        .shynh-seo-report-app select,
        .shynh-seo-report-app input[type="text"],
        .shynh-seo-report-app input[type="search"],
        .shynh-seo-report-app input[type="number"] {
            appearance: none !important;
            box-shadow: none !important;
            outline: none !important;
            min-height: 38px;
            font-family: inherit;
        }
        .shynh-seo-report-app input[type="checkbox"] {
            margin: 0 !important;
            box-shadow: none !important;
        }

        .shynh-seo-report-app .ssr-tab { border-bottom: 2px solid transparent !important; background: transparent !important; cursor: pointer; padding-left: 0 !important; padding-right: 0 !important; }
        .shynh-seo-report-app .ssr-tab.is-active { color: var(--wp-admin-theme-color, #D4AF37) !important; border-bottom-color: var(--wp-admin-theme-color, #D4AF37) !important; }
        .shynh-seo-report-app .ssr-panel { display: none; }
        .shynh-seo-report-app .ssr-panel.is-active { display: block; animation: ssrFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        
        @keyframes ssrFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* KPI Cards generated by JS */
        .shynh-seo-report-app .ssr-card { background-color: #ffffff; border: 1px solid #f3f4f6; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.4s ease; position: relative; overflow: hidden; }
        .shynh-seo-report-app .ssr-card::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--wp-admin-theme-color, #D4AF37) 0%, transparent 100%); opacity: 0; transition: opacity 0.4s ease; }
        .shynh-seo-report-app .ssr-card:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transform: translateY(-4px); }
        .shynh-seo-report-app .ssr-card:hover::before { opacity: 1; }
        .shynh-seo-report-app .ssr-card h3 { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.75rem; margin-top: 0; }
        .shynh-seo-report-app .ssr-big { font-size: 2.25rem; font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif; font-weight: 700; color: #111827; margin-bottom: 1.25rem; line-height: 1; }
        .shynh-seo-report-app .ssr-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; padding-top: 0.625rem; padding-bottom: 0.625rem; border-top: 1px solid #f9fafb; color: #4b5563; }
        
        /* Badges */
        .shynh-seo-report-app .ssr-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 40px; padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 700; color: #ffffff; border-radius: 9999px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shynh-seo-report-app .ssr-green { background-image: linear-gradient(to right, #10b981, #059669); }
        .shynh-seo-report-app .ssr-orange { background-image: linear-gradient(to right, #f59e0b, #d97706); }
        .shynh-seo-report-app .ssr-red { background-image: linear-gradient(to right, #f43f5e, #e11d48); }
        .shynh-seo-report-app .ssr-gray { background-color: #9ca3af; }
        
        /* Tables */
        .shynh-seo-report-app table.ssr-table { width: 100%; text-align: left; font-size: 0.875rem; white-space: normal; border-collapse: collapse; }
        .shynh-seo-report-app .ssr-table th { background-color: rgba(249, 250, 251, 0.5); padding: 1rem 1.25rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #f3f4f6; text-transform: uppercase; letter-spacing: 0.05em; font-size: 11px; }
        .shynh-seo-report-app .ssr-table td { padding: 1rem 1.25rem; border-bottom: 1px solid #f9fafb; vertical-align: top; color: #374151; transition: background-color 0.2s; }
        .shynh-seo-report-app .ssr-table tr:last-child td { border-bottom-width: 0; }
        .shynh-seo-report-app .ssr-table tbody tr:hover td { background-color: rgba(249, 250, 251, 0.5); }
        .shynh-seo-report-app .ssr-muted { color: #9ca3af; font-style: italic; font-size: 0.75rem; }
        .shynh-seo-report-app .ssr-issue { margin-top: 0.375rem; font-size: 0.75rem; color: #b45309; background-color: #fffbeb; padding: 0.25rem 0.5rem; border-radius: 0.25rem; border: 1px solid #fef3c7; display: inline-block; }
        
        /* Pager */
        .shynh-seo-report-app .ssr-pager { display: flex; gap: 0.5rem; align-items: center; margin-top: 1.5rem; justify-content: flex-end; padding: 1rem; border-top: 1px solid #f3f4f6; background-color: rgba(249, 250, 251, 0.3); }
        .shynh-seo-report-app .ssr-pager button { padding: 0.375rem 0.75rem; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.25rem; font-size: 0.875rem; color: #4b5563; transition: all 0.2s; cursor: pointer; }
        .shynh-seo-report-app .ssr-pager button:hover:not(:disabled) { background-color: #f9fafb; color: #111827; }
        .shynh-seo-report-app .ssr-pager button:disabled { opacity: 0.5; cursor: not-allowed; }
        </style>
        <?php
    }
}
