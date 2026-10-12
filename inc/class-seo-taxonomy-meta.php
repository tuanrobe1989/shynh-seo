<?php
/**
 * Shynh SEO Taxonomy Meta for editing term SEO settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Taxonomy_Meta {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'register_taxonomy_hooks' ], 99 );
    }

    public function register_taxonomy_hooks() {
        $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
        foreach ( $taxonomies as $tax ) {
            add_action( "{$tax}_edit_form_fields", [ $this, 'render_taxonomy_meta' ], 10, 2 );
            add_action( "edited_{$tax}", [ $this, 'save_taxonomy_meta' ], 10, 2 );
        }
    }

    public function render_taxonomy_meta( $term, $taxonomy ) {
        wp_nonce_field( 'shynh_seo_save_term_meta', 'shynh_seo_term_meta_nonce' );

        $term_id = $term->term_id;

        $focus_keyword = get_term_meta( $term_id, '_shynh_seo_focus_keyword', true );
        $seo_title     = get_term_meta( $term_id, '_shynh_seo_title', true );
        $meta_desc     = get_term_meta( $term_id, '_shynh_seo_meta_desc', true );
        
        $fb_title      = get_term_meta( $term_id, '_shynh_seo_fb_title', true );
        $fb_desc       = get_term_meta( $term_id, '_shynh_seo_fb_desc', true );
        $fb_image      = get_term_meta( $term_id, '_shynh_seo_fb_image', true );
        
        $tw_title      = get_term_meta( $term_id, '_shynh_seo_tw_title', true );
        $tw_desc       = get_term_meta( $term_id, '_shynh_seo_tw_desc', true );
        $tw_image      = get_term_meta( $term_id, '_shynh_seo_tw_image', true );
        
        $canonical     = get_term_meta( $term_id, '_shynh_seo_canonical', true );
        $robots_index  = get_term_meta( $term_id, '_shynh_seo_robots_index', true );
        $robots_follow = get_term_meta( $term_id, '_shynh_seo_robots_follow', true );
        $robots_noarchive = get_term_meta( $term_id, '_shynh_seo_robots_noarchive', true );
        $robots_nosnippet = get_term_meta( $term_id, '_shynh_seo_robots_nosnippet', true );
        $robots_noimageindex = get_term_meta( $term_id, '_shynh_seo_robots_noimageindex', true );
        $robots_max_snippet = get_term_meta( $term_id, '_shynh_seo_robots_max_snippet', true );
        $robots_max_image_preview = get_term_meta( $term_id, '_shynh_seo_robots_max_image_preview', true );
        $robots_max_video_preview = get_term_meta( $term_id, '_shynh_seo_robots_max_video_preview', true );
        $schema_type      = get_term_meta( $term_id, '_shynh_seo_schema_type', true );
        $schema_faq       = get_term_meta( $term_id, '_shynh_seo_schema_faq', true );
        $schema_service   = get_term_meta( $term_id, '_shynh_seo_schema_service_name', true );
        $geo_aeo_mode     = get_term_meta( $term_id, '_shynh_seo_geo_aeo_mode', true );
        $medical_reviewed = get_term_meta( $term_id, '_shynh_seo_schema_medical_reviewed', true );
        $reviewed_by      = get_term_meta( $term_id, '_shynh_seo_schema_reviewed_by', true );
        $reviewed_date    = get_term_meta( $term_id, '_shynh_seo_schema_reviewed_date', true );
        $schema_geo_ui    = $this->get_schema_geo_ui( $term );

        ?>
        <tr class="form-field shynh-seo-term-field">
            <th colspan="2" style="padding: 0;">
                <div class="shynh-seo-wrap" style="margin: 20px 0; max-width: 100%;">
                    
                    <h2 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid var(--shynh-border-gray); font-family: 'Playfair Display', serif; display: flex; align-items: center; justify-content: space-between;">
                        <span>Shynh SEO</span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span id="shynh-seo-title-badge" class="shynh-seo-badge shynh-seo-badge--gray" style="font-family: sans-serif; font-size: 13px;"><?php esc_html_e('Chưa phân tích', 'shynh-seo'); ?></span>
                            <span id="shynh-seo-geo-title-badge" class="shynh-seo-badge shynh-seo-badge--gray" style="font-family: sans-serif; font-size: 13px; display: none;"></span>
                        </div>
                    </h2>

                    <ul class="shynh-seo-htabs-nav">
                        <li class="active" data-tab="seo-term-basic"><?php esc_html_e( 'SEO Cơ Bản', 'shynh-seo' ); ?></li>
                        <li data-tab="seo-term-social"><?php esc_html_e( 'Mạng Xã Hội', 'shynh-seo' ); ?></li>
                        <li data-tab="seo-term-advanced"><?php esc_html_e( 'Nâng Cao', 'shynh-seo' ); ?></li>
                        <li data-tab="seo-term-schema"><?php esc_html_e( 'Schema/GEO', 'shynh-seo' ); ?></li>
                    </ul>

                    <div class="shynh-seo-htabs-content">
                        
                        <!-- TAB 1: SEO Cơ Bản -->
                        <div id="seo-term-basic" class="shynh-seo-htab-pane active">
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
                                            <span class="shynh-seo-var-badge">%%page%%</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                            <p class="description" style="margin:0;"><?php esc_html_e( 'Để trống để sử dụng tên chuyên mục.', 'shynh-seo' ); ?></p>
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
                                            <span class="shynh-seo-var-badge">%%excerpt%%</span>
                                            <span class="shynh-seo-var-badge">%%sep%%</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                            <p class="description" style="margin:0;"><?php esc_html_e( 'Để trống để tự động lấy từ mô tả.', 'shynh-seo' ); ?></p>
                                            <small id="shynh-seo-desc-count" style="color: var(--shynh-text-muted);">0 / 160</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Google Snippet Preview -->
                                    <div style="margin-top: 32px;">
                                        <label style="display:block; font-weight: 600; margin-bottom: 12px; color: var(--shynh-dark); font-size: 14px;"><?php esc_html_e( 'Xem trước kết quả tìm kiếm Google', 'shynh-seo' ); ?></label>
                                        <div class="shynh-seo-google-preview">
                                            <div class="sgp-url"><?php echo esc_url( get_term_link( $term ) ); ?></div>
                                            <div class="sgp-title" id="sgp-title-preview"><?php echo esc_html( $seo_title ? $seo_title : $term->name ); ?></div>
                                            <div class="sgp-desc" id="sgp-desc-preview"><?php echo esc_html( $meta_desc ? $meta_desc : 'Bản xem trước mô tả meta description sẽ hiển thị tại đây khi bạn cập nhật nội dung...' ); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Side: Analysis Results -->
                                <div class="shynh-seo-analysis-side">
                                    <div class="shynh-seo-analysis-box">
                                        <div id="shynh-seo-score-panel">
                                            <p style="text-align: center; color: var(--shynh-text-muted); font-size: 13px;">
                                                <?php esc_html_e( 'Đang phân tích chuyên mục...', 'shynh-seo' ); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- TAB 2: Mạng Xã Hội -->
                        <div id="seo-term-social" class="shynh-seo-htab-pane">
                            
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
                                <label for="shynh_seo_fb_image"><?php esc_html_e( 'Facebook Image', 'shynh-seo' ); ?></label>
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
                                <label for="shynh_seo_tw_image"><?php esc_html_e( 'Twitter Image', 'shynh-seo' ); ?></label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" id="shynh_seo_tw_image" name="_shynh_seo_tw_image" value="<?php echo esc_attr( $tw_image ); ?>" />
                                    <button type="button" class="shynh-seo-btn shynh-seo-btn--secondary shynh-seo-media-upload-btn" data-target="#shynh_seo_tw_image"><?php esc_html_e( 'Chọn Ảnh', 'shynh-seo' ); ?></button>
                                </div>
                            </div>

                        </div>

                        <!-- TAB 3: Nâng Cao -->
                        <div id="seo-term-advanced" class="shynh-seo-htab-pane">
                            
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_canonical"><?php esc_html_e( 'Canonical URL', 'shynh-seo' ); ?></label>
                                <input type="text" id="shynh_seo_canonical" name="_shynh_seo_canonical" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_url( get_term_link( $term ) ); ?>" />
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
                                <?php esc_html_e( 'Nếu nhập custom canonical, term này sẽ không được đưa vào sitemap để tránh tín hiệu SEO mâu thuẫn.', 'shynh-seo' ); ?>
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

                        </div>

                        <div id="seo-term-schema" class="shynh-seo-htab-pane">
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
                                <input type="text" id="shynh_seo_schema_service_name" name="_shynh_seo_schema_service_name" value="<?php echo esc_attr( $schema_service ); ?>" placeholder="<?php echo esc_attr( $term->name ); ?>" />
                                <p class="description"><?php echo esc_html( $schema_geo_ui['service_name_note'] ); ?></p>
                            </div>
                            <?php $this->render_faq_builder( $schema_faq, 'shynh_seo_schema_faq_term', $schema_geo_ui ); ?>
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

                    // Insert Snippet Variable
                    $('.shynh-seo-var-badge').on('click', function() {
                        var targetId = $(this).parent().data('target');
                        var $input = $(targetId);
                        var text = $(this).text();
                        
                        if ($input.length) {
                            var currentVal = $input.val();
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

                    $('.shynh-seo-faq-builder').each(function() {
                        var $builder = $(this);
                        if (!$builder.find('.shynh-seo-faq-row').length) {
                            addFaqRow($builder, '', '');
                        }
                        syncFaqBuilder($builder);
                        initFaqSortable($builder);
                    });

                    $(document).on('click', '.shynh-seo-faq-add', function(e) {
                        e.preventDefault();
                        addFaqRow($(this).closest('.shynh-seo-faq-builder'), '', '');
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
                });
                </script>
            </th>
        </tr>
        <?php
    }

    private function get_schema_geo_ui_language( $term = null ) {
        $lang = '';

        if ( $term instanceof WP_Term && function_exists( 'pll_get_term_language' ) ) {
            $lang = (string) pll_get_term_language( $term->term_id );
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

    private function get_schema_geo_ui( $term = null ) {
        $is_en = 'en' === $this->get_schema_geo_ui_language( $term );
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

        return [
            'intro'                    => $is_en ? 'Use these fields for GEO/AEO validation and CollectionPage/FAQ schema on this taxonomy archive.' : 'Các trường này dùng cho GEO/AEO validation và CollectionPage/FAQ schema của archive taxonomy này.',
            'geo_label'                => 'GEO/AEO Validation',
            'geo_options'              => [
                'inherit' => $is_en ? 'Follow global settings' : 'Theo settings tổng',
                'on'      => $is_en ? 'Enable for this term' : 'Bật riêng cho term này',
                'off'     => $is_en ? 'Disable for this term' : 'Tắt riêng cho term này',
            ],
            'geo_note'                 => $is_en ? 'Use this to override GEO/AEO validation for this term only.' : 'Dùng để bật/tắt GEO/AEO riêng cho term này, không ảnh hưởng settings tổng.',
            'schema_type_label'        => $is_en ? 'Schema Type' : 'Loại Schema',
            'schema_type_note'         => $is_en ? 'Choose the schema that matches the real archive intent.' : 'Chọn loại schema đúng với ý định thật của trang archive.',
            'schema_guide_label'       => $is_en ? 'Schema type quick guide' : 'Hướng dẫn nhanh từng loại schema',
            'schema_options'           => $schema_options,
            'service_name_label'       => $is_en ? 'Collection / Service Name' : 'Tên danh mục / dịch vụ',
            'service_name_note'        => $is_en ? 'Use the exact collection or service group name shown to users.' : 'Nhập đúng tên danh mục hoặc nhóm dịch vụ đang hiển thị.',
            'faq_label'                => $is_en ? 'FAQ Builder' : 'Trình tạo FAQ',
            'faq_note'                 => $is_en ? 'Add visible questions and answers; SHYNH SEO will output FAQPage JSON-LD for this term.' : 'Nhập câu hỏi và câu trả lời hiển thị trên trang; SHYNH SEO sẽ tạo JSON-LD FAQPage cho term này.',
            'faq_question_placeholder' => $is_en ? 'Question' : 'Câu hỏi',
            'faq_answer_placeholder'   => $is_en ? 'Answer' : 'Câu trả lời',
            'faq_move'                 => $is_en ? 'Move FAQ' : 'Di chuyển FAQ',
            'faq_add'                  => $is_en ? 'Add FAQ' : 'Thêm FAQ',
            'remove_label'             => $is_en ? 'Remove' : 'Xóa',
            'medical_reviewed_label'   => $is_en ? 'Medical-aesthetic content reviewed' : 'Nội dung thẩm mỹ/y khoa đã được rà soát',
            'medical_reviewed_note'    => $is_en ? 'Enable when this archive content has been checked by a qualified reviewer.' : 'Bật khi nội dung archive đã được người có chuyên môn rà soát.',
            'reviewed_by_label'        => $is_en ? 'Reviewed By' : 'Người rà soát',
            'reviewed_by_note'         => $is_en ? 'Enter the reviewer name exactly as it appears on the page.' : 'Nhập tên người rà soát đúng như phần hiển thị trên trang.',
            'reviewed_date_label'      => $is_en ? 'Reviewed Date' : 'Ngày rà soát',
            'reviewed_date_note'       => $is_en ? 'Use YYYY-MM-DD so schema can output a valid date.' : 'Dùng định dạng YYYY-MM-DD để schema xuất ngày hợp lệ.',
        ];
    }

    private function render_faq_builder( $schema_faq, $field_id, $schema_geo_ui = [] ) {
        if ( empty( $schema_geo_ui ) ) {
            $schema_geo_ui = $this->get_schema_geo_ui();
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

    public function save_taxonomy_meta( $term_id, $tt_id ) {
        if ( ! isset( $_POST['shynh_seo_term_meta_nonce'] ) || ! wp_verify_nonce( $_POST['shynh_seo_term_meta_nonce'], 'shynh_seo_save_term_meta' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_term', $term_id ) ) {
            return;
        }

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
            '_shynh_seo_geo_aeo_mode'    => 'sanitize_key',
            '_shynh_seo_schema_type'      => 'sanitize_text_field',
            '_shynh_seo_schema_faq'       => 'sanitize_textarea_field',
            '_shynh_seo_schema_service_name' => 'sanitize_text_field',
            '_shynh_seo_schema_medical_reviewed' => 'sanitize_text_field',
            '_shynh_seo_schema_reviewed_by' => 'sanitize_text_field',
            '_shynh_seo_schema_reviewed_date' => 'sanitize_text_field',
        ];

        foreach ( $fields as $field => $callback ) {
            if ( isset( $_POST[ $field ] ) ) {
                if ( in_array( $field, [ '_shynh_seo_robots_max_snippet', '_shynh_seo_robots_max_video_preview' ], true ) && '' === trim( wp_unslash( $_POST[ $field ] ) ) ) {
                    delete_term_meta( $term_id, $field );
                    continue;
                }

                $value = call_user_func( $callback, $_POST[ $field ] );
                update_term_meta( $term_id, $field, $value );
            } else {
                delete_term_meta( $term_id, $field );
            }
        }
    }
}
