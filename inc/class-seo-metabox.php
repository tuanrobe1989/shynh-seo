<?php
/**
 * Shynh SEO Meta Box for editing post SEO settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Metabox {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_box' ] );
    }

    public function add_meta_box() {
        $post_types = Shynh_SEO_Core::get_seo_post_types();
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'shynh_seo_metabox',
                __( 'Shynh SEO', 'shynh-seo' ) . ' <span id="shynh-seo-title-badge" class="shynh-seo-badge shynh-seo-badge--gray" style="margin-left:10px;">Chưa phân tích</span><span id="shynh-seo-geo-title-badge" class="shynh-seo-badge shynh-seo-badge--gray" style="margin-left:6px; display:none;"></span>',
                [ $this, 'render_metabox' ],
                $post_type,
                'normal',
                'high'
            );

            add_meta_box(
                'shynh_seo_primary_category_box',
                __( 'Danh mục chính cho đường dẫn', 'shynh-seo' ),
                [ $this, 'render_primary_category_box' ],
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_primary_category_box( $post ) {
        $primary_tax = Shynh_SEO_Core::get_primary_taxonomy( $post->post_type );

        if ( ! $primary_tax ) {
            echo '<p class="description">' . esc_html__( 'Post type này chưa có taxonomy phân cấp để chọn danh mục chính.', 'shynh-seo' ) . '</p>';
            return;
        }

        $tax_obj = get_taxonomy( $primary_tax );
        $terms   = get_terms( [
            'taxonomy'   => $primary_tax,
            'hide_empty' => false,
        ] );

        $primary_category = absint( get_post_meta( $post->ID, '_shynh_seo_primary_category', true ) );
        if ( ! $primary_category ) {
            $primary_term     = Shynh_SEO_Core::get_primary_term( $post->ID, $primary_tax );
            $primary_category = $primary_term ? absint( $primary_term->term_id ) : 0;
        }

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            echo '<p class="description">' . esc_html__( 'Chưa có danh mục nào để chọn.', 'shynh-seo' ) . '</p>';
            return;
        }
        ?>
        <input type="hidden" id="shynh_seo_primary_category_sidebar_hidden" name="_shynh_seo_primary_category_sidebar" value="<?php echo esc_attr( $primary_category ); ?>" />
        <div class="shynh-seo-primary-category-box" data-primary-taxonomy="<?php echo esc_attr( $primary_tax ); ?>">
            <label for="shynh_seo_primary_category_sidebar"><?php echo esc_html( $tax_obj ? $tax_obj->labels->singular_name : __( 'Danh mục', 'shynh-seo' ) ); ?></label>
            <select id="shynh_seo_primary_category_sidebar">
                <option value=""><?php esc_html_e( 'Tự động chọn danh mục phù hợp', 'shynh-seo' ); ?></option>
                <?php foreach ( $terms as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $primary_category, $term->term_id ); ?>>
                        <?php echo esc_html( $this->get_term_option_label( $term ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e( 'Danh mục chính được dùng cho breadcrumb, permalink, canonical URL và sitemap. Nếu danh mục chưa được tick, hệ thống sẽ tự gán khi lưu bài.', 'shynh-seo' ); ?></p>
        </div>
        <script>
        jQuery(function($) {
            var primaryTax = '<?php echo esc_js( $primary_tax ); ?>';
            var labels = [
                '<?php echo esc_js( $tax_obj ? $tax_obj->labels->name : '' ); ?>',
                '<?php echo esc_js( $tax_obj ? $tax_obj->labels->singular_name : '' ); ?>'
            ].filter(Boolean);

            function normalizeText(value) {
                return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function findClassicTaxonomyBox() {
                var selectors = ['#taxonomy-' + primaryTax, '#' + primaryTax + 'div'];
                if (primaryTax === 'category') {
                    selectors.push('#categorydiv');
                }

                return $(selectors.join(', ')).first();
            }

            function findBlockEditorTaxonomyPanel() {
                var $direct = $('[class*="taxonomy-panel-' + primaryTax + '"], [data-taxonomy="' + primaryTax + '"]').closest('.components-panel__body').first();
                if ($direct.length) {
                    return $direct;
                }

                var normalizedLabels = labels.map(normalizeText);
                var $matched = $();

                $('.components-panel__body').each(function() {
                    var $panel = $(this);
                    var panelTitle = normalizeText($panel.find('.components-panel__body-title').first().text());

                    if (!panelTitle) {
                        return;
                    }

                    var isMatch = normalizedLabels.some(function(label) {
                        return label && panelTitle.indexOf(label) !== -1;
                    });

                    if (isMatch) {
                        $matched = $panel;
                        return false;
                    }
                });

                return $matched;
            }

            function syncPrimaryValue(value) {
                $('#shynh_seo_primary_category_sidebar_hidden').val(value);
                $('#shynh_seo_primary_category_sidebar, #shynh_seo_primary_category').not(document.activeElement).val(value);
            }

            function movePrimaryControl() {
                var $control = $('.shynh-seo-primary-category-box[data-primary-taxonomy="' + primaryTax + '"]').first();
                if (!$control.length || $control.data('placed')) {
                    return true;
                }

                var $blockPanel = findBlockEditorTaxonomyPanel();
                if ($blockPanel.length) {
                    var $blockAnchor = $blockPanel.find('.editor-post-taxonomies__hierarchical-terms-list, .components-form-token-field, .components-base-control').last();
                    $control.addClass('shynh-seo-primary-category-box--inline');

                    if ($blockAnchor.length) {
                        $control.insertAfter($blockAnchor);
                    } else {
                        $blockPanel.append($control);
                    }

                    $('#shynh_seo_primary_category_box').hide();
                    $control.data('placed', true);
                    return true;
                }

                var $classicBox = findClassicTaxonomyBox();
                if ($classicBox.length) {
                    var $inside = $classicBox.find('.inside').first();
                    var $insertAfter = $classicBox.find('.tabs-panel, .categorydiv').last();
                    $control.addClass('shynh-seo-primary-category-box--inline');

                    if ($insertAfter.length) {
                        $control.insertAfter($insertAfter);
                    } else if ($inside.length) {
                        $inside.append($control);
                    } else {
                        $classicBox.append($control);
                    }

                    $('#shynh_seo_primary_category_box').hide();
                    $control.data('placed', true);
                    return true;
                }

                return false;
            }

            syncPrimaryValue($('#shynh_seo_primary_category_sidebar').val());
            movePrimaryControl();

            $(document).on('change', '#shynh_seo_primary_category_sidebar, #shynh_seo_primary_category', function() {
                syncPrimaryValue($(this).val());
            });

            var observer = new MutationObserver(function() {
                if (movePrimaryControl()) {
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            setTimeout(function() {
                observer.disconnect();
            }, 15000);
        });
        </script>
        <?php
    }

    private function get_term_option_label( $term ) {
        $ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );

        return str_repeat( '- ', count( $ancestors ) ) . $term->name;
    }

    public function render_metabox( $post ) {
        wp_nonce_field( 'shynh_seo_save_meta', 'shynh_seo_meta_nonce' );

        $focus_keyword    = get_post_meta( $post->ID, '_shynh_seo_focus_keyword', true );
        $seo_title        = get_post_meta( $post->ID, '_shynh_seo_title', true );
        $meta_desc        = get_post_meta( $post->ID, '_shynh_seo_meta_desc', true );
        
        $fb_title         = get_post_meta( $post->ID, '_shynh_seo_fb_title', true );
        $fb_desc          = get_post_meta( $post->ID, '_shynh_seo_fb_desc', true );
        $fb_image         = get_post_meta( $post->ID, '_shynh_seo_fb_image', true );
        
        $tw_title         = get_post_meta( $post->ID, '_shynh_seo_tw_title', true );
        $tw_desc          = get_post_meta( $post->ID, '_shynh_seo_tw_desc', true );
        $tw_image         = get_post_meta( $post->ID, '_shynh_seo_tw_image', true );
        
        $canonical        = get_post_meta( $post->ID, '_shynh_seo_canonical', true );
        $robots_index     = get_post_meta( $post->ID, '_shynh_seo_robots_index', true );
        $robots_follow    = get_post_meta( $post->ID, '_shynh_seo_robots_follow', true );
        $robots_noarchive = get_post_meta( $post->ID, '_shynh_seo_robots_noarchive', true );
        $robots_nosnippet = get_post_meta( $post->ID, '_shynh_seo_robots_nosnippet', true );
        $robots_noimageindex = get_post_meta( $post->ID, '_shynh_seo_robots_noimageindex', true );
        $robots_max_snippet = get_post_meta( $post->ID, '_shynh_seo_robots_max_snippet', true );
        $robots_max_image_preview = get_post_meta( $post->ID, '_shynh_seo_robots_max_image_preview', true );
        $robots_max_video_preview = get_post_meta( $post->ID, '_shynh_seo_robots_max_video_preview', true );
        $breadcrumb_title = get_post_meta( $post->ID, '_shynh_seo_breadcrumb_title', true );
        $cornerstone      = get_post_meta( $post->ID, '_shynh_seo_cornerstone', true );
        $primary_category = get_post_meta( $post->ID, '_shynh_seo_primary_category', true );
        $schema_type      = get_post_meta( $post->ID, '_shynh_seo_schema_type', true );
        $schema_faq       = get_post_meta( $post->ID, '_shynh_seo_schema_faq', true );
        $schema_service   = get_post_meta( $post->ID, '_shynh_seo_schema_service_name', true );
        $geo_aeo_mode     = get_post_meta( $post->ID, '_shynh_seo_geo_aeo_mode', true );
        $medical_reviewed = get_post_meta( $post->ID, '_shynh_seo_schema_medical_reviewed', true );
        $reviewed_by      = get_post_meta( $post->ID, '_shynh_seo_schema_reviewed_by', true );
        $reviewed_date    = get_post_meta( $post->ID, '_shynh_seo_schema_reviewed_date', true );
        $internal_links   = class_exists( 'Shynh_SEO_Internal_Linking' ) ? Shynh_SEO_Internal_Linking::get_internal_link_items( $post->ID ) : [];
        $schema_geo_ui    = $this->get_schema_geo_ui( $post, 'post' );

        ?>
        <div class="shynh-seo-wrap" style="margin: 0; max-width: 100%;">
            
            <ul class="shynh-seo-htabs-nav">
                <li class="active" data-tab="seo-tab-basic"><?php esc_html_e( 'SEO Cơ Bản', 'shynh-seo' ); ?></li>
                <li data-tab="seo-tab-social"><?php esc_html_e( 'Mạng Xã Hội', 'shynh-seo' ); ?></li>
                <li data-tab="seo-tab-advanced"><?php esc_html_e( 'Nâng Cao', 'shynh-seo' ); ?></li>
                <li data-tab="seo-tab-schema"><?php esc_html_e( 'Schema/GEO', 'shynh-seo' ); ?></li>
                <li data-tab="seo-tab-links"><?php esc_html_e( 'Internal Linking', 'shynh-seo' ); ?></li>
            </ul>

            <div class="shynh-seo-htabs-content">
                
                <!-- TAB 1: SEO Cơ Bản -->
                <div id="seo-tab-basic" class="shynh-seo-htab-pane active">
                    <div class="shynh-seo-split">
                        
                        <!-- Left Side: Inputs -->
                        <div class="shynh-seo-inputs-side">
                            <div class="shynh-seo-field">
                                <div class="shynh-seo-field-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <label for="shynh_seo_focus_keyword" style="margin:0;"><?php esc_html_e( 'Focus Keyword', 'shynh-seo' ); ?></label>
                                    <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="keyword" title="Gợi ý từ khóa AI ✨">✨ AI Gợi Ý</button>
                                </div>
                                <input type="text" id="shynh_seo_focus_keyword" name="_shynh_seo_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" placeholder="<?php esc_attr_e( 'Nhập từ khóa chính...', 'shynh-seo' ); ?>" />
                            </div>

                            <div class="shynh-seo-field">
                                <div class="shynh-seo-field-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <label for="shynh_seo_title" style="margin:0;"><?php esc_html_e( 'SEO Title', 'shynh-seo' ); ?></label>
                                    <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="title" title="Sinh tiêu đề AI ✨">✨ AI Sinh Tiêu Đề</button>
                                </div>
                                <input type="text" id="shynh_seo_title" name="_shynh_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" />
                                <div class="shynh-seo-var-hints" data-target="#shynh_seo_title">
                                    <span class="shynh-seo-var-badge">%%title%%</span>
                                    <span class="shynh-seo-var-badge">%%sitename%%</span>
                                    <span class="shynh-seo-var-badge">%%sitedesc%%</span>
                                    <span class="shynh-seo-var-badge">%%sep%%</span>
                                    <span class="shynh-seo-var-badge">%%category%%</span>
                                    <span class="shynh-seo-var-badge">%%date%%</span>
                                    <span class="shynh-seo-var-badge">%%page%%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                    <p class="description" style="margin:0;"><?php esc_html_e( 'Để trống để sử dụng tiêu đề bài viết.', 'shynh-seo' ); ?></p>
                                    <small id="shynh-seo-title-count" style="color: var(--shynh-text-muted);">0 / 60</small>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <div class="shynh-seo-field-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <label for="shynh_seo_meta_desc" style="margin:0;"><?php esc_html_e( 'Meta Description', 'shynh-seo' ); ?></label>
                                    <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="meta_desc" title="Sinh mô tả AI ✨">✨ AI Sinh Mô Tả</button>
                                </div>
                                <textarea id="shynh_seo_meta_desc" name="_shynh_seo_meta_desc" rows="3"><?php echo esc_textarea( $meta_desc ); ?></textarea>
                                <div class="shynh-seo-var-hints" data-target="#shynh_seo_meta_desc">
                                    <span class="shynh-seo-var-badge">%%title%%</span>
                                    <span class="shynh-seo-var-badge">%%sitename%%</span>
                                    <span class="shynh-seo-var-badge">%%sitedesc%%</span>
                                    <span class="shynh-seo-var-badge">%%sep%%</span>
                                    <span class="shynh-seo-var-badge">%%category%%</span>
                                    <span class="shynh-seo-var-badge">%%excerpt%%</span>
                                    <span class="shynh-seo-var-badge">%%date%%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                    <p class="description" style="margin:0;"><?php esc_html_e( 'Để trống để tự động lấy từ đoạn văn đầu.', 'shynh-seo' ); ?></p>
                                    <small id="shynh-seo-desc-count" style="color: var(--shynh-text-muted);">0 / 160</small>
                                </div>
                            </div>
                            
                            <!-- Google Snippet Preview -->
                            <div style="margin-top: 32px;">
                                <label style="display:block; font-weight: 600; margin-bottom: 12px; color: var(--shynh-dark); font-size: 14px;"><?php esc_html_e( 'Xem trước kết quả tìm kiếm Google', 'shynh-seo' ); ?></label>
                                <div class="shynh-seo-google-preview">
                                    <div class="sgp-url"><?php echo esc_url( get_permalink( $post ) ); ?></div>
                                    <div class="sgp-title" id="sgp-title-preview"><?php echo esc_html( $seo_title ? $seo_title : get_the_title( $post ) ); ?></div>
                                    <div class="sgp-desc" id="sgp-desc-preview"><?php echo esc_html( $meta_desc ? $meta_desc : 'Bản xem trước mô tả meta description sẽ hiển thị tại đây khi bạn cập nhật nội dung...' ); ?></div>
                                </div>
                            </div>

                            <div class="shynh-seo-field" style="margin-top: 30px; border-top: 1px solid var(--shynh-border-gray); padding-top: 20px;">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" id="shynh_seo_cornerstone" name="_shynh_seo_cornerstone" value="1" <?php checked( $cornerstone, '1' ); ?> style="margin-right: 8px;" />
                                    <strong><?php esc_html_e( 'Đánh dấu đây là Nội Dung Nền Tảng (Cornerstone Content)', 'shynh-seo' ); ?></strong>
                                </label>
                                <p class="description" style="margin-top: 5px;"><?php esc_html_e( 'Nội dung nền tảng là bài viết quan trọng nhất, toàn diện nhất trên trang web của bạn. Thuật toán phân tích SEO sẽ khắt khe hơn đối với loại bài viết này.', 'shynh-seo' ); ?></p>
                            </div>
                        </div>

                        <!-- Right Side: Analysis Results -->
                        <div class="shynh-seo-analysis-side">
                            <div class="shynh-seo-analysis-box">
                                <div id="shynh-seo-score-panel">
                                    <p style="text-align: center; color: var(--shynh-text-muted); font-size: 13px;">
                                        <?php esc_html_e( 'Đang phân tích bài viết...', 'shynh-seo' ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 2: Mạng Xã Hội -->
                <div id="seo-tab-social" class="shynh-seo-htab-pane">
                    
                    <h4 style="margin-top:0; color: var(--shynh-dark); border-bottom: 1px solid var(--shynh-border-gray); padding-bottom: 12px;"><span class="dashicons dashicons-facebook-alt" style="color: #1877F2;"></span> Facebook / Zalo</h4>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_fb_title"><?php esc_html_e( 'Facebook Title', 'shynh-seo' ); ?></label>
                        <input type="text" id="shynh_seo_fb_title" name="_shynh_seo_fb_title" value="<?php echo esc_attr( $fb_title ); ?>" placeholder="<?php esc_attr_e( 'Để trống để sử dụng SEO Title...', 'shynh-seo' ); ?>" />
                    </div>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_fb_desc"><?php esc_html_e( 'Facebook Description', 'shynh-seo' ); ?></label>
                        <textarea id="shynh_seo_fb_desc" name="_shynh_seo_fb_desc" rows="2" placeholder="<?php esc_attr_e( 'Để trống để sử dụng Meta Description...', 'shynh-seo' ); ?>"><?php echo esc_textarea( $fb_desc ); ?></textarea>
                    </div>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_fb_image"><?php esc_html_e( 'Facebook Image', 'shynh-seo' ); ?> <span style="font-weight:normal; color:var(--shynh-text-muted); font-size:12px;">(Kích thước chuẩn: 1200 x 630 px)</span></label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="shynh_seo_fb_image" name="_shynh_seo_fb_image" value="<?php echo esc_attr( $fb_image ); ?>" placeholder="https://" />
                            <button type="button" class="shynh-seo-btn shynh-seo-btn--secondary shynh-seo-media-upload-btn" data-target="#shynh_seo_fb_image"><?php esc_html_e( 'Chọn Ảnh', 'shynh-seo' ); ?></button>
                        </div>
                        <?php if ( $fb_image ) : ?>
                            <img src="<?php echo esc_url( $fb_image ); ?>" style="max-width: 200px; margin-top: 10px; border-radius: 8px; box-shadow: var(--shynh-shadow);" />
                        <?php endif; ?>
                    </div>

                    <h4 style="margin-top:40px; color: var(--shynh-dark); border-bottom: 1px solid var(--shynh-border-gray); padding-bottom: 12px;"><span class="dashicons dashicons-twitter" style="color: #1DA1F2;"></span> Twitter (X)</h4>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_tw_title"><?php esc_html_e( 'Twitter Title', 'shynh-seo' ); ?></label>
                        <input type="text" id="shynh_seo_tw_title" name="_shynh_seo_tw_title" value="<?php echo esc_attr( $tw_title ); ?>" placeholder="<?php esc_attr_e( 'Để trống để sử dụng Facebook Title...', 'shynh-seo' ); ?>" />
                    </div>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_tw_desc"><?php esc_html_e( 'Twitter Description', 'shynh-seo' ); ?></label>
                        <textarea id="shynh_seo_tw_desc" name="_shynh_seo_tw_desc" rows="2"><?php echo esc_textarea( $tw_desc ); ?></textarea>
                    </div>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_tw_image"><?php esc_html_e( 'Twitter Image', 'shynh-seo' ); ?> <span style="font-weight:normal; color:var(--shynh-text-muted); font-size:12px;">(Kích thước chuẩn: 1200 x 675 px)</span></label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="shynh_seo_tw_image" name="_shynh_seo_tw_image" value="<?php echo esc_attr( $tw_image ); ?>" />
                            <button type="button" class="shynh-seo-btn shynh-seo-btn--secondary shynh-seo-media-upload-btn" data-target="#shynh_seo_tw_image"><?php esc_html_e( 'Chọn Ảnh', 'shynh-seo' ); ?></button>
                        </div>
                    </div>

                </div>

                <!-- TAB 3: Nâng Cao -->
                <div id="seo-tab-advanced" class="shynh-seo-htab-pane">
                    
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_canonical"><?php esc_html_e( 'Canonical URL', 'shynh-seo' ); ?></label>
                        <input type="text" id="shynh_seo_canonical" name="_shynh_seo_canonical" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_url( get_permalink( $post ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Nhập link gốc nếu bài viết này được copy từ nguồn khác để tránh trùng lặp nội dung.', 'shynh-seo' ); ?></p>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_robots_index"><?php esc_html_e( 'Robots Index', 'shynh-seo' ); ?></label>
                        <select id="shynh_seo_robots_index" name="_shynh_seo_robots_index">
                            <option value="" <?php selected( $robots_index, '' ); ?>><?php esc_html_e( 'Mặc định (Index)', 'shynh-seo' ); ?></option>
                            <option value="index" <?php selected( $robots_index, 'index' ); ?>>Index</option>
                            <option value="noindex" <?php selected( $robots_index, 'noindex' ); ?>>NoIndex</option>
                        </select>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_robots_follow"><?php esc_html_e( 'Robots Follow', 'shynh-seo' ); ?></label>
                        <select id="shynh_seo_robots_follow" name="_shynh_seo_robots_follow">
                            <option value="" <?php selected( $robots_follow, '' ); ?>><?php esc_html_e( 'Mặc định (Follow)', 'shynh-seo' ); ?></option>
                            <option value="follow" <?php selected( $robots_follow, 'follow' ); ?>>Follow</option>
                            <option value="nofollow" <?php selected( $robots_follow, 'nofollow' ); ?>>NoFollow</option>
                        </select>
                    </div>

                    <div class="shynh-seo-note shynh-seo-note--warning">
                        <?php esc_html_e( 'Nếu nhập custom canonical, trang này sẽ không được đưa vào sitemap để tránh tín hiệu SEO mâu thuẫn.', 'shynh-seo' ); ?>
                    </div>

                    <div class="shynh-seo-field">
                        <label><?php esc_html_e( 'Robots nâng cao', 'shynh-seo' ); ?></label>
                        <label style="font-weight: normal; display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                            <input type="checkbox" name="_shynh_seo_robots_noarchive" value="1" <?php checked( $robots_noarchive, '1' ); ?> />
                            <span>Noarchive</span>
                        </label>
                        <label style="font-weight: normal; display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                            <input type="checkbox" name="_shynh_seo_robots_nosnippet" value="1" <?php checked( $robots_nosnippet, '1' ); ?> />
                            <span>Nosnippet</span>
                        </label>
                        <label style="font-weight: normal; display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                            <input type="checkbox" name="_shynh_seo_robots_noimageindex" value="1" <?php checked( $robots_noimageindex, '1' ); ?> />
                            <span>Noimageindex</span>
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px;">
                        <div class="shynh-seo-field">
                            <label for="shynh_seo_robots_max_snippet">Max snippet</label>
                            <input type="number" id="shynh_seo_robots_max_snippet" name="_shynh_seo_robots_max_snippet" value="<?php echo esc_attr( $robots_max_snippet ); ?>" placeholder="<?php esc_attr_e( 'Mặc định', 'shynh-seo' ); ?>" />
                        </div>
                        <div class="shynh-seo-field">
                            <label for="shynh_seo_robots_max_image_preview">Max image preview</label>
                            <select id="shynh_seo_robots_max_image_preview" name="_shynh_seo_robots_max_image_preview">
                                <option value="" <?php selected( $robots_max_image_preview, '' ); ?>><?php esc_html_e( 'Mặc định', 'shynh-seo' ); ?></option>
                                <option value="none" <?php selected( $robots_max_image_preview, 'none' ); ?>>None</option>
                                <option value="standard" <?php selected( $robots_max_image_preview, 'standard' ); ?>>Standard</option>
                                <option value="large" <?php selected( $robots_max_image_preview, 'large' ); ?>>Large</option>
                            </select>
                        </div>
                        <div class="shynh-seo-field">
                            <label for="shynh_seo_robots_max_video_preview">Max video preview</label>
                            <input type="number" id="shynh_seo_robots_max_video_preview" name="_shynh_seo_robots_max_video_preview" value="<?php echo esc_attr( $robots_max_video_preview ); ?>" placeholder="<?php esc_attr_e( 'Mặc định', 'shynh-seo' ); ?>" />
                        </div>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_breadcrumb_title"><?php esc_html_e( 'Tiêu đề Breadcrumb', 'shynh-seo' ); ?></label>
                        <input type="text" id="shynh_seo_breadcrumb_title" name="_shynh_seo_breadcrumb_title" value="<?php echo esc_attr( $breadcrumb_title ); ?>" placeholder="<?php esc_attr_e( 'Tiêu đề ngắn dùng cho menu điều hướng...', 'shynh-seo' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Sử dụng tiêu đề ngắn gọn cho Breadcrumb thay vì tiêu đề bài viết dài.', 'shynh-seo' ); ?></p>
                    </div>

                    <?php 
                    // Support Custom Post Types with Hierarchical Taxonomies (Categories)
                    $primary_tax = Shynh_SEO_Core::get_primary_taxonomy( $post->post_type );

                    if ( $primary_tax ) :
                        $terms = get_terms( [
                            'taxonomy'   => $primary_tax,
                            'hide_empty' => false,
                        ] );
                        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) :
                    ?>
                    <div class="shynh-seo-field">
                        <label for="shynh_seo_primary_category"><?php esc_html_e( 'Chuyên Mục Chính (Primary Category)', 'shynh-seo' ); ?></label>
                        <select id="shynh_seo_primary_category" name="_shynh_seo_primary_category">
                            <option value=""><?php esc_html_e( 'Mặc định', 'shynh-seo' ); ?></option>
                            <?php foreach ( $terms as $term ) : ?>
                                <option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $primary_category, $term->term_id ); ?>>
                                    <?php echo esc_html( $term->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Chọn chuyên mục chính để làm Breadcrumb chuẩn cho bài viết này.', 'shynh-seo' ); ?></p>
                    </div>
                    <?php endif; endif; ?>
                </div>

                <!-- TAB 4: Schema / GEO -->
                <div id="seo-tab-schema" class="shynh-seo-htab-pane">
                    <div class="shynh-seo-note shynh-seo-note--info">
                        <?php echo esc_html( $schema_geo_ui['intro'] ); ?>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_geo_aeo_mode"><?php echo esc_html( $schema_geo_ui['geo_label'] ); ?></label>
                        <select id="shynh_seo_geo_aeo_mode" name="_shynh_seo_geo_aeo_mode">
                            <option value="" <?php selected( $geo_aeo_mode, '' ); ?>><?php echo esc_html( $schema_geo_ui['geo_options']['inherit'] ); ?></option>
                            <option value="on" <?php selected( $geo_aeo_mode, 'on' ); ?>><?php echo esc_html( $schema_geo_ui['geo_options']['on'] ); ?></option>
                            <option value="off" <?php selected( $geo_aeo_mode, 'off' ); ?>><?php echo esc_html( $schema_geo_ui['geo_options']['off'] ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html( $schema_geo_ui['geo_note'] ); ?></p>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_schema_type"><?php echo esc_html( $schema_geo_ui['schema_type_label'] ); ?></label>
                        <select id="shynh_seo_schema_type" name="_shynh_seo_schema_type">
                            <?php foreach ( $schema_geo_ui['schema_options'] as $value => $opt ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?> title="<?php echo esc_attr( $opt['desc'] ); ?>"><?php echo esc_html( $opt['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html( $schema_geo_ui['schema_type_note'] ); ?></p>
                        <div class="shynh-seo-schema-guide" aria-label="<?php echo esc_attr( $schema_geo_ui['schema_guide_label'] ); ?>">
                            <?php foreach ( $schema_geo_ui['schema_options'] as $opt ) : ?>
                                <div class="shynh-seo-schema-guide__item">
                                    <strong><?php echo esc_html( $opt['label'] ); ?></strong>
                                    <span><?php echo esc_html( $opt['desc'] ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="shynh-seo-field">
                        <label for="shynh_seo_schema_service_name"><?php echo esc_html( $schema_geo_ui['service_name_label'] ); ?></label>
                        <input type="text" id="shynh_seo_schema_service_name" name="_shynh_seo_schema_service_name" value="<?php echo esc_attr( $schema_service ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
                        <p class="description"><?php echo esc_html( $schema_geo_ui['service_name_note'] ); ?></p>
                    </div>

                    <?php $schema_extra_fields = $schema_geo_ui['extra_fields']; ?>
                    <details class="shynh-seo-field" style="margin-top:8px;">
                        <summary style="cursor:pointer;font-weight:600;"><?php echo esc_html( $schema_geo_ui['advanced_summary'] ); ?></summary>
                        <p class="description"><?php echo esc_html( $schema_geo_ui['advanced_note'] ); ?></p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px;">
                            <?php foreach ( $schema_extra_fields as $key => $meta ) :
                                $val = get_post_meta( $post->ID, $key, true ); ?>
                                <div class="shynh-seo-field">
                                    <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta['label'] ); ?></label>
                                    <input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $meta['placeholder'] ); ?>" />
                                    <p class="description"><?php echo esc_html( $meta['note'] ); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <?php $this->render_faq_builder( $schema_faq, 'shynh_seo_schema_faq_post', $schema_geo_ui ); ?>

                    <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px;">
                        <label class="shynh-seo-switch">
                            <input type="checkbox" name="_shynh_seo_schema_medical_reviewed" value="1" <?php checked( $medical_reviewed, '1' ); ?> />
                            <span class="shynh-seo-slider"></span>
                        </label>
                        <div>
                            <strong><?php echo esc_html( $schema_geo_ui['medical_reviewed_label'] ); ?></strong>
                            <p class="description" style="margin:4px 0 0;"><?php echo esc_html( $schema_geo_ui['medical_reviewed_note'] ); ?></p>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px;">
                        <div class="shynh-seo-field">
                            <label for="shynh_seo_schema_reviewed_by"><?php echo esc_html( $schema_geo_ui['reviewed_by_label'] ); ?></label>
                            <input type="text" id="shynh_seo_schema_reviewed_by" name="_shynh_seo_schema_reviewed_by" value="<?php echo esc_attr( $reviewed_by ); ?>" />
                            <p class="description"><?php echo esc_html( $schema_geo_ui['reviewed_by_note'] ); ?></p>
                        </div>
                        <div class="shynh-seo-field">
                            <label for="shynh_seo_schema_reviewed_date"><?php echo esc_html( $schema_geo_ui['reviewed_date_label'] ); ?></label>
                            <input type="text" id="shynh_seo_schema_reviewed_date" name="_shynh_seo_schema_reviewed_date" value="<?php echo esc_attr( $reviewed_date ); ?>" placeholder="YYYY-MM-DD" />
                            <p class="description"><?php echo esc_html( $schema_geo_ui['reviewed_date_note'] ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: Internal Linking -->
                <div id="seo-tab-links" class="shynh-seo-htab-pane">
                    <div class="shynh-seo-internal-layout">
                        <section class="shynh-seo-link-card shynh-seo-link-card--manual">
                            <div class="shynh-seo-link-card__head">
                                <div>
                                    <span class="shynh-seo-link-eyebrow"><?php esc_html_e( 'Internal Links', 'shynh-seo' ); ?></span>
                                    <h3><?php esc_html_e( 'Internal Link', 'shynh-seo' ); ?></h3>
                                    <p><?php esc_html_e( 'Chọn các bài cần liên kết nội bộ. Bài viết liên quan cuối nội dung sẽ lấy từ danh sách Internal Link này.', 'shynh-seo' ); ?></p>
                                </div>
                                <span class="shynh-seo-link-pill"><?php esc_html_e( 'Auto append', 'shynh-seo' ); ?></span>
                            </div>

                            <input type="hidden" id="shynh_seo_internal_link_posts" name="_shynh_seo_internal_link_posts" value="<?php echo esc_attr( wp_json_encode( $internal_links, JSON_UNESCAPED_UNICODE ) ); ?>" />

                            <div class="shynh-seo-related-workspace">
                                <div class="shynh-seo-related-controls">
                                    <div class="shynh-seo-related-filters">
                                        <div class="shynh-seo-related-filter">
                                            <label for="shynh-seo-related-post-type-filter"><?php esc_html_e( 'Post Type', 'shynh-seo' ); ?></label>
                                            <select id="shynh-seo-related-post-type-filter"></select>
                                        </div>
                                        <div class="shynh-seo-related-filter">
                                            <label for="shynh-seo-related-tax-filter"><?php esc_html_e( 'Taxonomy', 'shynh-seo' ); ?></label>
                                            <select id="shynh-seo-related-tax-filter"></select>
                                        </div>
                                    </div>

                                    <div id="shynh-seo-related-search-results" class="shynh-seo-related-results">
                                        <p class="description">Nh&#7853;p t&#7915; kh&#243;a &#273;&#7875; t&#236;m b&#224;i vi&#7871;t c&#249;ng ng&#244;n ng&#7919;.</p>
                                    </div>
                                </div>

                                <div class="shynh-seo-related-right-rail">
                                    <div class="shynh-seo-related-search">
                                        <input type="search" id="shynh-seo-related-search-input" placeholder="T&#236;m internal link..." />
                                        <button type="button" class="button shynh-seo-btn shynh-seo-btn--secondary" id="shynh-seo-related-search-btn">T&#236;m</button>
                                    </div>

                                    <div class="shynh-seo-related-selected">
                                        <div class="shynh-seo-related-selected__head">
                                            <strong>Internal Link &#273;&#227; ch&#7885;n</strong>
                                            <span>K&#233;o &#273;&#7875; s&#7855;p x&#7871;p</span>
                                        </div>
                                        <div id="shynh-seo-related-selected-list" class="shynh-seo-related-selected__list"></div>
                                    </div>
                                </div>
                            </div>

                            <template id="shynh-seo-related-row-template">
                                <div class="shynh-seo-related-row" draggable="true">
                                    <button type="button" class="button shynh-seo-related-drag" aria-label="<?php esc_attr_e( 'Move related post', 'shynh-seo' ); ?>"><span class="dashicons dashicons-move"></span></button>
                                    <div class="shynh-seo-related-row__main">
                                        <strong class="shynh-seo-related-row__title"></strong>
                                        <a class="shynh-seo-related-row__url" href="#" target="_blank" rel="noopener"></a>
                                        <input type="text" class="shynh-seo-related-row__custom-title" placeholder="<?php esc_attr_e( 'Custom display title (optional)', 'shynh-seo' ); ?>" />
                                    </div>
                                    <span class="shynh-seo-related-row__lang"></span>
                                    <button type="button" class="button shynh-seo-related-remove"><?php esc_html_e( 'Xóa', 'shynh-seo' ); ?></button>
                                </div>
                            </template>
                        </section>

                        <aside class="shynh-seo-link-card shynh-seo-link-card--preview">
                            <span class="shynh-seo-link-eyebrow"><?php esc_html_e( 'Preview', 'shynh-seo' ); ?></span>
                            <div class="shynh-seo-related-preview">
                                <div class="shynh-seo-related-preview__dash"></div>
                                <h4 id="shynh-seo-related-preview-title"><?php esc_html_e( 'Nội dung liên quan', 'shynh-seo' ); ?></h4>
                                <ul id="shynh-seo-related-preview-list"></ul>
                            </div>
                            <p class="description"><?php esc_html_e( 'Frontend sẽ tự chọn H3/H4/H5 dựa theo heading cuối cùng trong bài và đổi nhãn theo ngôn ngữ.', 'shynh-seo' ); ?></p>
                        </aside>
                    </div>

                    <div class="shynh-seo-link-card" style="margin-top:18px;">
                        <div class="shynh-seo-link-card__head">
                            <div>
                                <span class="shynh-seo-link-eyebrow"><?php esc_html_e( 'Suggestions', 'shynh-seo' ); ?></span>
                                <h3><?php esc_html_e( 'Gợi ý internal link theo Focus Keyword', 'shynh-seo' ); ?></h3>
                                <p><?php esc_html_e( 'Dùng để copy link chèn thủ công trong thân bài khi cần anchor tự nhiên hơn.', 'shynh-seo' ); ?></p>
                            </div>
                        </div>
                        <div id="shynh-seo-linking-panel">
                            <div style="padding: 30px; text-align: center; border: 1px dashed var(--shynh-border-gray); border-radius: 8px;">
                                <?php esc_html_e( 'Đang tải dữ liệu liên kết...', 'shynh-seo' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Horizontal Tabs
            $('.shynh-seo-htabs-nav li').on('click', function() {
                $('.shynh-seo-htabs-nav li').removeClass('active');
                $(this).addClass('active');
                
                $('.shynh-seo-htab-pane').removeClass('active');
                $('#' + $(this).data('tab')).addClass('active');
            });

            // Media Uploader
            var frame;
            $('.shynh-seo-media-upload-btn').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                
                if (frame) {
                    frame.open();
                    return;
                }
                
                frame = wp.media({
                    title: 'Chọn ảnh',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $(target).val(attachment.url);
                });
                
                frame.open();
            });

            // Insert Snippet Variable
            $('.shynh-seo-var-badge').on('click', function() {
                var targetId = $(this).parent().data('target');
                var $input = $(targetId);
                var text = $(this).text();
                
                if ($input.length) {
                    var currentVal = $input.val();
                    // Basic insert at end. For caret pos, more complex logic is needed.
                    $input.val(currentVal + (currentVal ? ' ' : '') + text).trigger('input');
                    $input.focus();
                }
            });

            function syncFaqBuilder($builder) {
                var items = [];
                $builder.find('.shynh-seo-faq-row').each(function() {
                    var question = $.trim($(this).find('.shynh-seo-faq-question').val() || '');
                    var answer = $.trim($(this).find('.shynh-seo-faq-answer').val() || '');
                    if (question || answer) {
                        items.push({ question: question, answer: answer });
                    }
                });
                $builder.find('.shynh-seo-faq-json').val(JSON.stringify(items)).trigger('input');
            }

            function addFaqRow($builder, question, answer) {
                var template = $builder.find('.shynh-seo-faq-template').html();
                var $row = $(template);
                $row.find('.shynh-seo-faq-question').val(question || '');
                $row.find('.shynh-seo-faq-answer').val(answer || '');
                $builder.find('.shynh-seo-faq-rows').append($row);
                syncFaqBuilder($builder);
            }

            function initFaqSortable($builder) {
                var $rows = $builder.find('.shynh-seo-faq-rows');
                if (!$rows.length) {
                    return;
                }

                if ($rows.data('shynhSortableReady')) {
                    return;
                }

                var draggedRow = null;

                $rows.on('mousedown touchstart', '.shynh-seo-faq-drag', function() {
                    $rows.data('shynhDragHandleActive', true);
                    $(this).closest('.shynh-seo-faq-row').attr('draggable', 'true');
                });

                $(document).on('mouseup touchend', function() {
                    $rows.data('shynhDragHandleActive', false);
                });

                $rows.on('dragstart', '.shynh-seo-faq-row', function(e) {
                    if (!$rows.data('shynhDragHandleActive')) {
                        e.preventDefault();
                        return;
                    }

                    draggedRow = this;
                    $(this).addClass('shynh-seo-faq-row--dragging');
                    if (e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.effectAllowed = 'move';
                        e.originalEvent.dataTransfer.setData('text/plain', '');
                    }
                });

                $rows.on('dragover', '.shynh-seo-faq-row', function(e) {
                    if (!draggedRow || draggedRow === this) {
                        return;
                    }

                    e.preventDefault();
                    var rect = this.getBoundingClientRect();
                    var before = e.originalEvent.clientY < rect.top + (rect.height / 2);

                    if (before) {
                        this.parentNode.insertBefore(draggedRow, this);
                    } else {
                        this.parentNode.insertBefore(draggedRow, this.nextSibling);
                    }
                });

                $rows.on('dragend drop', '.shynh-seo-faq-row', function() {
                    if (draggedRow) {
                        $(draggedRow).removeClass('shynh-seo-faq-row--dragging').removeAttr('draggable');
                        draggedRow = null;
                        syncFaqBuilder($builder);
                    }
                });

                $rows.data('shynhSortableReady', true);
            }

            function stripHtml(value) {
                return $('<div>').html(String(value || '')).text().replace(/\s+/g, ' ').trim();
            }

            function getQuestionsFromText(value) {
                value = stripHtml(value);
                if (!value) {
                    return [];
                }

                var questions = [];
                var chunks = value.split('?');

                for (var i = 0; i < chunks.length - 1; i++) {
                    var question = $.trim(chunks[i]);
                    if (!question) {
                        continue;
                    }

                    question = question.replace(/^[\s\.\!\;:,\-]+/, '').replace(/\s+/g, ' ');
                    if (question.length >= 6) {
                        questions.push(question + '?');
                    }
                }

                return questions;
            }

            function getBlockHtmlValues(block) {
                var attrs = block.attributes || {};
                var values = [];

                collectStringValues(attrs, values, 0);

                [
                    'originalContent',
                    'saveContent',
                    'innerHTML',
                    'renderedHtml'
                ].forEach(function(key) {
                    if (typeof block[key] === 'string') {
                        values.push(block[key]);
                    }
                });

                return values;
            }

            function collectStringValues(value, output, depth) {
                if (depth > 6 || value === null || typeof value === 'undefined') {
                    return;
                }

                if (typeof value === 'string') {
                    if (value.indexOf('?') !== -1 || value.indexOf('<') !== -1) {
                        output.push(value);
                    }
                    return;
                }

                if (Array.isArray(value)) {
                    value.forEach(function(item) {
                        collectStringValues(item, output, depth + 1);
                    });
                    return;
                }

                if (typeof value === 'object') {
                    Object.keys(value).forEach(function(key) {
                        if (/^(url|href|src|id|className|anchor|style)$/i.test(key)) {
                            return;
                        }
                        collectStringValues(value[key], output, depth + 1);
                    });
                }
            }

            function getQuestionsFromHtml(value) {
                var questions = [];
                var html = String(value || '');

                if (html.indexOf('<') === -1) {
                    return getQuestionsFromText(html);
                }

                var $root = $('<div>').html(html);
                var selector = 'h1,h2,h3,h4,h5,h6,strong,b,summary,dt,li,figcaption,blockquote,p';

                $root.find(selector).each(function() {
                    getQuestionsFromText($(this).text()).forEach(function(question) {
                        questions.push(question);
                    });
                });

                if (!questions.length) {
                    questions = getQuestionsFromText($root.text());
                }

                return questions;
            }

            function getQuestionsFromBlock(block) {
                var questions = [];
                getBlockHtmlValues(block).forEach(function(value) {
                    getQuestionsFromHtml(value).forEach(function(question) {
                        questions.push(question);
                    });
                });

                if (!questions.length) {
                    questions = getQuestionsFromText(getBlockText(block));
                }

                return questions;
            }

            function pushUniqueFaq(output, seen, question, answer) {
                question = stripHtml(question);
                answer = stripHtml(answer);

                if (!question) {
                    return;
                }

                var key = question.toLowerCase();
                if (seen[key]) {
                    return;
                }

                seen[key] = true;
                output.push({ question: question, answer: answer });
            }

            function getBlockText(block) {
                return stripHtml(getBlockHtmlValues(block).join(' '));
            }

            function flattenBlocks(blocks, output) {
                (blocks || []).forEach(function(block) {
                    output.push(block);

                    if (Array.isArray(block.innerBlocks) && block.innerBlocks.length) {
                        flattenBlocks(block.innerBlocks, output);
                    }
                });
            }

            function collectFaqFromBlocks(blocks, output) {
                var seen = {};
                var flatBlocks = [];
                flattenBlocks(blocks || [], flatBlocks);

                flatBlocks.forEach(function(block, index) {
                    var attrs = block.attributes || {};
                    if (block.name === 'kp/faq' && Array.isArray(attrs.items)) {
                        attrs.items.forEach(function(item) {
                            var question = stripHtml(item && item.question);
                            var answer = stripHtml(item && item.answer);
                            pushUniqueFaq(output, seen, question, answer);
                        });
                        return;
                    }

                    var questions = getQuestionsFromBlock(block);
                    if (!questions.length) {
                        return;
                    }

                    var answers = [];
                    for (var i = index + 1; i < flatBlocks.length && answers.length < 2; i++) {
                        var nextText = getBlockText(flatBlocks[i]);
                        if (!nextText) {
                            continue;
                        }
                        if (getQuestionsFromBlock(flatBlocks[i]).length) {
                            break;
                        }
                        answers.push(nextText);
                    }

                    questions.forEach(function(question) {
                        pushUniqueFaq(output, seen, question, answers.join(' '));
                    });
                });
            }

            function getFaqItemsFromEditor() {
                var items = [];
                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                    collectFaqFromBlocks(wp.data.select('core/block-editor').getBlocks(), items);
                }
                return items;
            }

            function replaceFaqRows($builder, items) {
                $builder.find('.shynh-seo-faq-rows').empty();
                if (!items.length) {
                    addFaqRow($builder, '', '');
                    return;
                }
                items.forEach(function(item) {
                    addFaqRow($builder, item.question, item.answer);
                });
                syncFaqBuilder($builder);
                initFaqSortable($builder);
            }

            $('.shynh-seo-faq-builder').each(function() {
                var $builder = $(this);
                var hasSavedFaq = false;
                $builder.find('.shynh-seo-faq-row').each(function() {
                    if ($.trim($(this).find('.shynh-seo-faq-question').val() || '') || $.trim($(this).find('.shynh-seo-faq-answer').val() || '')) {
                        hasSavedFaq = true;
                    }
                });

                if (!hasSavedFaq) {
                    var imported = getFaqItemsFromEditor();
                    if (imported.length) {
                        replaceFaqRows($builder, imported);
                    } else if (!$builder.find('.shynh-seo-faq-row').length) {
                        addFaqRow($builder, '', '');
                    }
                }
                syncFaqBuilder($builder);
                initFaqSortable($builder);
            });

            $(document).on('click', '.shynh-seo-faq-add', function(e) {
                e.preventDefault();
                addFaqRow($(this).closest('.shynh-seo-faq-builder'), '', '');
            });

            $(document).on('click', '.shynh-seo-faq-import', function(e) {
                e.preventDefault();
                var items = getFaqItemsFromEditor();
                if (!items.length) {
                    alert('No questions found in the current content.');
                    return;
                }
                replaceFaqRows($(this).closest('.shynh-seo-faq-builder'), items);
            });

            $(document).on('click', '.shynh-seo-faq-remove', function(e) {
                e.preventDefault();
                var $builder = $(this).closest('.shynh-seo-faq-builder');
                $(this).closest('.shynh-seo-faq-row').remove();
                if (!$builder.find('.shynh-seo-faq-row').length) {
                    addFaqRow($builder, '', '');
                }
                syncFaqBuilder($builder);
            });

            $(document).on('input', '.shynh-seo-faq-question, .shynh-seo-faq-answer', function() {
                syncFaqBuilder($(this).closest('.shynh-seo-faq-builder'));
            });
        });
        </script>
        <?php
    }

    private function get_schema_geo_ui_language( $object = null ) {
        $lang = '';

        if ( $object instanceof WP_Post && function_exists( 'pll_get_post_language' ) ) {
            $lang = (string) pll_get_post_language( $object->ID );
        }

        if ( '' === $lang && function_exists( 'pll_current_language' ) ) {
            $lang = (string) pll_current_language();
        }

        if ( '' === $lang ) {
            $lang = (string) get_locale();
        }

        $lang = strtolower( str_replace( '_', '-', $lang ) );
        return ( 0 === strpos( $lang, 'en' ) ) ? 'en' : 'vi';
    }

    private function get_schema_geo_ui( $object = null, $context = 'post' ) {
        $lang = $this->get_schema_geo_ui_language( $object );
        $is_en = 'en' === $lang;

        $schema_options = $is_en
            ? [
                ''                 => [ 'label' => 'Auto', 'desc' => 'Let SHYNH SEO choose Article for posts and WebPage for other content.' ],
                'Article'          => [ 'label' => 'Article', 'desc' => 'Use for editorial articles, news, and standard knowledge content.' ],
                'BlogPosting'      => [ 'label' => 'BlogPosting', 'desc' => 'Use for blog-style content with a clear author and publish date.' ],
                'WebPage'          => [ 'label' => 'WebPage', 'desc' => 'Use for general pages without a specific service or medical intent.' ],
                'MedicalWebPage'   => [ 'label' => 'MedicalWebPage', 'desc' => 'Use for medically reviewed aesthetic or treatment pages.' ],
                'Service'          => [ 'label' => 'Service', 'desc' => 'Use for a service detail page; fill in Service Name when the H1 is not precise enough.' ],
                'MedicalProcedure' => [ 'label' => 'MedicalProcedure', 'desc' => 'Use for a concrete aesthetic procedure with body area, process, and preparation details.' ],
                'HowTo'            => [ 'label' => 'HowTo', 'desc' => 'Use only when the content explains a step-by-step process.' ],
                'Physician'        => [ 'label' => 'Physician', 'desc' => 'Use for doctor or expert profile pages.' ],
                'Review'           => [ 'label' => 'Review', 'desc' => 'Use only when a real review, rating, and reviewer are visible on the page.' ],
                'Product'          => [ 'label' => 'Product / Offer', 'desc' => 'Use for a paid package, product, or offer with visible price information.' ],
                'VideoObject'      => [ 'label' => 'VideoObject', 'desc' => 'Use when the page contains a primary embedded video.' ],
                'ImageGallery'     => [ 'label' => 'ImageGallery', 'desc' => 'Use for before/after galleries or image-led result pages.' ],
            ]
            : [
                ''                 => [ 'label' => 'Tự động', 'desc' => 'Để SHYNH SEO tự chọn Article cho bài viết và WebPage cho nội dung khác.' ],
                'Article'          => [ 'label' => 'Article', 'desc' => 'Dùng cho bài kiến thức, tin tức hoặc nội dung biên tập chuẩn.' ],
                'BlogPosting'      => [ 'label' => 'BlogPosting', 'desc' => 'Dùng cho bài blog có tác giả và ngày đăng rõ ràng.' ],
                'WebPage'          => [ 'label' => 'WebPage', 'desc' => 'Dùng cho trang thông thường, không nhấn mạnh dịch vụ hay y khoa.' ],
                'MedicalWebPage'   => [ 'label' => 'MedicalWebPage', 'desc' => 'Dùng cho trang thẩm mỹ/y khoa có nội dung được chuyên môn rà soát.' ],
                'Service'          => [ 'label' => 'Service', 'desc' => 'Dùng cho trang dịch vụ; điền Service Name nếu H1 chưa đủ rõ.' ],
                'MedicalProcedure' => [ 'label' => 'MedicalProcedure', 'desc' => 'Dùng cho liệu trình cụ thể có vùng điều trị, cách thực hiện và chuẩn bị.' ],
                'HowTo'            => [ 'label' => 'HowTo', 'desc' => 'Chỉ dùng khi nội dung trình bày theo từng bước rõ ràng.' ],
                'Physician'        => [ 'label' => 'Physician', 'desc' => 'Dùng cho trang bác sĩ hoặc chuyên gia.' ],
                'Review'           => [ 'label' => 'Review', 'desc' => 'Chỉ dùng khi review, điểm đánh giá và người đánh giá hiển thị thật trên trang.' ],
                'Product'          => [ 'label' => 'Product / Offer', 'desc' => 'Dùng cho sản phẩm, gói dịch vụ hoặc ưu đãi có thông tin giá.' ],
                'VideoObject'      => [ 'label' => 'VideoObject', 'desc' => 'Dùng khi trang có video chính được nhúng.' ],
                'ImageGallery'     => [ 'label' => 'ImageGallery', 'desc' => 'Dùng cho gallery hình ảnh hoặc hình trước/sau.' ],
            ];

        if ( 'term' === $context ) {
            $schema_options = $is_en
                ? [
                    ''               => [ 'label' => 'Auto', 'desc' => 'Let SHYNH SEO choose the safest schema for this taxonomy archive.' ],
                    'CollectionPage' => [ 'label' => 'CollectionPage', 'desc' => 'Use for a category page that groups many services or articles.' ],
                    'ItemList'       => [ 'label' => 'ItemList', 'desc' => 'Use when the archive presents an ordered or curated list.' ],
                    'FAQPage'        => [ 'label' => 'FAQPage', 'desc' => 'Use when the term page includes visible FAQ content.' ],
                    'Service'        => [ 'label' => 'Service', 'desc' => 'Use when the taxonomy represents a service group.' ],
                    'MedicalWebPage' => [ 'label' => 'MedicalWebPage', 'desc' => 'Use for medical or aesthetic treatment category pages.' ],
                    'WebPage'        => [ 'label' => 'WebPage', 'desc' => 'Use for a general taxonomy landing page.' ],
                ]
                : [
                    ''               => [ 'label' => 'Tự động', 'desc' => 'Để SHYNH SEO chọn schema an toàn nhất cho archive taxonomy này.' ],
                    'CollectionPage' => [ 'label' => 'CollectionPage', 'desc' => 'Dùng cho trang danh mục gom nhiều dịch vụ hoặc bài viết.' ],
                    'ItemList'       => [ 'label' => 'ItemList', 'desc' => 'Dùng khi archive trình bày danh sách có chọn lọc hoặc có thứ tự.' ],
                    'FAQPage'        => [ 'label' => 'FAQPage', 'desc' => 'Dùng khi trang term có FAQ hiển thị cho người đọc.' ],
                    'Service'        => [ 'label' => 'Service', 'desc' => 'Dùng khi taxonomy đại diện cho một nhóm dịch vụ.' ],
                    'MedicalWebPage' => [ 'label' => 'MedicalWebPage', 'desc' => 'Dùng cho danh mục điều trị/thẩm mỹ/y khoa.' ],
                    'WebPage'        => [ 'label' => 'WebPage', 'desc' => 'Dùng cho taxonomy landing page thông thường.' ],
                ];
        }

        return [
            'lang'                     => $lang,
            'intro'                    => $is_en ? 'Use these fields for GEO/AEO validation and JSON-LD schema. Keep schema data aligned with visible page content.' : 'Các trường này dùng cho kiểm tra GEO/AEO và JSON-LD schema. Dữ liệu schema nên khớp với nội dung đang hiển thị trên trang.',
            'geo_label'                => 'GEO/AEO Validation',
            'geo_options'              => [
                'inherit' => $is_en ? 'Follow global settings' : 'Theo settings tổng',
                'on'      => $is_en ? ( 'term' === $context ? 'Enable for this term' : 'Enable for this post' ) : ( 'term' === $context ? 'Bật riêng cho term này' : 'Bật riêng cho bài viết này' ),
                'off'     => $is_en ? ( 'term' === $context ? 'Disable for this term' : 'Disable for this post' ) : ( 'term' === $context ? 'Tắt riêng cho term này' : 'Tắt riêng cho bài viết này' ),
            ],
            'geo_note'                 => $is_en ? 'Use this to override GEO/AEO validation for the current item only.' : 'Dùng để bật/tắt GEO/AEO riêng cho nội dung hiện tại, không ảnh hưởng settings tổng.',
            'schema_type_label'        => $is_en ? 'Schema Type' : 'Loại Schema',
            'schema_type_note'         => $is_en ? 'Choose the schema that matches the real page intent; avoid adding schema for content that is not visible.' : 'Chọn loại schema đúng với ý định thật của trang; không khai báo dữ liệu không hiển thị cho người đọc.',
            'schema_guide_label'       => $is_en ? 'Schema type quick guide' : 'Hướng dẫn nhanh từng loại schema',
            'schema_options'           => $schema_options,
            'service_name_label'       => $is_en ? ( 'term' === $context ? 'Collection / Service Name' : 'Service Name' ) : ( 'term' === $context ? 'Tên danh mục / dịch vụ' : 'Tên dịch vụ' ),
            'service_name_note'        => $is_en ? 'Use the exact service or collection name shown to users; leave blank to use the current title.' : 'Nhập đúng tên dịch vụ/danh mục đang hiển thị; để trống sẽ dùng tiêu đề hiện tại.',
            'advanced_summary'         => $is_en ? 'Advanced schema fields (Procedure / Product / Review)' : 'Trường schema nâng cao (Procedure / Product / Review)',
            'advanced_note'            => $is_en ? 'Fill these only when the selected schema type needs them and the data is visible or verifiable.' : 'Chỉ điền khi loại schema đang chọn cần dữ liệu này và thông tin có hiển thị hoặc kiểm chứng được.',
            'extra_fields'             => $this->get_schema_geo_extra_fields( $is_en ),
            'faq_label'                => $is_en ? 'FAQ Builder' : 'Trình tạo FAQ',
            'faq_note'                 => $is_en ? 'Add questions and answers that are visible in the content; SHYNH SEO will output FAQPage JSON-LD.' : 'Nhập câu hỏi và câu trả lời có trong nội dung; SHYNH SEO sẽ tự tạo JSON-LD FAQPage khi lưu.',
            'faq_question_placeholder' => $is_en ? 'Question' : 'Câu hỏi',
            'faq_answer_placeholder'   => $is_en ? 'Answer' : 'Câu trả lời',
            'faq_move'                 => $is_en ? 'Move FAQ' : 'Di chuyển FAQ',
            'faq_import'               => $is_en ? 'Import FAQ from content' : 'Import FAQ từ nội dung',
            'faq_add'                  => $is_en ? 'Add FAQ' : 'Thêm FAQ',
            'remove_label'             => $is_en ? 'Remove' : 'Xóa',
            'medical_reviewed_label'   => $is_en ? 'Medical-aesthetic content reviewed' : 'Nội dung thẩm mỹ/y khoa đã được rà soát',
            'medical_reviewed_note'    => $is_en ? 'Enable when a qualified reviewer has checked the medical or aesthetic claims.' : 'Bật khi nội dung y khoa/thẩm mỹ đã được người có chuyên môn rà soát.',
            'reviewed_by_label'        => $is_en ? 'Reviewed By' : 'Người rà soát',
            'reviewed_by_note'         => $is_en ? 'Enter the reviewer name exactly as it appears on the page.' : 'Nhập tên người rà soát đúng như phần hiển thị trên trang.',
            'reviewed_date_label'      => $is_en ? 'Reviewed Date' : 'Ngày rà soát',
            'reviewed_date_note'       => $is_en ? 'Use YYYY-MM-DD so schema can output a valid date.' : 'Dùng định dạng YYYY-MM-DD để schema xuất ngày hợp lệ.',
        ];
    }

    private function get_schema_geo_extra_fields( $is_en ) {
        if ( $is_en ) {
            return [
                '_shynh_seo_schema_procedure_body_location' => [ 'label' => 'Body area (MedicalProcedure)', 'placeholder' => 'Example: face, chin, abdomen', 'note' => 'Use only for procedures with a clear treatment area.' ],
                '_shynh_seo_schema_procedure_how_performed' => [ 'label' => 'How it is performed (MedicalProcedure)', 'placeholder' => 'Short procedure summary', 'note' => 'Summarize the actual process in plain, non-promotional language.' ],
                '_shynh_seo_schema_procedure_preparation'   => [ 'label' => 'Preparation (MedicalProcedure)', 'placeholder' => 'Preparation notes', 'note' => 'List preparation guidance that is already visible in the article.' ],
                '_shynh_seo_schema_offer_price'             => [ 'label' => 'Price (Product/Offer)', 'placeholder' => 'Example: 5000000', 'note' => 'Use numbers only when the price is visible and current.' ],
                '_shynh_seo_schema_offer_currency'          => [ 'label' => 'Currency (Product/Offer)', 'placeholder' => 'VND', 'note' => 'Use an ISO currency code such as VND or USD.' ],
                '_shynh_seo_schema_review_rating'           => [ 'label' => 'Review rating (Review, 1-5)', 'placeholder' => 'Only when a real review exists', 'note' => 'Do not invent ratings; the review must be visible on the page.' ],
                '_shynh_seo_schema_review_author'           => [ 'label' => 'Review author (Review)', 'placeholder' => 'Reviewer name', 'note' => 'Use the real reviewer name shown with the review.' ],
            ];
        }

        return [
            '_shynh_seo_schema_procedure_body_location' => [ 'label' => 'Bộ phận (MedicalProcedure)', 'placeholder' => 'VD: vùng mặt, cằm, bụng', 'note' => 'Chỉ dùng cho liệu trình có vùng điều trị rõ ràng.' ],
            '_shynh_seo_schema_procedure_how_performed' => [ 'label' => 'Cách thực hiện (MedicalProcedure)', 'placeholder' => 'Tóm tắt cách thực hiện', 'note' => 'Tóm tắt quy trình thật, ngắn gọn và không quảng cáo quá mức.' ],
            '_shynh_seo_schema_procedure_preparation'   => [ 'label' => 'Chuẩn bị trước (MedicalProcedure)', 'placeholder' => 'Lưu ý chuẩn bị', 'note' => 'Chỉ nhập các lưu ý chuẩn bị đã có trong nội dung bài.' ],
            '_shynh_seo_schema_offer_price'             => [ 'label' => 'Giá (Product/Offer)', 'placeholder' => 'VD: 5000000', 'note' => 'Chỉ nhập số khi giá đang hiển thị thật và còn hiệu lực.' ],
            '_shynh_seo_schema_offer_currency'          => [ 'label' => 'Tiền tệ (Product/Offer)', 'placeholder' => 'VND', 'note' => 'Dùng mã tiền tệ ISO như VND hoặc USD.' ],
            '_shynh_seo_schema_review_rating'           => [ 'label' => 'Điểm đánh giá (Review, 1-5)', 'placeholder' => 'Chỉ nhập khi có đánh giá thật', 'note' => 'Không tự tạo rating; đánh giá phải hiển thị thật trên trang.' ],
            '_shynh_seo_schema_review_author'           => [ 'label' => 'Tác giả đánh giá (Review)', 'placeholder' => 'Tên người đánh giá', 'note' => 'Dùng đúng tên người đánh giá đang hiển thị cùng review.' ],
        ];
    }

    private function render_faq_builder( $schema_faq, $field_id, $schema_geo_ui = [] ) {
        if ( empty( $schema_geo_ui ) ) {
            $schema_geo_ui = $this->get_schema_geo_ui( null, 'post' );
        }

        $items = $this->decode_faq_items( $schema_faq );
        if ( empty( $items ) ) {
            $items = [
                [
                    'question' => '',
                    'answer'   => '',
                ],
            ];
        }
        ?>
        <div class="shynh-seo-field shynh-seo-faq-builder">
            <label><?php echo esc_html( $schema_geo_ui['faq_label'] ); ?></label>
            <p class="description"><?php echo esc_html( $schema_geo_ui['faq_note'] ); ?></p>
            <textarea id="<?php echo esc_attr( $field_id ); ?>" class="shynh-seo-faq-json" name="_shynh_seo_schema_faq" style="display:none;"><?php echo esc_textarea( $schema_faq ); ?></textarea>
            <div class="shynh-seo-faq-rows">
                <?php foreach ( $items as $item ) : ?>
                    <div class="shynh-seo-faq-row">
                        <button type="button" class="button shynh-seo-faq-drag" aria-label="<?php echo esc_attr( $schema_geo_ui['faq_move'] ); ?>"><span class="dashicons dashicons-move"></span></button>
                        <input type="text" class="shynh-seo-faq-question" value="<?php echo esc_attr( $item['question'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $schema_geo_ui['faq_question_placeholder'] ); ?>" />
                        <textarea class="shynh-seo-faq-answer" rows="3" placeholder="<?php echo esc_attr( $schema_geo_ui['faq_answer_placeholder'] ); ?>"><?php echo esc_textarea( $item['answer'] ?? '' ); ?></textarea>
                        <button type="button" class="button shynh-seo-faq-remove"><?php echo esc_html( $schema_geo_ui['remove_label'] ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button shynh-seo-btn shynh-seo-btn--secondary shynh-seo-faq-import"><?php echo esc_html( $schema_geo_ui['faq_import'] ); ?></button>
            <button type="button" class="button shynh-seo-btn shynh-seo-btn--secondary shynh-seo-faq-add"><?php echo esc_html( $schema_geo_ui['faq_add'] ); ?></button>
            <script type="text/template" class="shynh-seo-faq-template">
                <div class="shynh-seo-faq-row">
                    <button type="button" class="button shynh-seo-faq-drag" aria-label="<?php echo esc_attr( $schema_geo_ui['faq_move'] ); ?>"><span class="dashicons dashicons-move"></span></button>
                    <input type="text" class="shynh-seo-faq-question" value="" placeholder="<?php echo esc_attr( $schema_geo_ui['faq_question_placeholder'] ); ?>" />
                    <textarea class="shynh-seo-faq-answer" rows="3" placeholder="<?php echo esc_attr( $schema_geo_ui['faq_answer_placeholder'] ); ?>"></textarea>
                    <button type="button" class="button shynh-seo-faq-remove"><?php echo esc_html( $schema_geo_ui['remove_label'] ); ?></button>
                </div>
            </script>
        </div>
        <?php
    }

    private function decode_faq_items( $schema_faq ) {
        $decoded = json_decode( html_entity_decode( (string) $schema_faq ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return array_values( array_filter( $decoded, function( $item ) {
            return is_array( $item ) && ( ! empty( $item['question'] ) || ! empty( $item['answer'] ) );
        } ) );
    }

    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['shynh_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['shynh_seo_meta_nonce'], 'shynh_seo_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $this->save_primary_category( $post_id );
        $this->save_internal_links( $post_id );

        $fields = [
            '_shynh_seo_focus_keyword'   => 'sanitize_text_field',
            '_shynh_seo_title'           => 'sanitize_text_field',
            '_shynh_seo_meta_desc'       => 'sanitize_textarea_field',
            '_shynh_seo_fb_title'        => 'sanitize_text_field',
            '_shynh_seo_fb_desc'         => 'sanitize_textarea_field',
            '_shynh_seo_fb_image'        => 'esc_url_raw',
            '_shynh_seo_tw_title'        => 'sanitize_text_field',
            '_shynh_seo_tw_desc'         => 'sanitize_textarea_field',
            '_shynh_seo_tw_image'        => 'esc_url_raw',
            '_shynh_seo_canonical'       => 'esc_url_raw',
            '_shynh_seo_robots_index'    => 'sanitize_text_field',
            '_shynh_seo_robots_follow'   => 'sanitize_text_field',
            '_shynh_seo_robots_noarchive'=> 'sanitize_text_field',
            '_shynh_seo_robots_nosnippet'=> 'sanitize_text_field',
            '_shynh_seo_robots_noimageindex' => 'sanitize_text_field',
            '_shynh_seo_robots_max_snippet' => 'intval',
            '_shynh_seo_robots_max_image_preview' => 'sanitize_key',
            '_shynh_seo_robots_max_video_preview' => 'intval',
            '_shynh_seo_breadcrumb_title'=> 'sanitize_text_field',
            '_shynh_seo_cornerstone'     => 'sanitize_text_field',
            '_shynh_seo_geo_aeo_mode'    => 'sanitize_key',
            '_shynh_seo_schema_type'      => 'sanitize_text_field',
            '_shynh_seo_schema_faq'       => 'sanitize_textarea_field',
            '_shynh_seo_schema_service_name' => 'sanitize_text_field',
            '_shynh_seo_schema_medical_reviewed' => 'sanitize_text_field',
            '_shynh_seo_schema_reviewed_by' => 'sanitize_text_field',
            '_shynh_seo_schema_reviewed_date' => 'sanitize_text_field',
            '_shynh_seo_schema_procedure_body_location' => 'sanitize_text_field',
            '_shynh_seo_schema_procedure_how_performed' => 'sanitize_textarea_field',
            '_shynh_seo_schema_procedure_preparation'   => 'sanitize_textarea_field',
            '_shynh_seo_schema_offer_price'             => 'sanitize_text_field',
            '_shynh_seo_schema_offer_currency'          => 'sanitize_text_field',
            '_shynh_seo_schema_review_rating'           => 'sanitize_text_field',
            '_shynh_seo_schema_review_author'           => 'sanitize_text_field',
        ];

        foreach ( $fields as $field => $callback ) {
            if ( isset( $_POST[ $field ] ) ) {
                if ( in_array( $field, [ '_shynh_seo_robots_max_snippet', '_shynh_seo_robots_max_video_preview' ], true ) && '' === trim( wp_unslash( $_POST[ $field ] ) ) ) {
                    delete_post_meta( $post_id, $field );
                    continue;
                }

                $value = call_user_func( $callback, $_POST[ $field ] );
                update_post_meta( $post_id, $field, $value );
            } else {
                delete_post_meta( $post_id, $field );
            }
        }
    }

    private function save_primary_category( $post_id ) {
        $raw_value = null;

        if ( isset( $_POST['_shynh_seo_primary_category_sidebar'] ) ) {
            $raw_value = $_POST['_shynh_seo_primary_category_sidebar'];
        } elseif ( isset( $_POST['_shynh_seo_primary_category'] ) ) {
            $raw_value = $_POST['_shynh_seo_primary_category'];
        }

        if ( null === $raw_value ) {
            return;
        }

        $primary_term_id = absint( wp_unslash( $raw_value ) );
        if ( ! $primary_term_id ) {
            delete_post_meta( $post_id, '_shynh_seo_primary_category' );
            return;
        }

        $term = get_term( $primary_term_id );
        if ( ! $term || is_wp_error( $term ) ) {
            delete_post_meta( $post_id, '_shynh_seo_primary_category' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $post_taxonomies = get_object_taxonomies( $post->post_type );
        if ( ! in_array( $term->taxonomy, $post_taxonomies, true ) ) {
            delete_post_meta( $post_id, '_shynh_seo_primary_category' );
            return;
        }

        if ( ! has_term( $term->term_id, $term->taxonomy, $post_id ) ) {
            wp_set_object_terms( $post_id, [ (int) $term->term_id ], $term->taxonomy, true );
        }

        update_post_meta( $post_id, '_shynh_seo_primary_category', $term->term_id );
    }

    private function save_internal_links( $post_id ) {
        if ( ! isset( $_POST['_shynh_seo_internal_link_posts'] ) ) {
            delete_post_meta( $post_id, '_shynh_seo_internal_link_posts' );
            return;
        }

        $items = class_exists( 'Shynh_SEO_Internal_Linking' )
            ? Shynh_SEO_Internal_Linking::sanitize_internal_link_items( $_POST['_shynh_seo_internal_link_posts'], $post_id )
            : [];

        if ( empty( $items ) ) {
            delete_post_meta( $post_id, '_shynh_seo_internal_link_posts' );
            return;
        }

        update_post_meta( $post_id, '_shynh_seo_internal_link_posts', $items );
    }
}
