<?php
/**
 * SEO Rules Configuration Page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Rules {

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
        register_setting( 'shynh_seo_rules_group', 'shynh_seo_rules', [ $this, 'sanitize' ] );
    }

    public function sanitize( $input ) {
        if ( isset( $_POST['reset_rules'] ) ) {
            return Shynh_SEO_Core::get_default_rules();
        }

        $sanitized = [];
        $defaults  = Shynh_SEO_Core::get_default_rules();

        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        foreach ( $defaults as $key => $default_rule ) {
            if ( isset( $input[ $key ] ) ) {
                $sanitized[ $key ] = [
                    'label'       => $default_rule['label'],
                    'description' => $default_rule['description'],
                    'enabled'     => isset( $input[ $key ]['enabled'] ),
                    'weight'      => isset( $input[ $key ]['weight'] ) ? absint( $input[ $key ]['weight'] ) : $default_rule['weight'],
                ];
                
                $threshold_keys = [ 'green_min', 'green_max', 'orange_min', 'orange_max' ];
                foreach ( $threshold_keys as $t_key ) {
                    if ( isset( $default_rule[ $t_key ] ) ) {
                        $sanitized[ $key ][ $t_key ] = isset( $input[ $key ][ $t_key ] ) ? (float) $input[ $key ][ $t_key ] : $default_rule[ $t_key ];
                    }
                }
            } else {
                $sanitized[ $key ] = $default_rule;
                $sanitized[ $key ]['enabled'] = false;
            }
        }

        return $sanitized;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rules = get_option( 'shynh_seo_rules', Shynh_SEO_Core::get_default_rules() );
        ?>
        <div class="wrap shynh-seo-wrap">
            <h1 class="shynh-seo-page-title">
                <span class="dashicons dashicons-forms"></span>
                <?php esc_html_e( 'Quản Lý SEO Rules (Trọng số)', 'shynh-seo' ); ?>
            </h1>

            <div class="shynh-seo-card" style="margin-bottom: 24px;">
                <p><?php esc_html_e( 'Tùy chỉnh các tiêu chí chấm điểm bài viết tại đây. Hệ thống sẽ áp dụng "Cơ Chế Một" này cho toàn bộ quá trình phân tích bài viết mới và cũ.', 'shynh-seo' ); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields( 'shynh_seo_rules_group' ); ?>
                
                <div class="shynh-seo-rules-grid">
                    <?php foreach ( $rules as $key => $rule ) : ?>
                        <div class="shynh-seo-rule-card">
                            <div class="shynh-seo-rule-toggle">
                                <label class="shynh-seo-switch">
                                    <input type="checkbox" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $rule['enabled'] ?? true ); ?> />
                                    <span class="shynh-seo-slider"></span>
                                </label>
                            </div>
                            
                            <div class="shynh-seo-rule-info">
                                <strong><?php echo esc_html( $rule['label'] ?? $key ); ?></strong>
                                <p><?php echo esc_html( $rule['description'] ?? '' ); ?></p>
                            </div>

                            <div class="shynh-seo-rule-settings">
                                <div class="shynh-seo-field" style="margin-bottom: 0;">
                                    <label style="font-size: 11px; margin-bottom: 4px;"><?php esc_html_e( 'Điểm/Trọng số', 'shynh-seo' ); ?></label>
                                    <input type="number" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][weight]" value="<?php echo esc_attr( $rule['weight'] ?? 0 ); ?>" />
                                </div>
                                
                                <?php if ( isset( $rule['green_min'] ) ) : ?>
                                    <div class="shynh-seo-field" style="margin-bottom: 0;">
                                        <label style="font-size: 11px; color: #059669; margin-bottom: 4px;">Min <span class="dashicons dashicons-yes"></span></label>
                                        <input type="number" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][green_min]" value="<?php echo esc_attr( $rule['green_min'] ); ?>" />
                                    </div>
                                <?php endif; ?>

                                <?php if ( isset( $rule['green_max'] ) ) : ?>
                                    <div class="shynh-seo-field" style="margin-bottom: 0;">
                                        <label style="font-size: 11px; color: #059669; margin-bottom: 4px;">Max <span class="dashicons dashicons-yes"></span></label>
                                        <input type="number" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][green_max]" value="<?php echo esc_attr( $rule['green_max'] ); ?>" />
                                    </div>
                                <?php endif; ?>

                                <?php if ( isset( $rule['orange_min'] ) ) : ?>
                                    <div class="shynh-seo-field" style="margin-bottom: 0;">
                                        <label style="font-size: 11px; color: #d97706; margin-bottom: 4px;">Min <span class="dashicons dashicons-warning"></span></label>
                                        <input type="number" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][orange_min]" value="<?php echo esc_attr( $rule['orange_min'] ); ?>" />
                                    </div>
                                <?php endif; ?>

                                <?php if ( isset( $rule['orange_max'] ) ) : ?>
                                    <div class="shynh-seo-field" style="margin-bottom: 0;">
                                        <label style="font-size: 11px; color: #d97706; margin-bottom: 4px;">Max <span class="dashicons dashicons-warning"></span></label>
                                        <input type="number" name="shynh_seo_rules[<?php echo esc_attr( $key ); ?>][orange_max]" value="<?php echo esc_attr( $rule['orange_max'] ); ?>" />
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="shynh-seo-card" style="margin-top: 32px; display: flex; gap: 16px;">
                    <button type="submit" class="shynh-seo-btn shynh-seo-btn--primary"><?php esc_html_e( 'Lưu Thay Đổi', 'shynh-seo' ); ?></button>
                    <button type="submit" name="reset_rules" value="1" class="shynh-seo-btn shynh-seo-btn--secondary" onclick="return confirm('Bạn có chắc chắn muốn khôi phục toàn bộ SEO Rules về mặc định?');"><?php esc_html_e( 'Khôi Phục Mặc Định', 'shynh-seo' ); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}
