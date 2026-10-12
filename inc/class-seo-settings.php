<?php
/**
 * Settings Page Class.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Settings {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'shynh_seo_settings_group', 'shynh_seo_settings', [ $this, 'sanitize' ] );
    }

    public function sanitize( $input ) {
        $sanitized = [];
        $defaults  = Shynh_SEO_Core::get_default_settings();
        $public_post_types = array_diff( get_post_types( [ 'public' => true ], 'names' ), [ 'attachment' ] );

        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        $taxonomy_names = array_diff( get_taxonomies( [ 'public' => true ], 'names' ), [ 'post_format' ] );
        $sitemap_profiles = [ 'auto', 'fresh', 'dynamic', 'stable', 'archive' ];
        $select_fields  = [
            'twitter_card_type'          => [ 'summary', 'summary_large_image' ],
            'robots_max_image_preview'   => [ '', 'none', 'standard', 'large' ],
            'robots_txt_mode'            => [ 'auto', 'manual' ],
            'sitemap_default_post_type_profile' => $sitemap_profiles,
            'sitemap_default_taxonomy_profile'  => $sitemap_profiles,
            'brand_ai_unverified_claim_mode' => [ 'block', 'review', 'warn' ],
            'brand_ai_tone_deviation_mode'   => [ 'block', 'review', 'warn' ],
        ];
        $number_fields  = [ 'sitemap_per_page', 'robots_max_snippet', 'robots_max_video_preview', 'geo_aeo_min_score', 'brand_ai_min_brand_fit', 'brand_ai_min_claim_safety' ];
        $url_fields     = [
            'default_og_image',
            'social_facebook',
            'social_instagram',
            'social_tiktok',
            'social_youtube',
            'social_twitter',
            'org_logo',
        ];

        foreach ( $defaults as $key => $default_val ) {
            if ( isset( $input[ $key ] ) ) {
                if ( in_array( $key, [ 'seo_post_types', 'related_post_types' ], true ) ) {
                    $sanitized[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input[ $key ] ), $public_post_types ) );
                } elseif ( 'sitemap_post_types' === $key ) {
                    $sanitized[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input[ $key ] ), $public_post_types ) );
                } elseif ( 'sitemap_taxonomies' === $key ) {
                    $sanitized[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input[ $key ] ), $taxonomy_names ) );
                } elseif ( in_array( $key, [ 'geo_aeo_post_types', 'ai_sync_post_types' ], true ) ) {
                    $sanitized[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input[ $key ] ), $public_post_types ) );
                } elseif ( in_array( $key, [ 'geo_aeo_taxonomies', 'ai_sync_taxonomies' ], true ) ) {
                    $sanitized[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input[ $key ] ), $taxonomy_names ) );
                } elseif ( 'sitemap_post_type_profiles' === $key ) {
                    $sanitized[ $key ] = $this->sanitize_sitemap_profiles( $input[ $key ], $public_post_types, $sitemap_profiles );
                } elseif ( 'sitemap_taxonomy_profiles' === $key ) {
                    $sanitized[ $key ] = $this->sanitize_sitemap_profiles( $input[ $key ], $taxonomy_names, $sitemap_profiles );
                } elseif ( in_array( $key, [ 'sitemap_excluded_post_types', 'sitemap_excluded_taxonomies', 'geo_aeo_excluded_post_types', 'geo_aeo_excluded_taxonomies', 'ai_sync_excluded_post_types', 'ai_sync_excluded_taxonomies' ], true ) ) {
                    $sanitized[ $key ] = [];
                } elseif ( isset( $select_fields[ $key ] ) ) {
                    $value = sanitize_key( $input[ $key ] );
                    $sanitized[ $key ] = in_array( $value, $select_fields[ $key ], true ) ? $value : $default_val;
                } elseif ( in_array( $key, $number_fields, true ) ) {
                    $value = '' === $input[ $key ] ? '' : (string) intval( $input[ $key ] );
                    if ( 'sitemap_per_page' === $key ) {
                        $value = (string) absint( $input[ $key ] );
                    }
                    $sanitized[ $key ] = $value;
                } elseif ( in_array( $key, $url_fields, true ) ) {
                    $sanitized[ $key ] = esc_url_raw( $input[ $key ] );
                } elseif ( is_array( $default_val ) ) {
                    $sanitized[ $key ] = array_map( 'sanitize_text_field', $input[ $key ] );
                } elseif ( is_bool( $default_val ) ) {
                    $sanitized[ $key ] = ! empty( $input[ $key ] );
                } else {
                    $sanitized[ $key ] = sanitize_textarea_field( $input[ $key ] );
                }
            } else {
                if ( is_bool( $default_val ) ) {
                    $sanitized[ $key ] = false;
                } elseif ( is_array( $default_val ) ) {
                    $sanitized[ $key ] = [];
                } else {
                    $sanitized[ $key ] = '';
                }
            }
        }

        if ( ! empty( $sanitized['sitemap_auto_include_post_types'] ) && isset( $input['sitemap_known_post_types'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['sitemap_known_post_types'] ), $public_post_types ) );
            $checked = isset( $sanitized['sitemap_post_types'] ) ? (array) $sanitized['sitemap_post_types'] : [];
            $sanitized['sitemap_excluded_post_types'] = array_values( array_diff( $known, $checked ) );
        }

        if ( ! empty( $sanitized['sitemap_auto_include_taxonomies'] ) && isset( $input['sitemap_known_taxonomies'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['sitemap_known_taxonomies'] ), $taxonomy_names ) );
            $checked = isset( $sanitized['sitemap_taxonomies'] ) ? (array) $sanitized['sitemap_taxonomies'] : [];
            $sanitized['sitemap_excluded_taxonomies'] = array_values( array_diff( $known, $checked ) );
        }

        if ( ! empty( $sanitized['geo_aeo_auto_include_post_types'] ) && isset( $input['geo_aeo_known_post_types'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['geo_aeo_known_post_types'] ), $public_post_types ) );
            $checked = isset( $sanitized['geo_aeo_post_types'] ) ? (array) $sanitized['geo_aeo_post_types'] : [];
            $sanitized['geo_aeo_excluded_post_types'] = array_values( array_diff( $known, $checked ) );
        }

        if ( ! empty( $sanitized['geo_aeo_auto_include_taxonomies'] ) && isset( $input['geo_aeo_known_taxonomies'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['geo_aeo_known_taxonomies'] ), $taxonomy_names ) );
            $checked = isset( $sanitized['geo_aeo_taxonomies'] ) ? (array) $sanitized['geo_aeo_taxonomies'] : [];
            $sanitized['geo_aeo_excluded_taxonomies'] = array_values( array_diff( $known, $checked ) );
        }

        if ( ! empty( $sanitized['ai_sync_auto_include_post_types'] ) && isset( $input['ai_sync_known_post_types'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['ai_sync_known_post_types'] ), $public_post_types ) );
            $checked = isset( $sanitized['ai_sync_post_types'] ) ? (array) $sanitized['ai_sync_post_types'] : [];
            $sanitized['ai_sync_excluded_post_types'] = array_values( array_diff( $known, $checked ) );
        }

        if ( ! empty( $sanitized['ai_sync_auto_include_taxonomies'] ) && isset( $input['ai_sync_known_taxonomies'] ) ) {
            $known   = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['ai_sync_known_taxonomies'] ), $taxonomy_names ) );
            $checked = isset( $sanitized['ai_sync_taxonomies'] ) ? (array) $sanitized['ai_sync_taxonomies'] : [];
            $sanitized['ai_sync_excluded_taxonomies'] = array_values( array_diff( $known, $checked ) );
        }

        if ( class_exists( 'Shynh_SEO_Robots_Txt' ) ) {
            Shynh_SEO_Robots_Txt::get_instance()->sync_physical_file_from_settings( $sanitized );
        }

        return $sanitized;
    }

    private function sanitize_sitemap_profiles( $input, $allowed_keys, $allowed_profiles ) {
        $sanitized = [];

        if ( ! is_array( $input ) ) {
            return $sanitized;
        }

        foreach ( $input as $object_key => $profile ) {
            $object_key = sanitize_key( $object_key );
            $profile    = sanitize_key( $profile );

            if ( ! in_array( $object_key, $allowed_keys, true ) || ! in_array( $profile, $allowed_profiles, true ) ) {
                continue;
            }

            if ( 'auto' === $profile ) {
                continue;
            }

            $sanitized[ $object_key ] = $profile;
        }

        return $sanitized;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = Shynh_SEO_Core::get_settings();
        ?>
        <div class="wrap shynh-seo-wrap">
            <h1 class="shynh-seo-page-title">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'Cài Đặt SEO', 'shynh-seo' ); ?>
            </h1>

            <?php settings_errors( 'shynh_seo_settings_group' ); ?>

            <form action="options.php" method="post">
                <?php settings_fields( 'shynh_seo_settings_group' ); ?>
                
                <div class="shynh-seo-settings-layout">
                    <!-- Left Column: Vertical Tabs -->
                    <div class="shynh-seo-vtabs">
                        <ul class="shynh-seo-vtabs-nav">
                            <li class="active" data-tab="tab-general"><?php esc_html_e( 'Chung', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-sitemap"><?php esc_html_e( 'Sitemap', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-social"><?php esc_html_e( 'Mạng Xã Hội', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-robots"><?php esc_html_e( 'Robots', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-geo-aeo"><?php esc_html_e( 'GEO/AEO', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-brand-audit"><?php esc_html_e( 'Brand AI Audit', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-ai-sync"><?php esc_html_e( 'AI Sync', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-knowledge-vault"><?php esc_html_e( '📂 Kho Tài Nguyên', 'shynh-seo' ); ?></li>
                            <li data-tab="tab-schema"><?php esc_html_e( 'Schema', 'shynh-seo' ); ?></li>
                        </ul>
                    </div>

                    <!-- Right Column: Settings Content -->
                    <div class="shynh-seo-card" style="margin-bottom: 0;">
                        
                        <!-- TAB: General -->
                        <div id="tab-general" class="shynh-seo-vtab-pane active">
                            <h4><?php esc_html_e( 'Cài đặt chung', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_title_pattern"><?php esc_html_e( 'Mẫu Tiêu Đề SEO (Title Pattern)', 'shynh-seo' ); ?></label>
                                <input type="text" id="shynh_seo_settings_title_pattern" name="shynh_seo_settings[title_pattern]" value="<?php echo esc_attr( $settings['title_pattern'] ); ?>" />
                                <div class="shynh-seo-var-hints" data-target="#shynh_seo_settings_title_pattern">
                                    <span class="shynh-seo-var-badge">%%title%%</span>
                                    <span class="shynh-seo-var-badge">%%sitename%%</span>
                                    <span class="shynh-seo-var-badge">%%sitedesc%%</span>
                                    <span class="shynh-seo-var-badge">%%sep%%</span>
                                    <span class="shynh-seo-var-badge">%%page%%</span>
                                </div>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_title_separator"><?php esc_html_e( 'Ký tự ngăn cách (Title Separator)', 'shynh-seo' ); ?></label>
                                <select id="shynh_seo_settings_title_separator" name="shynh_seo_settings[title_separator]">
                                    <option value="-" <?php selected( $settings['title_separator'], '-' ); ?>>-</option>
                                    <option value="|" <?php selected( $settings['title_separator'], '|' ); ?>>|</option>
                                    <option value="—" <?php selected( $settings['title_separator'], '—' ); ?>>—</option>
                                    <option value="·" <?php selected( $settings['title_separator'], '·' ); ?>>·</option>
                                </select>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_default_meta_desc"><?php esc_html_e( 'Mô tả Meta Mặc định (Default Meta Description)', 'shynh-seo' ); ?></label>
                                <textarea id="shynh_seo_settings_default_meta_desc" name="shynh_seo_settings[default_meta_desc]" rows="4"><?php echo esc_textarea( $settings['default_meta_desc'] ); ?></textarea>
                                <div class="shynh-seo-var-hints" data-target="#shynh_seo_settings_default_meta_desc">
                                    <span class="shynh-seo-var-badge">%%sitename%%</span>
                                    <span class="shynh-seo-var-badge">%%sitedesc%%</span>
                                    <span class="shynh-seo-var-badge">%%sep%%</span>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="margin-top: 24px; display: flex; align-items: center; gap: 12px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[show_seo_columns]" value="1" <?php checked( $settings['show_seo_columns'] ?? true ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Hiển thị cột Điểm SEO và Từ Khóa Chính', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Hiện các cột điểm SEO (Xanh/Vàng/Đỏ) ra ngoài danh sách bài viết trong admin.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field" style="margin-top: 24px;">
                                <label><?php esc_html_e( 'Post Types hiển thị Shynh SEO', 'shynh-seo' ); ?></label>
                                <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Chọn nơi hiển thị meta box SEO, chạy phân tích điểm SEO và thêm cột SEO trong admin.', 'shynh-seo' ); ?></p>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px 16px; margin-top: 12px;">
                                    <?php
                                    $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                    foreach ( $post_types as $pt ) {
                                        if ( 'attachment' === $pt->name ) continue;

                                        echo '<label style="font-weight: normal; display: flex; align-items: center; gap: 8px; margin: 0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[seo_post_types][]" value="' . esc_attr( $pt->name ) . '" ' . checked( in_array( $pt->name, $settings['seo_post_types'], true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $pt->label ) . ' <code style="font-size: 11px;">' . esc_html( $pt->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="margin-top: 24px;">
                                <label><?php esc_html_e( 'Post Types hiển thị trong Bài viết liên quan', 'shynh-seo' ); ?></label>
                                <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Chọn loại nội dung được bật Bài viết liên quan và được dùng làm nguồn gợi ý cho Internal Linking.', 'shynh-seo' ); ?></p>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px 16px; margin-top: 12px;">
                                    <?php
                                    $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                    foreach ( $post_types as $pt ) {
                                        if ( 'attachment' === $pt->name ) continue;

                                        echo '<label style="font-weight: normal; display: flex; align-items: center; gap: 8px; margin: 0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[related_post_types][]" value="' . esc_attr( $pt->name ) . '" ' . checked( in_array( $pt->name, $settings['related_post_types'], true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $pt->label ) . ' <code style="font-size: 11px;">' . esc_html( $pt->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: Sitemap -->
                        <div id="tab-sitemap" class="shynh-seo-vtab-pane">
                            <h4><?php esc_html_e( 'Sitemap XML', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-note shynh-seo-note--info">
                                <?php esc_html_e( 'Smart sitemap tu tinh Frequency/Priority theo do moi, tan suat chinh sua va profile noi dung. Voi dich vu hoac category dich vu thay doi thuong xuyen, hay chon Fresh hoac Dynamic.', 'shynh-seo' ); ?>
                            </div>

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin:24px 0;">
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[sitemap_auto_include_post_types]" value="1" <?php checked( $settings['sitemap_auto_include_post_types'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu nap post type public moi', 'shynh-seo' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Post type dich vu moi se tu co sitemap neu public va khong phai attachment.', 'shynh-seo' ); ?></p>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[sitemap_auto_include_taxonomies]" value="1" <?php checked( $settings['sitemap_auto_include_taxonomies'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu nap taxonomy public moi', 'shynh-seo' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Category dich vu moi se tu vao sitemap khi public va co noi dung.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:24px;">
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_sitemap_default_post_type_profile"><?php esc_html_e( 'Profile mac dinh cho post type moi', 'shynh-seo' ); ?></label>
                                    <?php $this->render_sitemap_profile_select( 'shynh_seo_settings[sitemap_default_post_type_profile]', $settings['sitemap_default_post_type_profile'] ?? 'auto', 'shynh_seo_settings_sitemap_default_post_type_profile' ); ?>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_sitemap_default_taxonomy_profile"><?php esc_html_e( 'Profile mac dinh cho taxonomy moi', 'shynh-seo' ); ?></label>
                                    <?php $this->render_sitemap_profile_select( 'shynh_seo_settings[sitemap_default_taxonomy_profile]', $settings['sitemap_default_taxonomy_profile'] ?? 'dynamic', 'shynh_seo_settings_sitemap_default_taxonomy_profile' ); ?>
                                </div>
                            </div>
                            
                            <div class="shynh-seo-field">
                                <label><?php esc_html_e( 'Bật Sitemap cho các Post Types', 'shynh-seo' ); ?></label>
                                <?php
                                $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                $auto_post_types = ! empty( $settings['sitemap_auto_include_post_types'] );
                                $excluded_post_types = isset( $settings['sitemap_excluded_post_types'] ) ? (array) $settings['sitemap_excluded_post_types'] : [];
                                foreach ( $post_types as $pt ) {
                                    if ( 'attachment' === $pt->name ) continue;
                                    $checked = ( $auto_post_types && ! in_array( $pt->name, $excluded_post_types, true ) ) || in_array( $pt->name, $settings['sitemap_post_types'], true ) ? 'checked' : '';
                                    echo '<div style="display:grid; grid-template-columns:minmax(180px,1fr) minmax(170px,220px); gap:12px; align-items:center; margin-top:10px;">';
                                    echo '<input type="hidden" name="shynh_seo_settings[sitemap_known_post_types][]" value="' . esc_attr( $pt->name ) . '">';
                                    echo '<label style="font-weight: normal; margin:0;"><input type="checkbox" name="shynh_seo_settings[sitemap_post_types][]" value="' . esc_attr( $pt->name ) . '" ' . $checked . '> ' . esc_html( $pt->label ) . ' <code style="font-size:11px;">' . esc_html( $pt->name ) . '</code></label>';
                                    $this->render_sitemap_profile_select( 'shynh_seo_settings[sitemap_post_type_profiles][' . esc_attr( $pt->name ) . ']', $settings['sitemap_post_type_profiles'][ $pt->name ] ?? 'auto' );
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <div class="shynh-seo-field" style="margin-top: 24px;">
                                <label><?php esc_html_e( 'Bật Sitemap cho các Taxonomies', 'shynh-seo' ); ?></label>
                                <?php
                                $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
                                $auto_taxonomies = ! empty( $settings['sitemap_auto_include_taxonomies'] );
                                $excluded_taxonomies = isset( $settings['sitemap_excluded_taxonomies'] ) ? (array) $settings['sitemap_excluded_taxonomies'] : [];
                                foreach ( $taxonomies as $tax ) {
                                    if ( 'post_format' === $tax->name ) continue;
                                    $checked = ( $auto_taxonomies && ! in_array( $tax->name, $excluded_taxonomies, true ) ) || in_array( $tax->name, $settings['sitemap_taxonomies'], true ) ? 'checked' : '';
                                    echo '<div style="display:grid; grid-template-columns:minmax(180px,1fr) minmax(170px,220px); gap:12px; align-items:center; margin-top:10px;">';
                                    echo '<input type="hidden" name="shynh_seo_settings[sitemap_known_taxonomies][]" value="' . esc_attr( $tax->name ) . '">';
                                    echo '<label style="font-weight: normal; margin:0;"><input type="checkbox" name="shynh_seo_settings[sitemap_taxonomies][]" value="' . esc_attr( $tax->name ) . '" ' . $checked . '> ' . esc_html( $tax->label ) . ' <code style="font-size:11px;">' . esc_html( $tax->name ) . '</code></label>';
                                    $this->render_sitemap_profile_select( 'shynh_seo_settings[sitemap_taxonomy_profiles][' . esc_attr( $tax->name ) . ']', $settings['sitemap_taxonomy_profiles'][ $tax->name ] ?? 'auto' );
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <div class="shynh-seo-field" style="margin-top: 24px;">
                                <label for="shynh_seo_settings_sitemap_per_page"><?php esc_html_e( 'Số lượng link mỗi trang Sitemap', 'shynh-seo' ); ?></label>
                                <input type="number" id="shynh_seo_settings_sitemap_per_page" name="shynh_seo_settings[sitemap_per_page]" value="<?php echo esc_attr( $settings['sitemap_per_page'] ); ?>" />
                            </div>
                        </div>

                        <!-- TAB: Social -->
                        <div id="tab-social" class="shynh-seo-vtab-pane">
                            <?php $default_social_desc = $settings['default_social_desc'] ?? ''; ?>
                            <h4><?php esc_html_e( 'Mang Xa Hoi', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_default_social_desc"><?php esc_html_e( 'Mô tả chia sẻ mặc định', 'shynh-seo' ); ?></label>
                                <textarea id="shynh_seo_settings_default_social_desc" name="shynh_seo_settings[default_social_desc]" rows="3" placeholder="<?php esc_attr_e( 'Ví dụ: Shynh Super Luxury - Healthcare & Wellness Center...', 'shynh-seo' ); ?>"><?php echo esc_textarea( $default_social_desc ); ?></textarea>
                                <div class="shynh-seo-var-hints" data-target="#shynh_seo_settings_default_social_desc">
                                    <span class="shynh-seo-var-badge">%%sitename%%</span>
                                    <span class="shynh-seo-var-badge">%%sitedesc%%</span>
                                    <span class="shynh-seo-var-badge">%%sep%%</span>
                                    <span class="shynh-seo-var-badge">%%title%%</span>
                                </div>
                                <p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Dùng làm og:description và twitter:description khi trang/bài viết/chuyên mục chưa có mô tả share riêng.', 'shynh-seo' ); ?></p>
                            </div>
                            <h4><?php esc_html_e( 'Mạng Xã Hội', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_default_og_image"><?php esc_html_e( 'Ảnh OG Mặc định (Default Open Graph Image)', 'shynh-seo' ); ?></label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" id="shynh_seo_settings_default_og_image" name="shynh_seo_settings[default_og_image]" value="<?php echo esc_attr( $settings['default_og_image'] ); ?>" />
                                    <button type="button" class="shynh-seo-btn shynh-seo-btn--secondary shynh-seo-media-upload-btn" data-target="#shynh_seo_settings_default_og_image"><?php esc_html_e( 'Chọn Ảnh', 'shynh-seo' ); ?></button>
                                </div>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_facebook_app_id"><?php esc_html_e( 'Facebook App ID', 'shynh-seo' ); ?></label>
                                <input type="text" id="shynh_seo_settings_facebook_app_id" name="shynh_seo_settings[facebook_app_id]" value="<?php echo esc_attr( $settings['facebook_app_id'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_twitter_card_type"><?php esc_html_e( 'Twitter Card Type', 'shynh-seo' ); ?></label>
                                <select id="shynh_seo_settings_twitter_card_type" name="shynh_seo_settings[twitter_card_type]">
                                    <option value="summary" <?php selected( $settings['twitter_card_type'], 'summary' ); ?>>Summary</option>
                                    <option value="summary_large_image" <?php selected( $settings['twitter_card_type'], 'summary_large_image' ); ?>>Summary Large Image</option>
                                </select>
                            </div>

                            <h4 style="margin-top: 40px;"><?php esc_html_e( 'Liên kết Mạng Xã Hội', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            
                            <div class="shynh-seo-field">
                                <label for="social_facebook">Facebook URL</label>
                                <input type="text" id="social_facebook" name="shynh_seo_settings[social_facebook]" value="<?php echo esc_attr( $settings['social_facebook'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="social_instagram">Instagram URL</label>
                                <input type="text" id="social_instagram" name="shynh_seo_settings[social_instagram]" value="<?php echo esc_attr( $settings['social_instagram'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="social_tiktok">TikTok URL</label>
                                <input type="text" id="social_tiktok" name="shynh_seo_settings[social_tiktok]" value="<?php echo esc_attr( $settings['social_tiktok'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="social_youtube">YouTube URL</label>
                                <input type="text" id="social_youtube" name="shynh_seo_settings[social_youtube]" value="<?php echo esc_attr( $settings['social_youtube'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="social_twitter">Twitter (X) URL</label>
                                <input type="text" id="social_twitter" name="shynh_seo_settings[social_twitter]" value="<?php echo esc_attr( $settings['social_twitter'] ); ?>" />
                            </div>
                        </div>

                        <!-- TAB: Robots -->
                        <div id="tab-robots" class="shynh-seo-vtab-pane">
                            <div class="shynh-seo-note shynh-seo-note--warning">
                                <?php esc_html_e( 'Noindex dùng để chặn index. Canonical dùng để gom tín hiệu URL trùng lặp, không thay thế noindex.', 'shynh-seo' ); ?>
                            </div>
                            <h4><?php esc_html_e( 'Cấu hình Robots.txt / Noindex', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[robots_txt_enabled]" value="1" <?php checked( $settings['robots_txt_enabled'] ?? true ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Bật robots.txt tự động', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php echo esc_html( sprintf( __( 'Tự tạo /robots.txt và khai báo Sitemap: %s', 'shynh-seo' ), home_url( '/sitemap.xml' ) ) ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field" style="margin-top: 20px;">
                                <label for="shynh_seo_settings_robots_txt_custom_rules"><?php esc_html_e( 'Quy tắc robots.txt tùy chỉnh', 'shynh-seo' ); ?></label>
                                <select id="shynh_seo_settings_robots_txt_mode" name="shynh_seo_settings[robots_txt_mode]" style="margin-bottom: 12px;">
                                    <option value="auto" <?php selected( $settings['robots_txt_mode'] ?? 'auto', 'auto' ); ?>><?php esc_html_e( 'Auto - plugin tự tạo và thêm custom rules', 'shynh-seo' ); ?></option>
                                    <option value="manual" <?php selected( $settings['robots_txt_mode'] ?? 'auto', 'manual' ); ?>><?php esc_html_e( 'Manual - tự viết toàn bộ robots.txt', 'shynh-seo' ); ?></option>
                                </select>
                                <textarea id="shynh_seo_settings_robots_txt_custom_rules" name="shynh_seo_settings[robots_txt_custom_rules]" rows="5" placeholder="Disallow: /duong-dan-rieng/"><?php echo esc_textarea( $settings['robots_txt_custom_rules'] ?? '' ); ?></textarea>
                                <label for="shynh_seo_settings_robots_txt_manual_content" style="margin-top: 14px;"><?php esc_html_e( 'Nội dung robots.txt thủ công', 'shynh-seo' ); ?></label>
                                <textarea id="shynh_seo_settings_robots_txt_manual_content" name="shynh_seo_settings[robots_txt_manual_content]" rows="8" placeholder="User-agent: *&#10;Disallow: /wp-admin/&#10;Allow: /wp-admin/admin-ajax.php&#10;Sitemap: <?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>"><?php echo esc_textarea( $settings['robots_txt_manual_content'] ?? '' ); ?></textarea>
                                <p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Mỗi dòng một rule. Plugin sẽ tự thêm User-agent, wp-admin/admin-ajax và Sitemap.', 'shynh-seo' ); ?></p>
                            </div>

                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[sitewide_noindex]" value="1" <?php checked( $settings['sitewide_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Chặn index toàn bộ Website (Sitewide Noindex)', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Bật tính năng này sẽ thêm thẻ noindex vào tất cả các trang.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[archive_noindex]" value="1" <?php checked( $settings['archive_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Chặn index các trang Archive (Category, Tag, Date)', 'shynh-seo' ); ?></strong>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[search_noindex]" value="1" <?php checked( $settings['search_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Noindex trang tìm kiếm nội bộ', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Khuyến nghị bật để tránh index các trang kết quả tìm kiếm mỏng hoặc trộn lẫn.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[not_found_noindex]" value="1" <?php checked( $settings['not_found_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Noindex trang 404', 'shynh-seo' ); ?></strong>
                                </div>
                            </div>
                            <h4 style="margin-top: 40px;"><?php esc_html_e( 'Cấu hình Media (Ảnh/Video)', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[enable_media_seo_pages]" value="1" <?php checked( $settings['enable_media_seo_pages'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Bật trang SEO Media', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Tạo trang riêng (attachment page) được tối ưu hóa cho SEO cho từng hình ảnh/video thay vì chuyển hướng.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[attachment_noindex]" value="1" <?php checked( $settings['attachment_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Noindex trang đính kèm media', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Chặn index trang media nếu bạn không muốn Google index URL đính kèm ảnh độc lập.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[media_in_sitemap]" value="1" <?php checked( $settings['media_in_sitemap'] ?? false ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Đưa Media vào Sitemap', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Bật tính năng này sẽ thêm sitemap riêng cho media nếu chúng không bị noindex.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[paginated_noindex]" value="1" <?php checked( $settings['paginated_noindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Noindex các trang phân trang', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Chỉ bật nếu bạn chắc chắn không muốn các trang /page/2/ xuất hiện trên Google.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <h4 style="margin-top: 40px;"><?php esc_html_e( 'Robots nâng cao mặc định', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[robots_noarchive]" value="1" <?php checked( $settings['robots_noarchive'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong>Noarchive</strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Không cho search engine hiển thị bản cache.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[robots_nosnippet]" value="1" <?php checked( $settings['robots_nosnippet'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong>Nosnippet</strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Không cho hiển thị đoạn trích trong kết quả tìm kiếm.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div class="shynh-seo-field" style="display: flex; align-items: center; gap: 12px; margin-top: 24px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[robots_noimageindex]" value="1" <?php checked( $settings['robots_noimageindex'] ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong>Noimageindex</strong>
                                    <p class="description" style="margin-top: 2px;"><?php esc_html_e( 'Không cho index ảnh trên trang.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-top: 24px;">
                                <div class="shynh-seo-field" style="margin-bottom: 0;">
                                    <label for="shynh_seo_settings_robots_max_snippet">Max snippet</label>
                                    <input type="number" id="shynh_seo_settings_robots_max_snippet" name="shynh_seo_settings[robots_max_snippet]" value="<?php echo esc_attr( $settings['robots_max_snippet'] ); ?>" placeholder="-1" />
                                    <p class="description"><?php esc_html_e( 'Để trống để không khai báo. Dùng -1 nếu muốn không giới hạn.', 'shynh-seo' ); ?></p>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom: 0;">
                                    <label for="shynh_seo_settings_robots_max_image_preview">Max image preview</label>
                                    <select id="shynh_seo_settings_robots_max_image_preview" name="shynh_seo_settings[robots_max_image_preview]">
                                        <option value="" <?php selected( $settings['robots_max_image_preview'], '' ); ?>><?php esc_html_e( 'Không khai báo', 'shynh-seo' ); ?></option>
                                        <option value="none" <?php selected( $settings['robots_max_image_preview'], 'none' ); ?>>None</option>
                                        <option value="standard" <?php selected( $settings['robots_max_image_preview'], 'standard' ); ?>>Standard</option>
                                        <option value="large" <?php selected( $settings['robots_max_image_preview'], 'large' ); ?>>Large</option>
                                    </select>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom: 0;">
                                    <label for="shynh_seo_settings_robots_max_video_preview">Max video preview</label>
                                    <input type="number" id="shynh_seo_settings_robots_max_video_preview" name="shynh_seo_settings[robots_max_video_preview]" value="<?php echo esc_attr( $settings['robots_max_video_preview'] ); ?>" placeholder="-1" />
                                </div>
                            </div>
                        </div>

                        <!-- TAB: GEO/AEO -->
                        <div id="tab-geo-aeo" class="shynh-seo-vtab-pane">
                            <h4><?php esc_html_e( 'GEO/AEO validation', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-note shynh-seo-note--info">
                                <?php esc_html_e( 'GEO/AEO dung de kiem tra noi dung phu hop AI Overview, answer engine va entity search. Coverage duoc auto-detect tu public post type va taxonomy, khong hard code.', 'shynh-seo' ); ?>
                            </div>

                            <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[geo_aeo_enabled]" value="1" <?php checked( $settings['geo_aeo_enabled'] ?? false ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Bat GEO/AEO validation', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top:2px;"><?php esc_html_e( 'Khi bat, bai viet va term duoc chon se co them diem GEO/AEO rieng.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_geo_aeo_min_score"><?php esc_html_e( 'Diem GEO/AEO toi thieu', 'shynh-seo' ); ?></label>
                                <input type="number" id="shynh_seo_settings_geo_aeo_min_score" name="shynh_seo_settings[geo_aeo_min_score]" value="<?php echo esc_attr( $settings['geo_aeo_min_score'] ?? 80 ); ?>" min="0" max="100" />
                            </div>

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin:24px 0;">
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[geo_aeo_auto_include_post_types]" value="1" <?php checked( $settings['geo_aeo_auto_include_post_types'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu ap dung cho public post type moi', 'shynh-seo' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'CPT moi se tu co GEO/AEO neu public va khong phai attachment.', 'shynh-seo' ); ?></p>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[geo_aeo_auto_include_taxonomies]" value="1" <?php checked( $settings['geo_aeo_auto_include_taxonomies'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu ap dung cho public taxonomy moi', 'shynh-seo' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Taxonomy moi se tu co GEO/AEO neu public va khong phai post_format.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label><?php esc_html_e( 'Post types co GEO/AEO', 'shynh-seo' ); ?></label>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 16px; margin-top:12px;">
                                    <?php
                                    $geo_post_types = Shynh_SEO_Core::get_geo_aeo_post_types();
                                    foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
                                        if ( 'attachment' === $pt->name ) {
                                            continue;
                                        }
                                        echo '<input type="hidden" name="shynh_seo_settings[geo_aeo_known_post_types][]" value="' . esc_attr( $pt->name ) . '">';
                                        echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px; margin:0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[geo_aeo_post_types][]" value="' . esc_attr( $pt->name ) . '" ' . checked( in_array( $pt->name, $geo_post_types, true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $pt->label ) . ' <code style="font-size:11px;">' . esc_html( $pt->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label><?php esc_html_e( 'Taxonomies co GEO/AEO', 'shynh-seo' ); ?></label>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 16px; margin-top:12px;">
                                    <?php
                                    $geo_taxonomies = Shynh_SEO_Core::get_geo_aeo_taxonomies();
                                    foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
                                        if ( 'post_format' === $tax->name ) {
                                            continue;
                                        }
                                        echo '<input type="hidden" name="shynh_seo_settings[geo_aeo_known_taxonomies][]" value="' . esc_attr( $tax->name ) . '">';
                                        echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px; margin:0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[geo_aeo_taxonomies][]" value="' . esc_attr( $tax->name ) . '" ' . checked( in_array( $tax->name, $geo_taxonomies, true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $tax->label ) . ' <code style="font-size:11px;">' . esc_html( $tax->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Rule bat buoc', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">
                            <?php
                            $geo_toggles = [
                                'geo_aeo_require_quick_answer' => 'Require Quick Answer',
                                'geo_aeo_require_faq' => 'Require FAQ',
                                'geo_aeo_require_entity_signals' => 'Require entity signals',
                                'geo_aeo_require_schema' => 'Require schema support',
                                'geo_aeo_require_medical_disclaimer' => 'Require medical-aesthetic disclaimer',
                                'geo_aeo_require_review_status' => 'Require human review status',
                                'geo_aeo_require_search_eligibility' => 'Require index/snippet eligibility',
                                'geo_aeo_require_experience_signals' => 'Require expert/experience signals',
                                'geo_aeo_require_intent_coverage' => 'Require helpful intent coverage',
                                'geo_aeo_require_media_support' => 'Require image/video support',
                                'geo_aeo_require_schema_visibility' => 'Require schema to match visible content',
                                'geo_aeo_require_trust_signals' => 'Require trust/contact/authority signals',
                                'geo_aeo_require_brand_alignment' => 'Require Brand Intelligence alignment',
                                'geo_aeo_require_brand_proof' => 'Require verified brand proof signals',
                                'geo_aeo_require_brand_tone' => 'Require Shynh tone of voice',
                            ];
                            foreach ( $geo_toggles as $key => $label ) :
                            ?>
                                <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                    <label class="shynh-seo-switch">
                                        <input type="checkbox" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ?? true ); ?>>
                                        <span class="shynh-seo-slider"></span>
                                    </label>
                                    <strong><?php echo esc_html( $label ); ?></strong>
                                </div>
                            <?php endforeach; ?>

                            <div class="shynh-seo-field" style="margin-top:24px;">
                                <label for="shynh_seo_settings_geo_aeo_target_entities"><?php esc_html_e( 'Target entities tuy chinh', 'shynh-seo' ); ?></label>
                                <textarea id="shynh_seo_settings_geo_aeo_target_entities" name="shynh_seo_settings[geo_aeo_target_entities]" rows="4" placeholder="Shynh Super Luxury&#10;laser treatment&#10;skin rejuvenation"><?php echo esc_textarea( $settings['geo_aeo_target_entities'] ?? '' ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Moi dong mot entity. Validator se cong diem khi noi dung co cac entity nay.', 'shynh-seo' ); ?></p>
                            </div>
                        </div>

                        <!-- TAB: Brand AI Audit -->
                        <div id="tab-brand-audit" class="shynh-seo-vtab-pane">
                            <h4><?php esc_html_e( 'Brand Intelligence Audit', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">

                            <div class="shynh-seo-note shynh-seo-note--info">
                                <?php esc_html_e( 'Day la bo nao thuong hieu cho AI. Cac truong nay duoc dua vao prompt va analyzer de AI viet dung tam nhin, su menh, tone, proof, persona va claim safety cua Shynh.', 'shynh-seo' ); ?>
                            </div>

                            <div class="shynh-seo-brand-score-grid">
                                <?php
                                $brand_scores = [
                                    [ 'label' => 'Brand Fit', 'value' => $settings['brand_ai_min_brand_fit'] ?? 85, 'desc' => 'Nguong toi thieu de noi dung duoc xem la dung chat Shynh.' ],
                                    [ 'label' => 'Claim Safety', 'value' => $settings['brand_ai_min_claim_safety'] ?? 95, 'desc' => 'Nguong an toan cho claim tham my, y khoa va proof.' ],
                                    [ 'label' => 'Human Review', 'value' => ! empty( $settings['brand_ai_require_human_review'] ) ? 'ON' : 'OFF', 'desc' => 'YMYL va noi dung AI can nguoi duyet truoc khi publish.' ],
                                ];
                                foreach ( $brand_scores as $score_card ) :
                                ?>
                                    <div class="shynh-seo-brand-score-card">
                                        <strong><?php echo esc_html( $score_card['label'] ); ?></strong>
                                        <span><?php echo esc_html( (string) $score_card['value'] ); ?></span>
                                        <p><?php echo esc_html( $score_card['desc'] ); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Core Brand Profile', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">

                            <?php
                            $brand_textareas = [
                                'brand_positioning' => [ 'label' => 'Brand Positioning', 'rows' => 3, 'desc' => 'Dinh vi ngan gon de AI khong viet lech phan khuc premium.' ],
                                'brand_vision' => [ 'label' => 'Vision / Tam nhin', 'rows' => 4, 'desc' => 'Huong dai han, ly tuong thuong hieu, vai tro thi truong.' ],
                                'brand_mission' => [ 'label' => 'Mission / Su menh', 'rows' => 4, 'desc' => 'Ly do ton tai va cach Shynh phuc vu khach hang.' ],
                                'brand_values' => [ 'label' => 'Core Values', 'rows' => 6, 'desc' => 'Moi dong mot gia tri cot loi: tinh te, an toan, chuyen mon, ca nhan hoa...' ],
                                'brand_tone' => [ 'label' => 'Tone of Voice', 'rows' => 5, 'desc' => 'Moi dong mot tinh cach giong noi: premium, calm, trustworthy...' ],
                                'brand_personas' => [ 'label' => 'Target Personas', 'rows' => 7, 'desc' => 'Nhap persona, nhu cau, noi lo va giong noi phu hop.' ],
                                'brand_content_principles' => [ 'label' => 'Content Principles', 'rows' => 6, 'desc' => 'Nguyen tac viet bai de AI bam sat brand va YMYL.' ],
                            ];
                            foreach ( $brand_textareas as $key => $field ) :
                            ?>
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                    <textarea id="shynh_seo_settings_<?php echo esc_attr( $key ); ?>" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( $field['rows'] ); ?>"><?php echo esc_textarea( $settings[ $key ] ?? '' ); ?></textarea>
                                    <p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
                                </div>
                            <?php endforeach; ?>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Proof, Claims & Guardrails', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">

                            <?php
                            $brand_guardrails = [
                                'brand_proof_library' => [ 'label' => 'Verified Proof Library', 'rows' => 6, 'desc' => 'Bang chung AI duoc phep dung: chi nhanh, cong nghe, chung chi, bac si, review that, bao chi, giai thuong.' ],
                                'brand_source_policy' => [ 'label' => 'Source Policy', 'rows' => 4, 'desc' => 'Quy tac nguon du lieu: visible content, approved field, Knowledge Vault, verified proof.' ],
                                'brand_allowed_claims' => [ 'label' => 'Allowed / Safe Claims', 'rows' => 5, 'desc' => 'Moi dong mot claim an toan ma AI co the dung.' ],
                                'brand_banned_claims' => [ 'label' => 'Blocked Claims', 'rows' => 6, 'desc' => 'Moi dong mot claim bi chan hoac bat review thu cong.' ],
                                'brand_technologies' => [ 'label' => 'Approved Technologies', 'rows' => 5, 'desc' => 'Ten cong nghe chi dung khi co that va duoc duyet.' ],
                                'brand_medical_credentials' => [ 'label' => 'Approved Credentials', 'rows' => 5, 'desc' => 'Chung chi, bac si, uy tin chuyen mon da xac minh.' ],
                                'brand_usps' => [ 'label' => 'Approved USPs', 'rows' => 5, 'desc' => 'Diem khac biet chi duoc dung neu dung su that va co nguon.' ],
                                'brand_ctas' => [ 'label' => 'Approved CTAs', 'rows' => 4, 'desc' => 'CTA dung tone premium, khong hard-sell.' ],
                            ];
                            foreach ( $brand_guardrails as $key => $field ) :
                            ?>
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                    <textarea id="shynh_seo_settings_<?php echo esc_attr( $key ); ?>" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( $field['rows'] ); ?>"><?php echo esc_textarea( $settings[ $key ] ?? '' ); ?></textarea>
                                    <p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
                                </div>
                            <?php endforeach; ?>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'AI Audit Gates', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_brand_ai_min_brand_fit"><?php esc_html_e( 'Minimum Brand Fit Score', 'shynh-seo' ); ?></label>
                                    <input type="number" id="shynh_seo_settings_brand_ai_min_brand_fit" name="shynh_seo_settings[brand_ai_min_brand_fit]" value="<?php echo esc_attr( $settings['brand_ai_min_brand_fit'] ?? 85 ); ?>" min="0" max="100" />
                                </div>
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_brand_ai_min_claim_safety"><?php esc_html_e( 'Minimum Claim Safety Score', 'shynh-seo' ); ?></label>
                                    <input type="number" id="shynh_seo_settings_brand_ai_min_claim_safety" name="shynh_seo_settings[brand_ai_min_claim_safety]" value="<?php echo esc_attr( $settings['brand_ai_min_claim_safety'] ?? 95 ); ?>" min="0" max="100" />
                                </div>
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_brand_ai_unverified_claim_mode"><?php esc_html_e( 'Unverified Claim Mode', 'shynh-seo' ); ?></label>
                                    <select id="shynh_seo_settings_brand_ai_unverified_claim_mode" name="shynh_seo_settings[brand_ai_unverified_claim_mode]">
                                        <option value="block" <?php selected( $settings['brand_ai_unverified_claim_mode'] ?? 'block', 'block' ); ?>>Block</option>
                                        <option value="review" <?php selected( $settings['brand_ai_unverified_claim_mode'] ?? 'block', 'review' ); ?>>Needs Review</option>
                                        <option value="warn" <?php selected( $settings['brand_ai_unverified_claim_mode'] ?? 'block', 'warn' ); ?>>Warn Only</option>
                                    </select>
                                </div>
                                <div class="shynh-seo-field">
                                    <label for="shynh_seo_settings_brand_ai_tone_deviation_mode"><?php esc_html_e( 'Tone Deviation Mode', 'shynh-seo' ); ?></label>
                                    <select id="shynh_seo_settings_brand_ai_tone_deviation_mode" name="shynh_seo_settings[brand_ai_tone_deviation_mode]">
                                        <option value="block" <?php selected( $settings['brand_ai_tone_deviation_mode'] ?? 'review', 'block' ); ?>>Block</option>
                                        <option value="review" <?php selected( $settings['brand_ai_tone_deviation_mode'] ?? 'review', 'review' ); ?>>Needs Review</option>
                                        <option value="warn" <?php selected( $settings['brand_ai_tone_deviation_mode'] ?? 'review', 'warn' ); ?>>Warn Only</option>
                                    </select>
                                </div>
                            </div>

                            <?php
                            $brand_audit_toggles = [
                                'brand_ai_audit_enabled' => 'Enable Brand Intelligence Audit',
                                'brand_ai_entity_lock' => 'Lock brand/entity facts',
                                'brand_ai_require_source_map' => 'Require source_map in AI output',
                                'brand_ai_require_claim_map' => 'Require claim_map in AI output',
                                'brand_ai_require_human_review' => 'Require human review for YMYL / brand-risk content',
                                'brand_ai_proof_required' => 'Require verified proof for strong claims',
                                'brand_ai_output_contract' => 'Force structured AI output contract',
                            ];
                            foreach ( $brand_audit_toggles as $key => $label ) :
                            ?>
                                <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                    <label class="shynh-seo-switch">
                                        <input type="checkbox" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ?? true ); ?>>
                                        <span class="shynh-seo-slider"></span>
                                    </label>
                                    <strong><?php echo esc_html( $label ); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- TAB: AI Sync -->
                        <div id="tab-ai-sync" class="shynh-seo-vtab-pane">
                            <h4><?php esc_html_e( 'AI English SEO Sync', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-note shynh-seo-note--info">
                                <?php esc_html_e( 'Workflow nay gom du lieu bai viet/term tieng Viet, mo popup review cac truong SEO quan trong, roi moi tao English draft va link Polylang neu duoc bat.', 'shynh-seo' ); ?>
                            </div>

                            <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[ai_sync_enabled]" value="1" <?php checked( $settings['ai_sync_enabled'] ?? false ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Bat AI English SEO Sync', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top:2px;"><?php esc_html_e( 'Khi bat, editor se co hop AI English SEO Sync tren cac post type duoc chon.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_settings[ai_sync_force_en_noindex]" value="1" <?php checked( $settings['ai_sync_force_en_noindex'] ?? false ); ?>>
                                    <span class="shynh-seo-slider"></span>
                                </label>
                                <div>
                                    <strong><?php esc_html_e( 'Force Noindex/Nofollow cho bản tiếng Anh (Chờ nhập thông tin)', 'shynh-seo' ); ?></strong>
                                    <p class="description" style="margin-top:2px;"><?php esc_html_e( 'Tự động chèn thẻ noindex, nofollow cho toàn bộ các trang tiếng Anh để tránh Google quét khi chưa hoàn thiện.', 'shynh-seo' ); ?></p>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin:24px 0;">
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_ai_sync_openai_model"><?php esc_html_e( 'Default model', 'shynh-seo' ); ?></label>
                                    <input type="text" id="shynh_seo_settings_ai_sync_openai_model" name="shynh_seo_settings[ai_sync_openai_model]" value="<?php echo esc_attr( $settings['ai_sync_openai_model'] ?? 'gpt-5.6-luna' ); ?>" placeholder="gpt-5.6-luna" />
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_ai_sync_openai_model_economy"><?php esc_html_e( 'Economy model', 'shynh-seo' ); ?></label>
                                    <input type="text" id="shynh_seo_settings_ai_sync_openai_model_economy" name="shynh_seo_settings[ai_sync_openai_model_economy]" value="<?php echo esc_attr( $settings['ai_sync_openai_model_economy'] ?? 'gpt-5.6-luna' ); ?>" placeholder="gpt-5.6-luna" />
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_ai_sync_openai_model_important"><?php esc_html_e( 'Important model', 'shynh-seo' ); ?></label>
                                    <input type="text" id="shynh_seo_settings_ai_sync_openai_model_important" name="shynh_seo_settings[ai_sync_openai_model_important]" value="<?php echo esc_attr( $settings['ai_sync_openai_model_important'] ?? 'gpt-5.6-sol' ); ?>" placeholder="gpt-5.6-sol" />
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_ai_sync_target_language"><?php esc_html_e( 'Target language code', 'shynh-seo' ); ?></label>
                                    <input type="text" id="shynh_seo_settings_ai_sync_target_language" name="shynh_seo_settings[ai_sync_target_language]" value="<?php echo esc_attr( $settings['ai_sync_target_language'] ?? 'en' ); ?>" placeholder="en" />
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label for="shynh_seo_settings_ai_sync_target_locale"><?php esc_html_e( 'Target locale', 'shynh-seo' ); ?></label>
                                    <input type="text" id="shynh_seo_settings_ai_sync_target_locale" name="shynh_seo_settings[ai_sync_target_locale]" value="<?php echo esc_attr( $settings['ai_sync_target_locale'] ?? 'en_US' ); ?>" placeholder="en_US" />
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_ai_sync_openai_api_key"><?php esc_html_e( 'OpenAI API key', 'shynh-seo' ); ?></label>
                                <input type="password" id="shynh_seo_settings_ai_sync_openai_api_key" name="shynh_seo_settings[ai_sync_openai_api_key]" value="<?php echo esc_attr( $settings['ai_sync_openai_api_key'] ?? '' ); ?>" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'Neu de trong, plugin van mo duoc popup wrap-up nhung chua goi AI.', 'shynh-seo' ); ?></p>
                            </div>

                            <h4 style="margin-top:32px;">🧠 Shynh Brand Brain (Bảng Tri Thức Thương Hiệu 6 Trụ Cột Cho AI)</h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_vision"><strong>1. 🏛️ Mục tiêu, Định vị & Khách hàng Mục tiêu (Brand Vision & High-Net-Worth Personas)</strong></label>
                                <textarea id="shynh_seo_settings_brand_vision" name="shynh_seo_settings[brand_vision]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_vision'] ?? '' ); ?></textarea>
                                <p class="description">Định vị thương hiệu, đối tượng khách hàng mục tiêu thượng lưu VIP, tiêu chuẩn 5 sao, tông giọng chủ đạo.</p>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_technologies"><strong>2. ⚡ Ma trận Công nghệ & Dịch vụ Mũi nhọn (Flagship Technologies & Injectables)</strong></label>
                                <textarea id="shynh_seo_settings_brand_technologies" name="shynh_seo_settings[brand_technologies]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_technologies'] ?? '' ); ?></textarea>
                                <p class="description">Danh sách máy móc công nghệ cao độc quyền (Thermage FLX 2026, Ultherapy Prime, Laser Picoway, Exosome, Allergan, Galderma...).</p>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_medical_credentials"><strong>3. 🩺 Quy chuẩn Y Khoa, Bác sĩ CKI & YMYL Safety (Medical Credentials & Doctor Roster)</strong></label>
                                <textarea id="shynh_seo_settings_brand_medical_credentials" name="shynh_seo_settings[brand_medical_credentials]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_medical_credentials'] ?? '' ); ?></textarea>
                                <p class="description">Bằng chứng an toàn y tế FDA Hoa Kỳ, CE Châu Âu, đội ngũ Bác sĩ CKI Da liễu, vô trùng phòng Lab, 0 ngày nghỉ dưỡng.</p>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_market_trends"><strong>4. 📈 Xu hướng Thị trường 2026 & Nỗi đau Khách hàng (Market Trends & Pain-Point Mapping)</strong></label>
                                <textarea id="shynh_seo_settings_brand_market_trends" name="shynh_seo_settings[brand_market_trends]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_market_trends'] ?? '' ); ?></textarea>
                                <p class="description">Pre-juvenation, Nâng cơ không phẫu thuật, Thẩm mỹ tái tạo tế bào. Trực tiếp giải đáp: Giá bao nhiêu, Giữ được bao lâu, Có đau không.</p>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_usps"><strong>5. 🏆 5 USP Độc quyền của Tập đoàn Shynh Super Luxury (Competitive Edge)</strong></label>
                                <textarea id="shynh_seo_settings_brand_usps" name="shynh_seo_settings[brand_usps]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_usps'] ?? '' ); ?></textarea>
                                <p class="description">1. 100% Máy check mã QR; 2. Phòng VIP 1:1 bảo mật; 3. Phác đồ cá nhân hóa đa tầng; 4. Trải nghiệm Spa 5 sao; 5. Đồng hành 365 ngày.</p>
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_brand_ctas"><strong>6. 🎯 Bộ Lời kêu gọi Đặt lịch & Chuyển đổi (Luxury CTAs)</strong></label>
                                <textarea id="shynh_seo_settings_brand_ctas" name="shynh_seo_settings[brand_ctas]" rows="2" style="width:100%;"><?php echo esc_textarea( $settings['brand_ctas'] ?? '' ); ?></textarea>
                                <p class="description">Lời kêu gọi chuyển đổi thượng lưu ("Đặt lịch soi da AI 3D trực tiếp cùng Bác sĩ Trưởng khoa").</p>
                            </div>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Output controls', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">
                            <?php
                            $ai_sync_toggles = [
                                'ai_sync_write_yoast'     => 'Write Yoast SEO data',
                                'ai_sync_write_shynh_seo' => 'Write Shynh SEO data',
                                'ai_sync_translate_terms' => 'Translate/link categories and terms',
                                'ai_sync_link_polylang'   => 'Link source and English draft with Polylang',
                                'ai_sync_auto_publish'    => 'Allow auto publish',
                            ];
                            foreach ( $ai_sync_toggles as $key => $label ) :
                            ?>
                                <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                    <label class="shynh-seo-switch">
                                        <input type="checkbox" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ?? false ); ?>>
                                        <span class="shynh-seo-slider"></span>
                                    </label>
                                    <div>
                                        <strong><?php echo esc_html( $label ); ?></strong>
                                        <?php if ( 'ai_sync_write_yoast' === $key ) : ?>
                                            <p class="description" style="margin-top:2px;"><?php esc_html_e( 'Mac dinh tat. Khi tat, workflow khong tao va khong ghi keyword/title/meta Yoast.', 'shynh-seo' ); ?></p>
                                        <?php elseif ( 'ai_sync_auto_publish' === $key ) : ?>
                                            <p class="description" style="margin-top:2px;"><?php esc_html_e( 'Nen de tat. Mac dinh plugin chi tao Draft sau khi editor duyet popup.', 'shynh-seo' ); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Image handling', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">
                            <div class="shynh-seo-note shynh-seo-note--info">
                                <?php esc_html_e( 'Mac dinh plugin giu lai image/media blocks va featured image cua bai goc. AI chi goi y alt/title/caption, khong duoc lam mat anh.', 'shynh-seo' ); ?>
                            </div>
                            <?php
                            $ai_image_toggles = [
                                'ai_sync_preserve_images' => [
                                    'label' => 'Preserve original content images',
                                    'desc'  => 'Giữ image/gallery/cover/media/video blocks từ bài gốc trong bản dịch.',
                                ],
                                'ai_sync_copy_featured_image' => [
                                    'label' => 'Copy featured image to translation',
                                    'desc'  => 'Bản English dùng cùng featured image nếu chưa có ảnh dịch riêng.',
                                ],
                                'ai_sync_suggest_image_metadata' => [
                                    'label' => 'Suggest English image alt/title/caption',
                                    'desc'  => 'AI gợi ý metadata ảnh để editor duyệt trong popup.',
                                ],
                                'ai_sync_auto_apply_image_metadata' => [
                                    'label' => 'Auto-apply image metadata after review',
                                    'desc'  => 'Mặc định tắt. Khi tắt, chỉ những ảnh được tick trong popup mới được ghi metadata.',
                                ],
                                'ai_sync_suggest_additional_images' => [
                                    'label' => 'Suggest additional images if content needs support',
                                    'desc'  => 'AI có thể đề xuất vị trí cần thêm ảnh, ưu tiên Media Library/site assets.',
                                ],
                                'ai_sync_allow_ai_generated_images' => [
                                    'label' => 'Allow AI-generated images when no site asset fits',
                                    'desc'  => 'Mặc định tắt. Chỉ bật khi muốn AI đề xuất tạo ảnh mới.',
                                ],
                            ];
                            foreach ( $ai_image_toggles as $key => $data ) :
                            ?>
                                <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                    <label class="shynh-seo-switch">
                                        <input type="checkbox" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ?? false ); ?>>
                                        <span class="shynh-seo-slider"></span>
                                    </label>
                                    <div>
                                        <strong><?php echo esc_html( $data['label'] ); ?></strong>
                                        <p class="description" style="margin-top:2px;"><?php echo esc_html( $data['desc'] ); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <h4 style="margin-top:32px;"><?php esc_html_e( 'Auto coverage', 'shynh-seo' ); ?></h4>
                            <hr style="border:0; border-top:1px solid var(--shynh-border-gray); margin:20px 0;">
                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin:24px 0;">
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[ai_sync_auto_include_post_types]" value="1" <?php checked( $settings['ai_sync_auto_include_post_types'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu ap dung public post type moi', 'shynh-seo' ); ?>
                                    </label>
                                </div>
                                <div class="shynh-seo-field" style="margin-bottom:0;">
                                    <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
                                        <input type="checkbox" name="shynh_seo_settings[ai_sync_auto_include_taxonomies]" value="1" <?php checked( $settings['ai_sync_auto_include_taxonomies'] ?? true ); ?>>
                                        <?php esc_html_e( 'Tu ap dung public taxonomy moi', 'shynh-seo' ); ?>
                                    </label>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label><?php esc_html_e( 'Post types co AI Sync', 'shynh-seo' ); ?></label>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 16px; margin-top:12px;">
                                    <?php
                                    $ai_post_types = ! empty( $settings['ai_sync_auto_include_post_types'] )
                                        ? array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), array_merge( [ 'attachment' ], (array) ( $settings['ai_sync_excluded_post_types'] ?? [] ) ) ) )
                                        : (array) ( $settings['ai_sync_post_types'] ?? [] );
                                    foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
                                        if ( 'attachment' === $pt->name ) {
                                            continue;
                                        }
                                        echo '<input type="hidden" name="shynh_seo_settings[ai_sync_known_post_types][]" value="' . esc_attr( $pt->name ) . '">';
                                        echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px; margin:0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[ai_sync_post_types][]" value="' . esc_attr( $pt->name ) . '" ' . checked( in_array( $pt->name, $ai_post_types, true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $pt->label ) . ' <code style="font-size:11px;">' . esc_html( $pt->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="shynh-seo-field">
                                <label><?php esc_html_e( 'Taxonomies/terms co AI Sync', 'shynh-seo' ); ?></label>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 16px; margin-top:12px;">
                                    <?php
                                    $ai_taxonomies = ! empty( $settings['ai_sync_auto_include_taxonomies'] )
                                        ? array_values( array_diff( get_taxonomies( [ 'public' => true ], 'names' ), array_merge( [ 'post_format' ], (array) ( $settings['ai_sync_excluded_taxonomies'] ?? [] ) ) ) )
                                        : (array) ( $settings['ai_sync_taxonomies'] ?? [] );
                                    foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
                                        if ( 'post_format' === $tax->name ) {
                                            continue;
                                        }
                                        echo '<input type="hidden" name="shynh_seo_settings[ai_sync_known_taxonomies][]" value="' . esc_attr( $tax->name ) . '">';
                                        echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px; margin:0;">';
                                        echo '<input type="checkbox" name="shynh_seo_settings[ai_sync_taxonomies][]" value="' . esc_attr( $tax->name ) . '" ' . checked( in_array( $tax->name, $ai_taxonomies, true ), true, false ) . '>';
                                        echo '<span>' . esc_html( $tax->label ) . ' <code style="font-size:11px;">' . esc_html( $tax->name ) . '</code></span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: Knowledge Vault -->
                        <div id="tab-knowledge-vault" class="shynh-seo-vtab-pane">
                            <h4>📂 Kho Tài Nguyên & Tri Thức Dịch Vụ Tập Đoàn (Flexible Nested Tree Vault)</h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">

                            <div class="shynh-seo-note shynh-seo-note--info" style="margin-bottom:20px;">
                                QUẢN LÝ TÀI NGUYÊN CẤU TRÚC CÂY: Chọn danh mục cha/con để nạp Phác đồ y khoa gốc (Upload PDF/DOCX, Link S3, Google Drive) và Kho Media Social chính chủ (YouTube Videos/Shorts, Facebook Reels, TikTok Clips). Hệ thống có <strong>Cơ chế Thừa Kế Tri Thức Cây (Inheritance Engine)</strong> tự động kế thừa từ cấp cha nếu dịch vụ con chưa nạp riêng.
                            </div>

                            <div style="background:var(--shynh-bg-light, #f8fafc); border:1px solid var(--shynh-border-gray, #e2e8f0); border-radius:12px; padding:20px; margin-bottom:24px;">
                                <h5 style="margin-top:0; font-size:16px;">🌲 Cây Thư Mục Danh Mục Dịch Vụ (Taxonomy Directory Tree)</h5>
                                <div class="shynh-seo-knowledge-tree-wrap">
                                    <?php
                                    $terms = get_terms( [
                                        'taxonomy'   => [ 'category', 'loai-dich-vu' ],
                                        'hide_empty' => false,
                                        'parent'     => 0,
                                    ] );

                                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) :
                                        echo '<ul style="margin:0; padding-left:10px; list-style:none;">';
                                        foreach ( $terms as $p_term ) :
                                            echo '<li style="margin-bottom:12px; background:#fff; padding:12px; border-radius:8px; border:1px solid #cbd5e1;">';
                                            echo '<strong style="font-size:15px; color:#1e293b;">📁 ' . esc_html( $p_term->name ) . '</strong> <code style="font-size:11px;">(' . esc_html( $p_term->taxonomy ) . ')</code>';

                                            $children = get_terms( [
                                                'taxonomy'   => $p_term->taxonomy,
                                                'hide_empty' => false,
                                                'parent'     => $p_term->term_id,
                                            ] );

                                            if ( ! empty( $children ) && ! is_wp_error( $children ) ) :
                                                echo '<ul style="margin-top:8px; padding-left:24px; list-style:none;">';
                                                foreach ( $children as $c_term ) :
                                                    echo '<li style="margin-bottom:6px; color:#475569;">🔹 <strong>' . esc_html( $c_term->name ) . '</strong></li>';
                                                endforeach;
                                                echo '</ul>';
                                            endif;

                                            echo '</li>';
                                        endforeach;
                                        echo '</ul>';
                                    else :
                                        echo '<p class="description">Chưa có danh mục dịch vụ nào trong CSDL.</p>';
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: Schema -->
                        <div id="tab-schema" class="shynh-seo-vtab-pane">
                            <h4><?php esc_html_e( 'Cấu hình Schema Markup (JSON-LD)', 'shynh-seo' ); ?></h4>
                            <hr style="border: 0; border-top: 1px solid var(--shynh-border-gray); margin: 20px 0;">
                            
                            <?php
                            $schema_toggles = [
                                'schema_enabled' => 'Enable JSON-LD schema',
                                'schema_auto_detect_type' => 'Auto detect schema type',
                                'schema_enable_faq' => 'Enable FAQPage schema',
                                'schema_enable_service' => 'Enable Service schema',
                                'schema_enable_medical_web_page' => 'Enable MedicalWebPage schema',
                                'schema_enable_collection_page' => 'Enable CollectionPage schema for terms',
                                'schema_require_human_review' => 'Require human review for medical-aesthetic content',
                            ];
                            foreach ( $schema_toggles as $key => $label ) :
                            ?>
                                <div class="shynh-seo-field" style="display:flex; align-items:center; gap:12px; margin-top:16px;">
                                    <label class="shynh-seo-switch">
                                        <input type="checkbox" name="shynh_seo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ?? true ); ?>>
                                        <span class="shynh-seo-slider"></span>
                                    </label>
                                    <strong><?php echo esc_html( $label ); ?></strong>
                                </div>
                            <?php endforeach; ?>

                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_org_name"><?php esc_html_e( 'Tên Tổ chức / Công ty', 'shynh-seo' ); ?></label>
                                <input type="text" id="shynh_seo_settings_org_name" name="shynh_seo_settings[org_name]" value="<?php echo esc_attr( $settings['org_name'] ); ?>" />
                            </div>
                            <div class="shynh-seo-field">
                                <label for="shynh_seo_settings_org_logo"><?php esc_html_e( 'Logo Tổ chức', 'shynh-seo' ); ?></label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" id="shynh_seo_settings_org_logo" name="shynh_seo_settings[org_logo]" value="<?php echo esc_attr( $settings['org_logo'] ); ?>" />
                                    <button type="button" class="shynh-seo-btn shynh-seo-btn--secondary shynh-seo-media-upload-btn" data-target="#shynh_seo_settings_org_logo"><?php esc_html_e( 'Chọn Logo', 'shynh-seo' ); ?></button>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--shynh-border-gray);">
                            <button type="submit" class="shynh-seo-btn shynh-seo-btn--primary"><?php esc_html_e( 'Lưu Cài Đặt', 'shynh-seo' ); ?></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            // Vertical Tabs
            $('.shynh-seo-vtabs-nav li').on('click', function() {
                $('.shynh-seo-vtabs-nav li').removeClass('active');
                $(this).addClass('active');
                
                $('.shynh-seo-vtab-pane').removeClass('active');
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
                    // Basic insert at end
                    $input.val(currentVal + (currentVal ? ' ' : '') + text).trigger('input');
                    $input.focus();
                }
            });
        });
        </script>
        <?php
    }

    private function render_sitemap_profile_select( $name, $selected = 'auto', $id = '' ) {
        $profiles = [
            'auto'    => __( 'Auto', 'shynh-seo' ),
            'fresh'   => __( 'Fresh - cập nhật rất thường xuyên', 'shynh-seo' ),
            'dynamic' => __( 'Dynamic - thay đổi đều đặn', 'shynh-seo' ),
            'stable'  => __( 'Stable - ít thay đổi', 'shynh-seo' ),
            'archive' => __( 'Archive - gần như cố định', 'shynh-seo' ),
        ];

        echo '<select ' . ( $id ? 'id="' . esc_attr( $id ) . '" ' : '' ) . 'name="' . esc_attr( $name ) . '" style="width:100%;">';
        foreach ( $profiles as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }
}
