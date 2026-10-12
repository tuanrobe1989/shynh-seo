<?php
/**
 * Shynh SEO — API admin UI.
 *
 * Adds the "API & AI Access" submenu with three tabs:
 *   - Docs   : bundled (offline) Swagger UI pointing at /openapi.json.
 *   - Keys   : create / revoke API keys, toggle the API on/off.
 *   - Log    : recent API access entries.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_API_Admin {

    const MENU_SLUG   = 'shynh-seo-api';
    const NEW_KEY_TRANSIENT = 'shynh_seo_new_api_key_';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_shynh_seo_api_create_key', [ $this, 'handle_create_key' ] );
        add_action( 'admin_post_shynh_seo_api_revoke_key', [ $this, 'handle_revoke_key' ] );
        add_action( 'admin_post_shynh_seo_api_toggle', [ $this, 'handle_toggle' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'shynh-seo',
            __( 'API & AI Access', 'shynh-seo' ),
            __( 'API & AI Access', 'shynh-seo' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
            return;
        }

        $base = SHYNH_SEO_URL . 'assets/vendor/swagger-ui/';
        $ver  = defined( 'SHYNH_SEO_VERSION' ) ? SHYNH_SEO_VERSION : '1.0.0';

        wp_enqueue_style( 'shynh-seo-swagger', $base . 'swagger-ui.css', [], $ver );
        wp_enqueue_style( 'shynh-seo-api-admin', SHYNH_SEO_URL . 'assets/css/api-admin.css', [], $ver );

        wp_enqueue_script( 'shynh-seo-swagger', $base . 'swagger-ui-bundle.js', [], $ver, true );
        wp_enqueue_script( 'shynh-seo-api-docs', SHYNH_SEO_URL . 'assets/js/api-docs.js', [ 'shynh-seo-swagger' ], $ver, true );

        wp_localize_script( 'shynh-seo-api-docs', 'ShynhSeoApiDocs', [
            'specUrl' => rest_url( Shynh_SEO_API::NAMESPACE . '/openapi.json' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'docs';
        $base_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        ?>
        <div class="wrap shynh-seo-api-wrap">
            <h1><?php esc_html_e( 'Shynh SEO — API & AI Access', 'shynh-seo' ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( $base_url . '&tab=docs' ); ?>" class="nav-tab <?php echo 'docs' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tài liệu (Swagger)', 'shynh-seo' ); ?></a>
                <a href="<?php echo esc_url( $base_url . '&tab=keys' ); ?>" class="nav-tab <?php echo 'keys' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'API Keys', 'shynh-seo' ); ?></a>
                <a href="<?php echo esc_url( $base_url . '&tab=log' ); ?>" class="nav-tab <?php echo 'log' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Nhật ký truy cập', 'shynh-seo' ); ?></a>
            </h2>

            <?php
            if ( 'keys' === $tab ) {
                $this->render_keys_tab();
            } elseif ( 'log' === $tab ) {
                $this->render_log_tab();
            } else {
                $this->render_docs_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_docs_tab() {
        $enabled = Shynh_SEO_API_Keys::is_api_enabled();
        ?>
        <?php if ( ! $enabled ) : ?>
            <div class="notice notice-warning inline"><p>
                <?php esc_html_e( 'API đang TẮT với client bên ngoài. Bạn (admin đăng nhập) vẫn thử được ngay dưới đây. Sang tab "API Keys" để bật và tạo key cho AI.', 'shynh-seo' ); ?>
            </p></div>
        <?php endif; ?>

        <p class="description">
            <?php
            printf(
                /* translators: %s: REST base URL */
                esc_html__( 'Base URL: %s — AI gọi API với header X-Shynh-API-Key. Giao diện dưới đây được nhúng cục bộ, không cần internet.', 'shynh-seo' ),
                '<code>' . esc_html( untrailingslashit( rest_url( Shynh_SEO_API::NAMESPACE ) ) ) . '</code>'
            );
            ?>
        </p>

        <div id="shynh-seo-swagger-ui"></div>
        <?php
    }

    private function render_keys_tab() {
        $new_key = get_transient( self::NEW_KEY_TRANSIENT . get_current_user_id() );
        if ( $new_key ) {
            delete_transient( self::NEW_KEY_TRANSIENT . get_current_user_id() );
        }
        $enabled = Shynh_SEO_API_Keys::is_api_enabled();
        $keys    = Shynh_SEO_API_Keys::get_all();
        ?>
        <?php if ( $new_key ) : ?>
            <div class="notice notice-success"><p>
                <strong><?php esc_html_e( 'Key mới đã tạo — chỉ hiển thị 1 lần duy nhất. Hãy sao chép ngay:', 'shynh-seo' ); ?></strong><br>
                <code class="shynh-seo-new-key"><?php echo esc_html( $new_key ); ?></code>
            </p></div>
        <?php endif; ?>

        <div class="shynh-seo-api-toggle card">
            <h2><?php esc_html_e( 'Trạng thái API', 'shynh-seo' ); ?></h2>
            <p>
                <?php
                echo $enabled
                    ? '<span style="color:#1a7f37;font-weight:600;">● ' . esc_html__( 'Đang BẬT', 'shynh-seo' ) . '</span>'
                    : '<span style="color:#b32d2e;font-weight:600;">● ' . esc_html__( 'Đang TẮT', 'shynh-seo' ) . '</span>';
                ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="shynh_seo_api_toggle" />
                <input type="hidden" name="enable" value="<?php echo $enabled ? '0' : '1'; ?>" />
                <?php wp_nonce_field( 'shynh_seo_api_toggle' ); ?>
                <button type="submit" class="button <?php echo $enabled ? '' : 'button-primary'; ?>">
                    <?php echo $enabled ? esc_html__( 'Tắt API', 'shynh-seo' ) : esc_html__( 'Bật API', 'shynh-seo' ); ?>
                </button>
            </form>
        </div>

        <div class="card">
            <h2><?php esc_html_e( 'Tạo API Key mới', 'shynh-seo' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="shynh_seo_api_create_key" />
                <?php wp_nonce_field( 'shynh_seo_api_create_key' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="shynh-key-label"><?php esc_html_e( 'Tên/ghi chú', 'shynh-seo' ); ?></label></th>
                        <td><input type="text" id="shynh-key-label" name="label" class="regular-text" placeholder="VD: ChatGPT SEO Planner" required /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Quyền (scope)', 'shynh-seo' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="scopes[]" value="read" checked /> <?php esc_html_e( 'read — đọc dữ liệu SEO/AEO/GEO', 'shynh-seo' ); ?></label><br>
                            <label><input type="checkbox" name="scopes[]" value="write" /> <?php esc_html_e( 'write — cập nhật meta SEO / FAQ (thận trọng)', 'shynh-seo' ); ?></label>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Tạo Key', 'shynh-seo' ); ?></button></p>
            </form>
        </div>

        <div class="card">
            <h2><?php esc_html_e( 'Danh sách Key', 'shynh-seo' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tên', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Prefix', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Scope', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Tạo lúc', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Dùng gần nhất', 'shynh-seo' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $keys ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'Chưa có key nào.', 'shynh-seo' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $keys as $k ) : ?>
                            <tr>
                                <td><?php echo esc_html( $k['label'] ); ?></td>
                                <td><code><?php echo esc_html( $k['prefix'] ); ?>…</code></td>
                                <td><?php echo esc_html( implode( ', ', $k['scopes'] ) ); ?></td>
                                <td><?php echo $k['created'] ? esc_html( wp_date( 'Y-m-d H:i', $k['created'] ) ) : '—'; ?></td>
                                <td><?php echo $k['last_used'] ? esc_html( wp_date( 'Y-m-d H:i', $k['last_used'] ) ) : '—'; ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Thu hồi key này?', 'shynh-seo' ) ); ?>');">
                                        <input type="hidden" name="action" value="shynh_seo_api_revoke_key" />
                                        <input type="hidden" name="id" value="<?php echo esc_attr( $k['id'] ); ?>" />
                                        <?php wp_nonce_field( 'shynh_seo_api_revoke_key' ); ?>
                                        <button type="submit" class="button-link-delete button-link"><?php esc_html_e( 'Thu hồi', 'shynh-seo' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_log_tab() {
        $log = get_option( Shynh_SEO_API::LOG_OPTION, [] );
        $log = is_array( $log ) ? $log : [];
        ?>
        <div class="card">
            <h2><?php esc_html_e( '200 lần gọi gần nhất', 'shynh-seo' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Thời gian', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Client', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Method', 'shynh-seo' ); ?></th>
                        <th><?php esc_html_e( 'Route', 'shynh-seo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $log ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Chưa có lượt gọi nào.', 'shynh-seo' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $log as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $entry['time'] ) ); ?></td>
                                <td><?php echo esc_html( $entry['actor'] ?? '' ); ?></td>
                                <td><code><?php echo esc_html( $entry['method'] ?? '' ); ?></code></td>
                                <td><code><?php echo esc_html( $entry['route'] ?? '' ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Form handlers
     * ------------------------------------------------------------------- */

    public function handle_create_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Không đủ quyền.', 'shynh-seo' ) );
        }
        check_admin_referer( 'shynh_seo_api_create_key' );

        $label  = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $scopes = isset( $_POST['scopes'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['scopes'] ) ) : [];

        $result = Shynh_SEO_API_Keys::generate( $label, $scopes );
        set_transient( self::NEW_KEY_TRANSIENT . get_current_user_id(), $result['key'], 300 );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=keys' ) );
        exit;
    }

    public function handle_revoke_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Không đủ quyền.', 'shynh-seo' ) );
        }
        check_admin_referer( 'shynh_seo_api_revoke_key' );

        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        if ( $id ) {
            Shynh_SEO_API_Keys::revoke( $id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=keys' ) );
        exit;
    }

    public function handle_toggle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Không đủ quyền.', 'shynh-seo' ) );
        }
        check_admin_referer( 'shynh_seo_api_toggle' );

        $enable = isset( $_POST['enable'] ) && '1' === $_POST['enable'];
        Shynh_SEO_API_Keys::set_api_enabled( $enable );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=keys' ) );
        exit;
    }
}
