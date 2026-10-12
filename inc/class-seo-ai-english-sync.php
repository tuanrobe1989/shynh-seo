<?php
/**
 * AI English SEO Sync workflow for Polylang, Yoast, Shynh SEO, posts, and terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_AI_English_Sync {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'admin_init', [ $this, 'register_term_fields' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_shynh_seo_ai_sync_wrapup', [ $this, 'ajax_wrapup' ] );
        add_action( 'wp_ajax_shynh_seo_ai_sync_create_draft', [ $this, 'ajax_create_draft' ] );
        add_action( 'wp_ajax_shynh_seo_ai_suggest_field', [ $this, 'ajax_suggest_field' ] );
    }

    public function add_meta_boxes() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        foreach ( $this->get_supported_post_types() as $post_type ) {
            add_meta_box(
                'shynh_seo_ai_english_sync',
                __( 'AI English SEO Sync', 'shynh-seo' ),
                [ $this, 'render_post_box' ],
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function register_term_fields() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
            add_action( $taxonomy . '_edit_form_fields', [ $this, 'render_term_box' ], 99, 2 );
        }
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ], true ) ) {
            return;
        }

        wp_enqueue_script(
            'shynh-seo-ai-english-sync',
            SHYNH_SEO_URL . 'assets/js/ai-english-sync.js',
            [ 'jquery' ],
            SHYNH_SEO_VERSION,
            true
        );

        wp_localize_script( 'shynh-seo-ai-english-sync', 'shynhSeoAiSync', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'shynh_seo_ai_sync' ),
            'i18n'    => [
                'loading'            => __( 'Đang tổng hợp dữ liệu AI (Wrap-up)...', 'shynh-seo' ),
                'creating'           => __( 'Đang tạo bài nháp Tiếng Anh...', 'shynh-seo' ),
                'createDraft'        => __( 'Duyệt & Tạo bài nháp Tiếng Anh', 'shynh-seo' ),
                'openDraft'          => __( 'Mở bài nháp Tiếng Anh', 'shynh-seo' ),
                'close'              => __( 'Đóng', 'shynh-seo' ),
                'requiredTitle'      => __( 'Vui lòng nhập Tiêu đề và Nội dung Tiếng Anh trước khi tạo bản nháp.', 'shynh-seo' ),
                'generateWrapup'     => __( 'Tổng hợp dữ liệu AI (Wrap-up)', 'shynh-seo' ),
                'generateTermWrapup' => __( 'Tổng hợp dữ liệu AI Danh mục', 'shynh-seo' ),
                'reviewNotice'       => __( 'Vui lòng rà soát nội dung bên dưới. Nhấn "Duyệt & Tạo bài nháp Tiếng Anh" ở góc dưới để hoàn tất.', 'shynh-seo' ),
            ],
        ] );
    }

    public function get_default_language() {
        if ( function_exists( 'pll_default_language' ) ) {
            $default = pll_default_language();
            if ( ! empty( $default ) ) {
                return $default;
            }
        }
        return 'vi';
    }

    public function get_target_language() {
        if ( function_exists( 'pll_default_language' ) && function_exists( 'pll_languages_list' ) ) {
            $default_lang = $this->get_default_language();
            $languages    = pll_languages_list();
            if ( is_array( $languages ) ) {
                foreach ( $languages as $lang_slug ) {
                    if ( $lang_slug !== $default_lang ) {
                        return $lang_slug;
                    }
                }
            }
        }
        $settings = Shynh_SEO_Core::get_settings();
        return $settings['ai_sync_target_language'] ?? 'en';
    }

    private function get_language_slugs() {
        $languages = [];

        if ( function_exists( 'pll_languages_list' ) ) {
            $pll_languages = pll_languages_list( [ 'fields' => 'slug' ] );
            if ( is_array( $pll_languages ) ) {
                $languages = array_map( 'sanitize_key', $pll_languages );
            }
        }

        $settings    = Shynh_SEO_Core::get_settings();
        $languages[] = sanitize_key( $settings['ai_sync_target_language'] ?? 'en' );
        $languages[] = $this->get_default_language();

        return array_values( array_filter( array_unique( $languages ) ) );
    }

    private function sanitize_language_scoped_slug( $raw_slug, $fallback ) {
        $slug = sanitize_title( $raw_slug ?: $fallback );

        foreach ( $this->get_language_slugs() as $language ) {
            if ( $language && preg_match( '/-' . preg_quote( $language, '/' ) . '$/i', $slug ) ) {
                $slug = preg_replace( '/-' . preg_quote( $language, '/' ) . '$/i', '', $slug );
                break;
            }
        }

        return $slug ?: sanitize_title( $fallback );
    }

    public function is_source_default_language( $object_type, $object_id ) {
        if ( ! function_exists( 'pll_default_language' ) ) {
            return true;
        }

        $default_lang = $this->get_default_language();

        // Check if currently on a creation screen creating a translation (e.g., new_lang=en)
        $new_lang = isset( $_GET['new_lang'] ) ? sanitize_key( wp_unslash( $_GET['new_lang'] ) ) : '';
        if ( ! empty( $new_lang ) && $new_lang !== $default_lang ) {
            return false;
        }

        if ( 'term' === $object_type ) {
            $from_tag = isset( $_GET['from_tag'] ) ? absint( $_GET['from_tag'] ) : 0;
            if ( $from_tag > 0 && ! empty( $new_lang ) && $new_lang !== $default_lang ) {
                return false;
            }

            if ( $object_id > 0 && function_exists( 'pll_get_term_language' ) ) {
                $term_lang = pll_get_term_language( $object_id );
                if ( ! empty( $term_lang ) && $term_lang !== $default_lang ) {
                    return false;
                }
            }
        } else {
            $from_post = isset( $_GET['from_post'] ) ? absint( $_GET['from_post'] ) : 0;
            if ( $from_post > 0 && ! empty( $new_lang ) && $new_lang !== $default_lang ) {
                return false;
            }

            if ( $object_id > 0 && function_exists( 'pll_get_post_language' ) ) {
                $post_lang = pll_get_post_language( $object_id );
                if ( ! empty( $post_lang ) && $post_lang !== $default_lang ) {
                    return false;
                }
            }
        }

        return true;
    }

    public function render_post_box( $post ) {
        if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
            return;
        }

        if ( ! $this->is_source_default_language( 'post', $post->ID ) ) {
            return;
        }

        $settings     = Shynh_SEO_Core::get_settings();
        $default_lang = $this->get_default_language();
        $target_lang  = $this->get_target_language();
        $current_lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID ) : $default_lang;
        $english_id   = function_exists( 'pll_get_post' ) ? absint( pll_get_post( $post->ID, $target_lang ) ) : 0;
        $yoast_status = class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' );
        ?>
        <div class="shynh-seo-ai-sync-box" data-object-type="post" data-object-id="<?php echo esc_attr( $post->ID ); ?>">
            <?php $this->render_status_grid( [
                __( 'Source language', 'shynh-seo' ) => $current_lang ?: $default_lang,
                __( 'Target language', 'shynh-seo' ) => strtoupper( $target_lang ),
                'Polylang' => function_exists( 'pll_set_post_language' ) ? __( 'Detected', 'shynh-seo' ) : __( 'Not detected', 'shynh-seo' ),
                'Yoast SEO' => $yoast_status ? __( 'Detected', 'shynh-seo' ) : __( 'Not detected', 'shynh-seo' ),
                __( 'Translation version', 'shynh-seo' ) => $english_id ? sprintf( __( 'Exists #%d', 'shynh-seo' ), $english_id ) : __( 'Not created', 'shynh-seo' ),
            ] ); ?>
            <?php $this->render_toggles_note(); ?>
            <?php $this->render_model_controls(); ?>
            <button type="button" class="button button-primary shynh-seo-ai-sync-open">
                <?php esc_html_e( 'Generate Translation Wrap-up', 'shynh-seo' ); ?>
            </button>
        </div>
        <?php
    }

    public function render_term_box( $term, $taxonomy ) {
        if ( ! in_array( $taxonomy, $this->get_supported_taxonomies(), true ) ) {
            return;
        }

        $term_id = is_object( $term ) ? ( $term->term_id ?? 0 ) : 0;

        if ( ! $this->is_source_default_language( 'term', $term_id ) ) {
            return;
        }

        $settings     = Shynh_SEO_Core::get_settings();
        $default_lang = $this->get_default_language();
        $target_lang  = $this->get_target_language();
        $current_lang = ( $term_id && function_exists( 'pll_get_term_language' ) ) ? pll_get_term_language( $term_id ) : $default_lang;
        $english_id   = ( $term_id && function_exists( 'pll_get_term' ) ) ? absint( pll_get_term( $term_id, $target_lang ) ) : 0;
        ?>
        <tr class="form-field shynh-seo-ai-sync-term-row">
            <th scope="row"><?php esc_html_e( 'AI English SEO Sync', 'shynh-seo' ); ?></th>
            <td>
                <div class="shynh-seo-ai-sync-box" data-object-type="term" data-object-id="<?php echo esc_attr( $term_id ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
                    <?php $this->render_status_grid( [
                        __( 'Source language', 'shynh-seo' ) => $current_lang ?: $default_lang,
                        __( 'Target language', 'shynh-seo' ) => strtoupper( $target_lang ),
                        'Polylang' => function_exists( 'pll_set_term_language' ) ? __( 'Detected', 'shynh-seo' ) : __( 'Not detected', 'shynh-seo' ),
                        __( 'Translation term', 'shynh-seo' ) => $english_id ? sprintf( __( 'Exists #%d', 'shynh-seo' ), $english_id ) : __( 'Not created', 'shynh-seo' ),
                    ] ); ?>
                    <?php $this->render_toggles_note(); ?>
                    <?php $this->render_model_controls(); ?>
                    <button type="button" class="button button-primary shynh-seo-ai-sync-open">
                        <?php esc_html_e( 'Generate Translation Term Wrap-up', 'shynh-seo' ); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    private function render_status_grid( $items ) {
        echo '<dl class="shynh-seo-ai-sync-status">';
        foreach ( $items as $label => $value ) {
            echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
        }
        echo '</dl>';
    }

    private function render_toggles_note() {
        $settings = Shynh_SEO_Core::get_settings();
        ?>
        <div class="shynh-seo-ai-sync-toggles">
            <span><?php echo esc_html( ! empty( $settings['ai_sync_write_yoast'] ) ? __( 'Yoast: ON', 'shynh-seo' ) : __( 'Yoast: OFF', 'shynh-seo' ) ); ?></span>
            <span><?php echo esc_html( ! empty( $settings['ai_sync_translate_terms'] ) ? __( 'Terms: ON', 'shynh-seo' ) : __( 'Terms: OFF', 'shynh-seo' ) ); ?></span>
            <span><?php echo esc_html( ! empty( $settings['ai_sync_link_polylang'] ) ? __( 'Polylang: ON', 'shynh-seo' ) : __( 'Polylang: OFF', 'shynh-seo' ) ); ?></span>
        </div>
        <?php
    }

    private function render_model_controls() {
        $settings = Shynh_SEO_Core::get_settings();
        ?>
        <div class="shynh-seo-ai-sync-models">
            <label for="shynh_seo_ai_sync_model_mode"><?php esc_html_e( 'AI model', 'shynh-seo' ); ?></label>
            <select class="shynh-seo-ai-sync-model-mode" id="shynh_seo_ai_sync_model_mode">
                <option value="auto"><?php esc_html_e( 'Auto detect importance', 'shynh-seo' ); ?></option>
                <option value="default"><?php echo esc_html( sprintf( __( 'Default (%s)', 'shynh-seo' ), $settings['ai_sync_openai_model'] ?? 'gpt-5.6-luna' ) ); ?></option>
                <option value="important"><?php echo esc_html( sprintf( __( 'Important (%s)', 'shynh-seo' ), $settings['ai_sync_openai_model_important'] ?? 'gpt-5.6-sol' ) ); ?></option>
                <option value="economy"><?php echo esc_html( sprintf( __( 'Economy (%s)', 'shynh-seo' ), $settings['ai_sync_openai_model_economy'] ?? 'gpt-5.6-luna' ) ); ?></option>
                <option value="custom"><?php esc_html_e( 'Custom model', 'shynh-seo' ); ?></option>
            </select>
            <input type="text" class="shynh-seo-ai-sync-custom-model" value="" placeholder="gpt-5.6-luna" style="display:none;" />
            <p class="description"><?php esc_html_e( 'Auto se chon model theo do quan trong cua post/term. Ban van co the tu chon model rieng moi lan generate.', 'shynh-seo' ); ?></p>
        </div>
        <?php
    }

    public function ajax_wrapup() {
        $this->verify_ajax();

        $object_type = isset( $_POST['objectType'] ) ? sanitize_key( wp_unslash( $_POST['objectType'] ) ) : 'post';
        $object_id   = isset( $_POST['objectId'] ) ? absint( $_POST['objectId'] ) : 0;
        $taxonomy    = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $model_mode  = isset( $_POST['modelMode'] ) ? sanitize_key( wp_unslash( $_POST['modelMode'] ) ) : 'auto';
        $custom_model = isset( $_POST['customModel'] ) ? sanitize_text_field( wp_unslash( $_POST['customModel'] ) ) : '';

        $source = 'term' === $object_type ? $this->collect_term_source( $object_id, $taxonomy ) : $this->collect_post_source( $object_id );
        if ( is_wp_error( $source ) ) {
            wp_send_json_error( [ 'message' => $source->get_error_message() ] );
        }

        $settings = Shynh_SEO_Core::get_settings();
        $model_selection = $this->resolve_model_selection( $source, $settings, $model_mode, $custom_model );
        $ai       = $this->generate_ai_payload( $source, $settings, $model_selection );
        $ready    = ! empty( $ai['title'] ) && ! empty( $ai['content'] );

        wp_send_json_success( [
            'source'        => $source,
            'generated'     => $ai,
            'model'         => $model_selection,
            'readyToCreate' => $ready,
            'yoastEnabled'  => ! empty( $settings['ai_sync_write_yoast'] ),
            'termEnabled'   => ! empty( $settings['ai_sync_translate_terms'] ),
            'message'       => $ready
                ? __( 'AI da tao ban wrap-up. Hay duyet ky truoc khi tao draft.', 'shynh-seo' )
                : __( 'Chua co API key hoac AI chua tra ket qua hop le. Ban co the dien thu cong trong popup.', 'shynh-seo' ),
        ] );
    }

    public function ajax_create_draft() {
        $this->verify_ajax();

        $object_type = isset( $_POST['objectType'] ) ? sanitize_key( wp_unslash( $_POST['objectType'] ) ) : 'post';
        $object_id   = isset( $_POST['objectId'] ) ? absint( $_POST['objectId'] ) : 0;
        $taxonomy    = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
        $payload     = json_decode( $payload_raw, true );

        if ( ! is_array( $payload ) ) {
            wp_send_json_error( [ 'message' => __( 'Payload khong hop le.', 'shynh-seo' ) ] );
        }

        $result = 'term' === $object_type
            ? $this->create_or_update_term_translation( $object_id, $taxonomy, $payload )
            : $this->create_or_update_post_translation( $object_id, $payload );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    public function ajax_suggest_field() {
        $field_name    = isset( $_POST['fieldName'] ) ? sanitize_key( wp_unslash( $_POST['fieldName'] ) ) : '';
        $object_type   = isset( $_POST['objectType'] ) ? sanitize_key( wp_unslash( $_POST['objectType'] ) ) : 'post';
        $object_id     = isset( $_POST['objectId'] ) ? absint( $_POST['objectId'] ) : 0;
        $taxonomy      = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $parent_id     = isset( $_POST['parentId'] ) ? absint( $_POST['parentId'] ) : 0;
        $from_tag      = isset( $_POST['fromTag'] ) ? absint( $_POST['fromTag'] ) : 0;
        $lang          = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
        $current_kw    = isset( $_POST['currentKeyword'] ) ? sanitize_text_field( wp_unslash( $_POST['currentKeyword'] ) ) : '';
        $current_title = isset( $_POST['currentTitle'] ) ? sanitize_text_field( wp_unslash( $_POST['currentTitle'] ) ) : '';
        $current_desc  = isset( $_POST['currentDesc'] ) ? sanitize_text_field( wp_unslash( $_POST['currentDesc'] ) ) : '';
        $current_val   = isset( $_POST['currentValue'] ) ? sanitize_text_field( wp_unslash( $_POST['currentValue'] ) ) : '';

        $source = 'term' === $object_type ? $this->collect_term_source( $object_id, $taxonomy, $from_tag ) : $this->collect_post_source( $object_id );
        if ( is_wp_error( $source ) ) {
            wp_send_json_error( [ 'message' => $source->get_error_message() ] );
        }

        if ( $parent_id > 0 ) {
            $parent_term = get_term( $parent_id );
            if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                $source['parent_name'] = $parent_term->name;
                $source['parent_desc'] = $parent_term->description;
            }
        }

        $existing_keywords = class_exists( 'Shynh_SEO_Cannibalization' ) ? Shynh_SEO_Cannibalization::get_existing_keywords( $object_id, $object_type ) : [];
        $existing_titles   = class_exists( 'Shynh_SEO_Cannibalization' ) ? Shynh_SEO_Cannibalization::get_existing_titles( $object_id, $object_type ) : [];

        $settings = Shynh_SEO_Core::get_settings();
        $api_key  = trim( $settings['ai_sync_openai_api_key'] ?? '' );
        $model    = $settings['ai_sync_openai_model_economy'] ?? 'gpt-5.6-luna';

        if ( ! empty( $api_key ) ) {
            $suggested = $this->query_openai_single_field( $field_name, $source, $current_kw, $current_title, $current_desc, $current_val, $existing_keywords, $existing_titles, $api_key, $model, $lang );
            if ( $suggested ) {
                if ( is_array( $suggested ) ) {
                    wp_send_json_success( array_merge( [ 'fieldName' => $field_name ], $suggested ) );
                } else {
                    wp_send_json_success( [ 'value' => $suggested, 'fieldName' => $field_name ] );
                }
            }
        }

        $fallback = $this->generate_local_fallback( $field_name, $source, $current_kw );
        wp_send_json_success( [ 'value' => $fallback, 'fieldName' => $field_name, 'fallback' => true ] );
    }

    private function query_openai_single_field( $field_name, $source, $current_kw, $current_title, $current_desc, $current_val, $existing_keywords, $existing_titles, $api_key, $model, $lang = '' ) {
        $target_lang = ( 'en' === strtolower( $lang ) || ( isset( $source['language'] ) && 'en' === strtolower( $source['language'] ) ) ) ? 'English' : 'Vietnamese';

        $term_name_prompt = 'You are a Senior Brand & Medical Aesthetics Specialist for Shynh Super Luxury.';
        if ( ! empty( $source['fromTerm']['name'] ) ) {
            $term_name_prompt .= ' Translate and adapt the original category name: "' . $source['fromTerm']['name'] . '" into ' . $target_lang . ' for Shynh Super Luxury (High-End Medical Aesthetics).';
        } else {
            $term_name_prompt .= ' Generate ONE concise, elegant Category / Service Name (2-4 words) in ' . $target_lang . ' for this aesthetic taxonomy.' . ( ! empty( $source['parent_name'] ) ? ' It is a sub-category under parent category: "' . $source['parent_name'] . '", so expand sub-keywords logically.' : '' );
        }
        $term_name_prompt .= ' MUST NOT match existing site categories: ' . implode( ', ', array_slice( $existing_titles, 0, 40 ) ) . '. Return JSON {"value": "Category Name", "slug": "category-slug"}';

        $instructions = [
            'term_name' => $term_name_prompt,
            'term_slug' => 'Generate a clean, SEO-friendly ASCII hyphenated slug in lowercase for this category name: "' . ( $current_val ?: ( $source['name'] ?? '' ) ) . '". MUST NOT match existing slugs in the same language and MUST NOT append language suffixes like -vi or -en. Return JSON {"value": "category-slug"}',
            'keyword'   => 'You are a Senior Google SEO Specialist for Shynh Super Luxury (High-End Medical Aesthetics). Extract or generate ONE high-intent ' . $target_lang . ' Focus Keyword (2-4 words) that potential customers search for on Google. It MUST reflect the page topic, URL slug, and service benefits. MUST NOT match existing site keywords: ' . implode( ', ', array_slice( $existing_keywords, 0, 40 ) ) . '. Return JSON {"value": "keyword"}',
            'title'     => 'You are a Senior SEO Title Strategist. Generate a Google-optimized ' . $target_lang . ' SEO Title (strictly 50-60 characters) for Shynh Super Luxury.' . ( $current_kw ? ' MUST contain focus keyword: "' . $current_kw . '" near the beginning.' : ' MUST include a high-intent focus keyword near the beginning.' ) . ' Format: "[Focus Keyword / Benefit] | Shynh Super Luxury". MUST NOT match existing titles: ' . implode( ', ', array_slice( $existing_titles, 0, 40 ) ) . '. Return JSON {"value": "title"}',
            'meta_desc' => 'You are a Senior Copywriter & SEO Specialist. Generate a Google-optimized ' . $target_lang . ' Meta Description (strictly 140-160 characters) for Shynh Super Luxury.' . ( $current_kw ? ' MUST naturally include focus keyword: "' . $current_kw . '".' : '' ) . ' Must highlight premium aesthetic benefits and end with a strong Call-To-Action. Return JSON {"value": "description"}',
            'term_description' => 'You are a Senior Copywriter for Shynh Super Luxury (High-End Medical Aesthetics). Write a rich, compelling, and informative 2-3 paragraph ' . $target_lang . ' Term Description (about 150-250 words) for this category/service topic.' . ( ! empty( $source['fromTerm']['desc'] ) ? ' Translate & adapt from source description: "' . wp_strip_all_tags( $source['fromTerm']['desc'] ) . '".' : '' ) . ( ! empty( $source['parent_name'] ) ? ' Parent Category Context: "' . $source['parent_name'] . '".' : '' ) . ( $current_kw ? ' MUST naturally incorporate focus keyword: "' . $current_kw . '".' : '' ) . ' Highlight luxury service standards, treatment efficacy, and brand excellence. Return JSON {"value": "description"}',
            'description'      => 'You are a Senior Copywriter for Shynh Super Luxury (High-End Medical Aesthetics). Write a rich, compelling, and informative 2-3 paragraph ' . $target_lang . ' Term Description (about 150-250 words) for this category/service topic.' . ( ! empty( $source['fromTerm']['desc'] ) ? ' Translate & adapt from source description: "' . wp_strip_all_tags( $source['fromTerm']['desc'] ) . '".' : '' ) . ( ! empty( $source['parent_name'] ) ? ' Parent Category Context: "' . $source['parent_name'] . '".' : '' ) . ( $current_kw ? ' MUST naturally incorporate focus keyword: "' . $current_kw . '".' : '' ) . ' Highlight luxury service standards, treatment efficacy, and brand excellence. Return JSON {"value": "description"}',
            'quick_answer' => 'You are an AEO / AI Search Expert (Perplexity, ChatGPT, Google SGE). Write a concise 2-3 sentence Answer-First Quick Answer paragraph in ' . $target_lang . ' summarizing the medical aesthetic solution and benefits.' . ( $current_kw ? ' Target keyword: "' . $current_kw . '".' : '' ) . ' Return JSON {"value": "quick_answer"}',
            'fb_title' => 'Generate an engaging ' . $target_lang . ' Facebook Title for social sharing for Shynh Super Luxury.' . ( $current_kw ? ' Target keyword: "' . $current_kw . '".' : '' ) . ' Return JSON {"value": "fb_title"}',
            'fb_desc' => 'Generate an engaging 1-2 sentence ' . $target_lang . ' Facebook Description for social sharing for Shynh Super Luxury.' . ( $current_kw ? ' Target keyword: "' . $current_kw . '".' : '' ) . ' Return JSON {"value": "fb_desc"}',
        ];

        $system_prompt = $instructions[ $field_name ] ?? $instructions['title'];

        $page_title = ! empty( $source['title'] ) ? $source['title'] : ( $source['name'] ?? '' );
        $page_desc  = ! empty( $source['plainText'] ) ? $source['plainText'] : ( $source['description'] ?? ( $source['excerpt'] ?? '' ) );
        $page_url   = $source['url'] ?? '';

        $user_content  = "=== SYSTEM BRAND & SITE CONTEXT ===\n";
        $user_content .= "Brand: Shynh Super Luxury (Viện Thẩm Mỹ / Clinic Hạng Sang)\n";
        $user_content .= "Target Language: " . $target_lang . "\n";
        $user_content .= "Page Type: " . ( 'term' === $source['objectType'] ? 'Category / Service Taxonomy Term (' . ( $source['taxonomy'] ?? '' ) . ')' : 'Post / Page / Service Detail' ) . "\n";
        if ( ! empty( $source['fromTerm']['name'] ) ) {
            $user_content .= "Source Translation Category Name: " . $source['fromTerm']['name'] . "\n";
            $user_content .= "Source Translation Category Description: " . wp_strip_all_tags( $source['fromTerm']['desc'] ) . "\n";
        }
        $user_content .= "Page Title: " . $page_title . "\n";
        if ( ! empty( $source['parent_name'] ) ) {
            $user_content .= "Parent Category Name: " . $source['parent_name'] . "\n";
        }
        if ( ! empty( $page_url ) ) {
            $user_content .= "Page URL / Permalink: " . $page_url . "\n";
        }
        if ( ! empty( $page_desc ) ) {
            $user_content .= "Page Description / Content: " . mb_substr( wp_strip_all_tags( $page_desc ), 0, 1500, 'UTF-8' ) . "\n";
        }
        if ( ! empty( $current_kw ) ) {
            $user_content .= "Current Focus Keyword: " . $current_kw . "\n";
        }
        if ( ! empty( $current_title ) ) {
            $user_content .= "Current Form SEO Title: " . $current_title . "\n";
        }
        if ( ! empty( $current_desc ) ) {
            $user_content .= "Current Form Meta Description: " . $current_desc . "\n";
        }

        $body = [
            'model' => $model,
            'messages' => [
                [ 'role' => 'system', 'content' => $system_prompt ],
                [ 'role' => 'user', 'content' => $user_content ],
            ],
            'response_format' => [ 'type' => 'json_object' ],
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 35,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $text    = $this->extract_response_text( $decoded );
        $json    = json_decode( $text, true );

        if ( is_array( $json ) ) {
            if ( isset( $json['value'] ) ) {
                return $json;
            }
            return reset( $json );
        }

        return false;
    }

    private function generate_local_fallback( $field_name, $source, $current_kw ) {
        $title   = ! empty( $source['title'] ) ? $source['title'] : ( $source['name'] ?? '' );
        $keyword = $current_kw ?: ( $source['shynhSeo']['focus_keyword'] ?? $title );
        $desc    = ! empty( $source['plainText'] ) ? $source['plainText'] : ( $source['description'] ?? $title );

        switch ( $field_name ) {
            case 'term_name':
                if ( ! empty( $source['fromTerm']['name'] ) ) {
                    return $source['fromTerm']['name'] . ' (EN)';
                }
                return ! empty( $source['parent_name'] ) ? sprintf( '%s Cao Cấp', $source['parent_name'] ) : 'Dịch Vụ Thẩm Mỹ';
            case 'term_slug':
                return sanitize_title( $title ?: 'dich-vu-cao-cap' );
            case 'keyword':
                return wp_trim_words( $title, 3, '' );
            case 'title':
                return $keyword ? sprintf( '%s | Shynh Super Luxury', $keyword ) : sprintf( '%s | Shynh Super Luxury', $title );
            case 'meta_desc':
                return wp_trim_words( $desc, 25, '... Trải nghiệm làm đẹp đẳng cấp tại Shynh Super Luxury.' );
            case 'term_description':
            case 'description':
                return sprintf( 'Chuyên mục %s tại Viện Thẩm Mỹ Shynh Super Luxury mang đến các giải pháp làm đẹp công nghệ cao chuẩn y khoa, kết hợp công nghệ hiện đại và dịch vụ chăm sóc 5 sao đẳng cấp.', $title );
            case 'quick_answer':
                return wp_trim_words( $desc, 40, '.' );
            case 'fb_title':
                return $title . ' | Shynh Super Luxury';
            case 'fb_desc':
                return wp_trim_words( $desc, 25, '...' );
            default:
                return $title;
        }
    }

    private function collect_post_source( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'invalid_post', __( 'Không tìm thấy bài viết hoặc bạn không có quyền.', 'shynh-seo' ) );
        }

        $settings = Shynh_SEO_Core::get_settings();
        $context  = class_exists( 'Shynh_SEO_Content_Extractor' )
            ? Shynh_SEO_Content_Extractor::extract( $post_id, $post->post_content )
            : [ 'plain_text' => wp_strip_all_tags( $post->post_content ), 'headings' => [], 'images' => [] ];

        return [
            'objectType'  => 'post',
            'id'          => $post_id,
            'postType'    => $post->post_type,
            'language'    => function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id ) : '',
            'title'       => get_the_title( $post_id ),
            'slug'        => $post->post_name,
            'url'         => get_permalink( $post_id ),
            'excerpt'     => has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '',
            'description' => has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( $context['plain_text'] ?? '', 50, '...' ),
            'content'     => $post->post_content,
            'plainText'   => $context['plain_text'] ?? '',
            'headings'    => array_slice( $context['headings'] ?? [], 0, 40 ),
            'images'      => array_slice( $this->enrich_images( $context['images'] ?? [] ), 0, 30 ),
            'mediaBlocks' => array_slice( $this->extract_media_blocks( $post->post_content ), 0, 30 ),
            'featuredImage' => $this->get_featured_image_payload( $post_id ),
            'imagePolicy' => $this->get_image_policy( $settings ),
            'siteMediaCandidates' => $this->get_site_media_candidates_for_post( $post_id, $post ),
            'shynhSeo'    => $this->get_post_seo_meta( $post_id ),
            'yoastSeo'    => ! empty( $settings['ai_sync_write_yoast'] ) ? $this->get_post_yoast_meta( $post_id ) : null,
            'terms'       => $this->collect_post_terms( $post_id, $post->post_type ),
        ];
    }

    private function collect_term_source( $term_id, $taxonomy = '', $from_tag = 0 ) {
        $can_edit = current_user_can( 'manage_categories' ) || current_user_can( 'edit_terms' ) || current_user_can( 'edit_posts' );
        if ( ! $can_edit ) {
            return new WP_Error( 'invalid_permission', __( 'Bạn không có quyền thực hiện thao tác này.', 'shynh-seo' ) );
        }

        $term = null;
        if ( empty( $taxonomy ) && $term_id ) {
            $term_obj = get_term( $term_id );
            if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                $term     = $term_obj;
                $taxonomy = $term->taxonomy;
            }
        } elseif ( $term_id ) {
            $term = get_term( $term_id, $taxonomy );
            if ( ( ! $term || is_wp_error( $term ) ) && $term_id ) {
                $term_obj = get_term( $term_id );
                if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                    $term     = $term_obj;
                    $taxonomy = $term->taxonomy;
                }
            }
        }

        if ( ! $term ) {
            $from_term = null;
            if ( $from_tag > 0 ) {
                $from_term_obj = get_term( $from_tag );
                if ( $from_term_obj && ! is_wp_error( $from_term_obj ) ) {
                    $from_term = $from_term_obj;
                }
            }

            return [
                'objectType'  => 'term',
                'id'          => 0,
                'taxonomy'    => $taxonomy,
                'language'    => isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '',
                'title'       => $from_term ? $from_term->name : '',
                'name'        => $from_term ? $from_term->name : '',
                'slug'        => $from_term ? $from_term->slug : '',
                'url'         => '',
                'description' => $from_term ? $from_term->description : '',
                'plainText'   => $from_term ? wp_strip_all_tags( $from_term->description ) : '',
                'shynhSeo'    => $from_term ? $this->get_term_seo_meta( $from_term->term_id ) : [],
                'isNew'       => true,
                'fromTerm'    => $from_term ? [ 'id' => $from_term->term_id, 'name' => $from_term->name, 'desc' => $from_term->description ] : null,
            ];
        }

        $settings  = Shynh_SEO_Core::get_settings();
        $term_link = get_term_link( $term );
        $url       = ( $term_link && ! is_wp_error( $term_link ) ) ? $term_link : '';

        return [
            'objectType'  => 'term',
            'id'          => $term_id,
            'taxonomy'    => $taxonomy,
            'language'    => function_exists( 'pll_get_term_language' ) ? pll_get_term_language( $term_id ) : '',
            'title'       => $term->name,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'url'         => $url,
            'description' => $term->description,
            'plainText'   => wp_strip_all_tags( $term->description ),
            'siteMediaCandidates' => $this->get_site_media_candidates( [ $term->name, $term->slug ] ),
            'shynhSeo'    => $this->get_term_seo_meta( $term_id ),
            'yoastSeo'    => ! empty( $settings['ai_sync_write_yoast'] ) ? $this->get_term_yoast_meta( $term_id ) : null,
        ];
    }

    private function generate_ai_payload( $source, $settings, $model_selection ) {
        if ( empty( $settings['ai_sync_openai_api_key'] ) ) {
            return $this->empty_ai_payload();
        }

        $manifest_path = get_template_directory() . '/docs/blocks-manifest.json';
        $manifest_data = file_exists( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : [];
        if ( ! class_exists( 'Shynh_SEO_Block_Validator' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-seo-block-validator.php';
        }
        if ( ! class_exists( 'Shynh_SEO_Block_Composer' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-seo-block-composer.php';
        }
        $validator = new Shynh_SEO_Block_Validator( $manifest_data );
        $composer = new Shynh_SEO_Block_Composer( $manifest_data, $validator );
        $context = ( $source['objectType'] === 'post' ) ? 'article' : 'service';

        $prompt = $this->build_prompt( $source, $settings, $model_selection );
        $body   = [
            'model' => $model_selection['model'],
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are a senior American English SEO, GEO, and AEO localization editor for a medical-aesthetic luxury brand. Return valid JSON only.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if ( $source['objectType'] === 'post' ) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'shynh_seo_english_sync',
                    'strict' => false,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [ 'type' => 'string' ],
                            'slug' => [ 'type' => 'string' ],
                            'h1' => [ 'type' => 'string' ],
                            'excerpt' => [ 'type' => 'string' ],
                            'focus_keyword' => [ 'type' => 'string' ],
                            'seo_title' => [ 'type' => 'string' ],
                            'meta_description' => [ 'type' => 'string' ],
                            'quick_answer' => [ 'type' => 'string' ],
                            'blocks' => $composer->build_schema_for_context( $context )['properties']['blocks'],
                            'faqs' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'question' => [ 'type' => 'string' ], 'answer' => [ 'type' => 'string' ] ] ] ],
                            'image_suggestions' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'source_id' => [ 'type' => 'integer' ], 'source' => [ 'type' => 'string' ], 'alt' => [ 'type' => 'string' ], 'caption' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ], 'reason' => [ 'type' => 'string' ] ] ] ],
                            'additional_image_suggestions' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'section' => [ 'type' => 'string' ], 'reason' => [ 'type' => 'string' ], 'preferred_source' => [ 'type' => 'string' ] ] ] ],
                            'reviewer_notes' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ]
                        ],
                        'required' => ['title', 'slug', 'h1', 'excerpt', 'focus_keyword', 'seo_title', 'meta_description', 'quick_answer', 'blocks', 'faqs', 'image_suggestions', 'additional_image_suggestions', 'reviewer_notes'],
                        'additionalProperties' => false
                    ]
                ]
            ];
        } else {
            $body['response_format'] = [ 'type' => 'json_object' ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . trim( $settings['ai_sync_openai_api_key'] ),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
        ] );

        if ( is_wp_error( $response ) ) {
            return array_merge( $this->empty_ai_payload(), [ 'error' => $response->get_error_message() ] );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $text    = $this->extract_response_text( $decoded );
        $payload = json_decode( $text, true );

        if ( ! is_array( $payload ) ) {
            return array_merge( $this->empty_ai_payload(), [ 'error' => __( 'AI response khong phai JSON hop le.', 'shynh-seo' ) ] );
        }

        if ( $source['objectType'] === 'post' && ! empty( $payload['blocks'] ) && is_array( $payload['blocks'] ) ) {
            $compose_result = $composer->compose_markup( $payload, $context );
            $payload['content'] = $compose_result['markup'];
            $payload['warnings'] = $compose_result['warnings'];
            $payload['dropped'] = $compose_result['dropped'];
            unset( $payload['blocks'] ); // Remove JSON blocks structure, replace with serialized HTML for JS
        }

        return wp_parse_args( $payload, $this->empty_ai_payload() );
    }

    private function resolve_model_selection( $source, $settings, $model_mode, $custom_model ) {
        $allowed_modes = [ 'auto', 'default', 'important', 'economy', 'custom' ];
        if ( ! in_array( $model_mode, $allowed_modes, true ) ) {
            $model_mode = 'auto';
        }

        $models = [
            'default'   => $settings['ai_sync_openai_model'] ?? 'gpt-5.6-luna',
            'economy'   => $settings['ai_sync_openai_model_economy'] ?? 'gpt-5.6-luna',
            'important' => $settings['ai_sync_openai_model_important'] ?? 'gpt-5.6-sol',
        ];

        $importance = $this->detect_source_importance( $source );
        $resolved_mode = $model_mode;

        if ( 'custom' === $model_mode && '' !== trim( $custom_model ) ) {
            $model = trim( $custom_model );
        } elseif ( 'auto' === $model_mode ) {
            $resolved_mode = $importance['level'];
            $model = $models[ $resolved_mode ] ?? $models['default'];
        } else {
            $model = $models[ $model_mode ] ?? $models['default'];
        }

        if ( '' === trim( (string) $model ) ) {
            $model = 'gpt-5.4-mini';
        }

        return [
            'mode'          => $model_mode,
            'resolvedMode'  => $resolved_mode,
            'model'         => sanitize_text_field( $model ),
            'importance'    => $importance,
        ];
    }

    private function detect_source_importance( $source ) {
        $score = 0;
        $reasons = [];

        if ( 'post' === ( $source['objectType'] ?? '' ) ) {
            $post_type = $source['postType'] ?? '';
            if ( in_array( $post_type, [ 'page', 'dich-vu' ], true ) ) {
                $score += 25;
                $reasons[] = 'service_or_page';
            }

            if ( ! empty( $source['shynhSeo']['schema_type'] ) && in_array( $source['shynhSeo']['schema_type'], [ 'MedicalWebPage', 'Service' ], true ) ) {
                $score += 25;
                $reasons[] = 'medical_or_service_schema';
            }

            $word_count = str_word_count( wp_strip_all_tags( $source['plainText'] ?? '' ) );
            if ( $word_count >= 1200 ) {
                $score += 20;
                $reasons[] = 'long_form_content';
            } elseif ( $word_count < 250 ) {
                $score -= 15;
                $reasons[] = 'short_content';
            }

            if ( count( (array) ( $source['images'] ?? [] ) ) >= 4 ) {
                $score += 10;
                $reasons[] = 'image_heavy';
            }

            if ( count( (array) ( $source['terms'] ?? [] ) ) >= 3 ) {
                $score += 10;
                $reasons[] = 'multiple_terms';
            }
        } else {
            $score += 5;
            $reasons[] = 'term_translation';

            if ( strlen( wp_strip_all_tags( $source['description'] ?? '' ) ) > 700 ) {
                $score += 15;
                $reasons[] = 'rich_term_description';
            }
        }

        $combined = strtolower( wp_strip_all_tags( ( $source['title'] ?? $source['name'] ?? '' ) . ' ' . ( $source['plainText'] ?? $source['description'] ?? '' ) ) );
        if ( preg_match( '/\b(laser|filler|botox|injection|treatment|skin|medical|surgery|risk|recovery|aftercare|doctor|consultation)\b/i', $combined ) ) {
            $score += 20;
            $reasons[] = 'medical_aesthetic_terms';
        }

        if ( $score >= 45 ) {
            $level = 'important';
        } elseif ( $score <= 5 ) {
            $level = 'economy';
        } else {
            $level = 'default';
        }

        return [
            'level'   => $level,
            'score'   => $score,
            'reasons' => array_values( array_unique( $reasons ) ),
        ];
    }

    private function build_prompt( $source, $settings, $model_selection = [] ) {
        $output_rules = [
            'Write natural American English. Do not translate word by word.',
            'Preserve brand meaning, medical-aesthetic accuracy, safety, risk, consultation, and aftercare language.',
            'Create an answer-first intro suitable for AEO, but keep heading structure clean.',
            $source['objectType'] === 'post' ? 'Generate the translated content as an array of Gutenberg block objects ("blocks" key) matching the strictly provided schema.' : 'Keep content as clean HTML fragments.',
            'Do not remove, replace, or rewrite original images. The plugin preserves source image/media blocks; write localized text around them.',
            'Generate clean slugs inside the target language namespace. Do not append language suffixes like -vi or -en to slugs.',
            'Return image_suggestions for existing images only unless additional images are explicitly allowed by imagePolicy.',
            'Return JSON keys: title, slug, h1, excerpt, ' . ($source['objectType'] === 'post' ? 'blocks' : 'content') . ', focus_keyword, seo_title, meta_description, quick_answer, faqs, image_suggestions, additional_image_suggestions, terms, reviewer_notes.',
            'faqs must be an array of {question, answer}. image_suggestions must be {source_id, source, alt, caption, title, reason}.',
        ];

        if ( empty( $settings['ai_sync_write_yoast'] ) ) {
            $output_rules[] = 'Yoast SEO output is disabled. Leave focus_keyword, seo_title, and meta_description empty.';
        }

        return wp_json_encode( [
            'task'          => 'Localize Vietnamese source into English SEO-ready draft for Shynh Super Luxury.',
            'targetLocale'  => $settings['ai_sync_target_locale'] ?? 'en_US',
            'modelSelection'=> $model_selection,
            'outputRules'   => $output_rules,
            'source'        => $source,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }

    private function create_or_update_post_translation( $source_post_id, $payload ) {
        $source = get_post( $source_post_id );
        if ( ! $source || ! current_user_can( 'edit_post', $source_post_id ) ) {
            return new WP_Error( 'invalid_post', __( 'Khong tim thay bai viet nguon.', 'shynh-seo' ) );
        }

        $settings = Shynh_SEO_Core::get_settings();
        $title    = sanitize_text_field( $payload['title'] ?? '' );
        $content  = wp_kses_post( $payload['content'] ?? '' );
        $content  = $this->maybe_preserve_source_media_blocks( $source->post_content, $content, $settings );

        if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) ) {
            return new WP_Error( 'missing_content', __( 'Can co English title va content truoc khi tao draft.', 'shynh-seo' ) );
        }

        $target_lang = sanitize_key( $settings['ai_sync_target_language'] ?? 'en' );
        $target_id   = function_exists( 'pll_get_post' ) ? absint( pll_get_post( $source_post_id, $target_lang ) ) : 0;
        $post_name   = $this->sanitize_language_scoped_slug( $payload['slug'] ?? '', $title );
        $postarr     = [
            'post_title'   => $title,
            'post_name'    => $post_name,
            'post_excerpt' => sanitize_textarea_field( $payload['excerpt'] ?? '' ),
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => $source->post_type,
            'post_author'  => get_current_user_id() ?: $source->post_author,
        ];

        if ( $target_id ) {
            $postarr['ID'] = $target_id;
            $target_id = wp_update_post( $postarr, true );
        } else {
            $target_id = wp_insert_post( $postarr, true );
        }

        if ( is_wp_error( $target_id ) ) {
            return $target_id;
        }

        $this->maybe_copy_featured_image( $source_post_id, $target_id, $settings );
        $this->sync_post_terms( $source_post_id, $target_id, $payload, $settings );
        $this->write_post_meta_outputs( $target_id, $payload, $settings );
        $this->maybe_apply_image_metadata( $payload, $settings );
        $this->link_polylang_posts( $source_post_id, $target_id, $target_lang );

        return [
            'id'      => $target_id,
            'message' => __( 'English draft da duoc tao/cap nhat.', 'shynh-seo' ),
            'editUrl' => get_edit_post_link( $target_id, 'raw' ),
        ];
    }

    private function create_or_update_term_translation( $source_term_id, $taxonomy, $payload ) {
        $term = get_term( $source_term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) || ! current_user_can( 'manage_categories' ) ) {
            return new WP_Error( 'invalid_term', __( 'Khong tim thay term nguon.', 'shynh-seo' ) );
        }

        $settings    = Shynh_SEO_Core::get_settings();
        $target_lang = sanitize_key( $settings['ai_sync_target_language'] ?? 'en' );
        $name        = sanitize_text_field( $payload['title'] ?? $payload['name'] ?? '' );
        $description = wp_kses_post( $payload['content'] ?? $payload['description'] ?? '' );

        if ( '' === $name ) {
            return new WP_Error( 'missing_term_name', __( 'Can co English term name truoc khi tao term.', 'shynh-seo' ) );
        }

        $target_id = function_exists( 'pll_get_term' ) ? absint( pll_get_term( $source_term_id, $target_lang ) ) : 0;
        $slug      = $this->sanitize_language_scoped_slug( $payload['slug'] ?? '', $name );
        $args      = [
            'slug'        => $slug,
            'description' => $description,
        ];

        if ( $target_id ) {
            $result = wp_update_term( $target_id, $taxonomy, array_merge( $args, [ 'name' => $name ] ) );
        } else {
            $result = wp_insert_term( $name, $taxonomy, $args );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $target_id = absint( is_array( $result ) ? $result['term_id'] : $target_id );
        $this->write_term_meta_outputs( $target_id, $payload, $settings );
        $this->link_polylang_terms( $source_term_id, $target_id, $target_lang );

        return [
            'id'      => $target_id,
            'message' => __( 'English term da duoc tao/cap nhat.', 'shynh-seo' ),
            'editUrl' => get_edit_term_link( $target_id, $taxonomy ),
        ];
    }

    private function sync_post_terms( $source_post_id, $target_post_id, $payload, $settings ) {
        if ( empty( $settings['ai_sync_translate_terms'] ) ) {
            return;
        }

        $term_payloads = [];
        foreach ( (array) ( $payload['terms'] ?? [] ) as $term_payload ) {
            if ( ! empty( $term_payload['source_term_id'] ) ) {
                $term_payloads[ absint( $term_payload['source_term_id'] ) ] = $term_payload;
            }
        }

        foreach ( get_object_taxonomies( get_post_type( $source_post_id ), 'names' ) as $taxonomy ) {
            if ( ! in_array( $taxonomy, $this->get_supported_taxonomies(), true ) ) {
                continue;
            }

            $source_terms = wp_get_post_terms( $source_post_id, $taxonomy );
            if ( empty( $source_terms ) || is_wp_error( $source_terms ) ) {
                continue;
            }

            $target_term_ids = [];
            foreach ( $source_terms as $source_term ) {
                $target_term_id = function_exists( 'pll_get_term' ) ? absint( pll_get_term( $source_term->term_id, $settings['ai_sync_target_language'] ?? 'en' ) ) : 0;
                if ( ! $target_term_id && isset( $term_payloads[ $source_term->term_id ] ) ) {
                    $created = $this->create_or_update_term_translation( $source_term->term_id, $taxonomy, $term_payloads[ $source_term->term_id ] );
                    if ( ! is_wp_error( $created ) ) {
                        $target_term_id = absint( $created['id'] );
                    }
                }
                if ( $target_term_id ) {
                    $target_term_ids[] = $target_term_id;
                }
            }

            if ( $target_term_ids ) {
                wp_set_object_terms( $target_post_id, $target_term_ids, $taxonomy );
            }
        }
    }

    private function maybe_preserve_source_media_blocks( $source_content, $target_content, $settings ) {
        if ( empty( $settings['ai_sync_preserve_images'] ) ) {
            return $target_content;
        }

        $media_blocks = $this->extract_media_blocks( $source_content );
        if ( empty( $media_blocks ) ) {
            return $target_content;
        }

        $target_blocks = parse_blocks( $target_content );
        $has_blocks    = ! empty( $target_blocks );

        foreach ( $media_blocks as $media_block ) {
            if ( $this->content_has_media_reference( $target_content, $media_block ) ) {
                continue;
            }

            $serialized = $media_block['html'];
            if ( '' === trim( $serialized ) ) {
                continue;
            }

            if ( $has_blocks ) {
                $insert_at = min( count( $target_blocks ), max( 0, (int) round( $media_block['ratio'] * count( $target_blocks ) ) ) );
                array_splice( $target_blocks, $insert_at, 0, [ $media_block['block'] ] );
                $target_content = serialize_blocks( $target_blocks );
                $has_blocks = true;
            } else {
                $target_content .= "\n\n" . $serialized;
            }
        }

        return $target_content;
    }

    private function maybe_copy_featured_image( $source_post_id, $target_post_id, $settings ) {
        if ( empty( $settings['ai_sync_copy_featured_image'] ) || has_post_thumbnail( $target_post_id ) ) {
            return;
        }

        $thumbnail_id = get_post_thumbnail_id( $source_post_id );
        if ( ! $thumbnail_id ) {
            return;
        }

        $target_lang = sanitize_key( $settings['ai_sync_target_language'] ?? 'en' );
        if ( function_exists( 'pll_get_post' ) ) {
            $translated_thumbnail_id = absint( pll_get_post( $thumbnail_id, $target_lang ) );
            if ( $translated_thumbnail_id ) {
                $thumbnail_id = $translated_thumbnail_id;
            }
        }

        set_post_thumbnail( $target_post_id, $thumbnail_id );
    }

    private function maybe_apply_image_metadata( $payload, $settings ) {
        $suggestions = isset( $payload['image_suggestions'] ) && is_array( $payload['image_suggestions'] ) ? $payload['image_suggestions'] : [];
        if ( empty( $suggestions ) ) {
            return;
        }

        $auto_apply = ! empty( $settings['ai_sync_auto_apply_image_metadata'] );
        foreach ( $suggestions as $suggestion ) {
            $should_apply = $auto_apply || ! empty( $suggestion['apply'] );
            if ( ! $should_apply ) {
                continue;
            }

            $attachment_id = absint( $suggestion['source_id'] ?? $suggestion['id'] ?? 0 );
            if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
                continue;
            }

            if ( isset( $suggestion['alt'] ) ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $suggestion['alt'] ) );
            }

            $postarr = [ 'ID' => $attachment_id ];
            if ( ! empty( $suggestion['title'] ) ) {
                $postarr['post_title'] = sanitize_text_field( $suggestion['title'] );
            }
            if ( ! empty( $suggestion['caption'] ) ) {
                $postarr['post_excerpt'] = sanitize_text_field( $suggestion['caption'] );
            }
            if ( count( $postarr ) > 1 ) {
                wp_update_post( $postarr );
            }
        }
    }

    private function write_post_meta_outputs( $post_id, $payload, $settings ) {
        if ( ! empty( $settings['ai_sync_write_shynh_seo'] ) ) {
            update_post_meta( $post_id, '_shynh_seo_focus_keyword', sanitize_text_field( $payload['focus_keyword'] ?? '' ) );
            update_post_meta( $post_id, '_shynh_seo_title', sanitize_text_field( $payload['seo_title'] ?? $payload['title'] ?? '' ) );
            update_post_meta( $post_id, '_shynh_seo_meta_desc', sanitize_textarea_field( $payload['meta_description'] ?? '' ) );
            update_post_meta( $post_id, '_shynh_seo_schema_faq', wp_json_encode( $payload['faqs'] ?? [], JSON_UNESCAPED_UNICODE ) );
        }

        if ( ! empty( $settings['ai_sync_write_yoast'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $payload['focus_keyword'] ?? '' ) );
            update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $payload['seo_title'] ?? $payload['title'] ?? '' ) );
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $payload['meta_description'] ?? '' ) );
        }
    }

    private function write_term_meta_outputs( $term_id, $payload, $settings ) {
        if ( ! empty( $settings['ai_sync_write_shynh_seo'] ) ) {
            update_term_meta( $term_id, '_shynh_seo_focus_keyword', sanitize_text_field( $payload['focus_keyword'] ?? '' ) );
            update_term_meta( $term_id, '_shynh_seo_title', sanitize_text_field( $payload['seo_title'] ?? $payload['title'] ?? '' ) );
            update_term_meta( $term_id, '_shynh_seo_meta_desc', sanitize_textarea_field( $payload['meta_description'] ?? '' ) );
            update_term_meta( $term_id, '_shynh_seo_schema_faq', wp_json_encode( $payload['faqs'] ?? [], JSON_UNESCAPED_UNICODE ) );
        }

        if ( ! empty( $settings['ai_sync_write_yoast'] ) ) {
            update_term_meta( $term_id, '_yoast_wpseo_focuskw', sanitize_text_field( $payload['focus_keyword'] ?? '' ) );
            update_term_meta( $term_id, '_yoast_wpseo_title', sanitize_text_field( $payload['seo_title'] ?? $payload['title'] ?? '' ) );
            update_term_meta( $term_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $payload['meta_description'] ?? '' ) );
        }
    }

    private function link_polylang_posts( $source_id, $target_id, $target_lang ) {
        if ( ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) || empty( Shynh_SEO_Core::get_settings()['ai_sync_link_polylang'] ) ) {
            return;
        }

        $source_lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $source_id ) : 'vi';
        pll_set_post_language( $target_id, $target_lang );
        pll_save_post_translations( array_filter( [
            $source_lang ?: 'vi' => $source_id,
            $target_lang         => $target_id,
        ] ) );
    }

    private function link_polylang_terms( $source_id, $target_id, $target_lang ) {
        if ( ! function_exists( 'pll_set_term_language' ) || ! function_exists( 'pll_save_term_translations' ) || empty( Shynh_SEO_Core::get_settings()['ai_sync_link_polylang'] ) ) {
            return;
        }

        $source_lang = function_exists( 'pll_get_term_language' ) ? pll_get_term_language( $source_id ) : 'vi';
        pll_set_term_language( $target_id, $target_lang );
        pll_save_term_translations( array_filter( [
            $source_lang ?: 'vi' => $source_id,
            $target_lang         => $target_id,
        ] ) );
    }

    private function get_post_seo_meta( $post_id ) {
        return [
            'focus_keyword' => get_post_meta( $post_id, '_shynh_seo_focus_keyword', true ),
            'seo_title'     => get_post_meta( $post_id, '_shynh_seo_title', true ),
            'meta_desc'     => get_post_meta( $post_id, '_shynh_seo_meta_desc', true ),
            'schema_type'   => get_post_meta( $post_id, '_shynh_seo_schema_type', true ),
            'schema_faq'    => get_post_meta( $post_id, '_shynh_seo_schema_faq', true ),
        ];
    }

    private function get_term_seo_meta( $term_id ) {
        return [
            'focus_keyword' => get_term_meta( $term_id, '_shynh_seo_focus_keyword', true ),
            'seo_title'     => get_term_meta( $term_id, '_shynh_seo_title', true ),
            'meta_desc'     => get_term_meta( $term_id, '_shynh_seo_meta_desc', true ),
            'schema_type'   => get_term_meta( $term_id, '_shynh_seo_schema_type', true ),
            'schema_faq'    => get_term_meta( $term_id, '_shynh_seo_schema_faq', true ),
        ];
    }

    private function get_post_yoast_meta( $post_id ) {
        return [
            'focus_keyword' => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
            'seo_title'     => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
            'meta_desc'     => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
        ];
    }

    private function get_term_yoast_meta( $term_id ) {
        return [
            'focus_keyword' => get_term_meta( $term_id, '_yoast_wpseo_focuskw', true ),
            'seo_title'     => get_term_meta( $term_id, '_yoast_wpseo_title', true ),
            'meta_desc'     => get_term_meta( $term_id, '_yoast_wpseo_metadesc', true ),
        ];
    }

    private function collect_post_terms( $post_id, $post_type ) {
        $items = [];
        foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
            if ( ! in_array( $taxonomy->name, $this->get_supported_taxonomies(), true ) ) {
                continue;
            }

            $terms = wp_get_post_terms( $post_id, $taxonomy->name );
            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $items[] = [
                    'source_term_id' => $term->term_id,
                    'taxonomy'       => $taxonomy->name,
                    'name'           => $term->name,
                    'slug'           => $term->slug,
                    'description'    => $term->description,
                    'language'       => function_exists( 'pll_get_term_language' ) ? pll_get_term_language( $term->term_id ) : '',
                    'english_id'     => function_exists( 'pll_get_term' ) ? absint( pll_get_term( $term->term_id, 'en' ) ) : 0,
                ];
            }
        }
        return $items;
    }

    private function get_image_policy( $settings ) {
        return [
            'preserve_original_images'    => ! empty( $settings['ai_sync_preserve_images'] ),
            'copy_featured_image'         => ! empty( $settings['ai_sync_copy_featured_image'] ),
            'suggest_metadata'            => ! empty( $settings['ai_sync_suggest_image_metadata'] ),
            'auto_apply_metadata'         => ! empty( $settings['ai_sync_auto_apply_image_metadata'] ),
            'suggest_additional_images'   => ! empty( $settings['ai_sync_suggest_additional_images'] ),
            'allow_ai_generated_images'   => ! empty( $settings['ai_sync_allow_ai_generated_images'] ),
            'source_priority'             => [ 'media_library', 'related_content', 'brand_assets', 'ai_generated_if_enabled' ],
        ];
    }

    private function get_site_media_candidates_for_post( $post_id, $post ) {
        $keywords = [ get_the_title( $post_id ), $post->post_name ];

        $terms = wp_get_post_terms( $post_id, get_object_taxonomies( $post->post_type, 'names' ) );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $keywords[] = $term->name;
                $keywords[] = $term->slug;
            }
        }

        return $this->get_site_media_candidates( $keywords );
    }

    private function get_site_media_candidates( $keywords ) {
        $keywords = array_values( array_filter( array_unique( array_map( 'sanitize_text_field', (array) $keywords ) ) ) );
        $items    = [];

        foreach ( array_slice( $keywords, 0, 6 ) as $keyword ) {
            if ( '' === trim( $keyword ) ) {
                continue;
            }

            $query = new WP_Query( [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image',
                's'              => $keyword,
                'posts_per_page' => 4,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ] );

            foreach ( $query->posts as $attachment_id ) {
                if ( isset( $items[ $attachment_id ] ) ) {
                    continue;
                }

                $items[ $attachment_id ] = [
                    'id'      => $attachment_id,
                    'src'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
                    'alt'     => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                    'caption' => wp_get_attachment_caption( $attachment_id ),
                    'title'   => get_the_title( $attachment_id ),
                    'matched' => $keyword,
                ];
            }
        }

        return array_values( array_slice( $items, 0, 12, true ) );
    }

    private function get_featured_image_payload( $post_id ) {
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumbnail_id ) {
            return null;
        }

        return [
            'id'      => $thumbnail_id,
            'src'     => wp_get_attachment_image_url( $thumbnail_id, 'full' ),
            'alt'     => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
            'caption' => wp_get_attachment_caption( $thumbnail_id ),
            'title'   => get_the_title( $thumbnail_id ),
        ];
    }

    private function extract_media_blocks( $content ) {
        $blocks = parse_blocks( (string) $content );
        $total_length = max( 1, strlen( (string) $content ) );
        $items = [];
        $this->walk_media_blocks( $blocks, $items, $total_length, 0 );

        return $items;
    }

    private function walk_media_blocks( $blocks, &$items, $total_length, $offset ) {
        foreach ( (array) $blocks as $block ) {
            $block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
            $html       = serialize_block( $block );

            if ( $this->is_media_block( $block_name, $block, $html ) ) {
                $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
                $items[] = [
                    'blockName' => $block_name,
                    'id'        => absint( $attrs['id'] ?? $attrs['mediaId'] ?? 0 ),
                    'url'       => esc_url_raw( $attrs['url'] ?? $attrs['src'] ?? '' ),
                    'html'      => $html,
                    'ratio'     => min( 1, max( 0, $offset / $total_length ) ),
                    'block'     => $block,
                ];
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $this->walk_media_blocks( $block['innerBlocks'], $items, $total_length, $offset );
            }

            $offset += strlen( $html );
        }
    }

    private function is_media_block( $block_name, $block, $html ) {
        $media_blocks = [
            'core/image',
            'core/gallery',
            'core/cover',
            'core/media-text',
            'core/video',
            'core/audio',
            'core/embed',
            'core/file',
        ];

        if ( in_array( $block_name, $media_blocks, true ) ) {
            return true;
        }

        if ( false !== strpos( $block_name, 'image' ) || false !== strpos( $block_name, 'gallery' ) || false !== strpos( $block_name, 'media' ) ) {
            return true;
        }

        return is_string( $html ) && preg_match( '/<(img|video|picture|figure|iframe)\b/i', $html );
    }

    private function content_has_media_reference( $content, $media_block ) {
        $content = (string) $content;

        if ( ! empty( $media_block['id'] ) && false !== strpos( $content, (string) absint( $media_block['id'] ) ) ) {
            return true;
        }

        if ( ! empty( $media_block['url'] ) && false !== strpos( $content, $media_block['url'] ) ) {
            return true;
        }

        if ( ! empty( $media_block['html'] ) && false !== strpos( $content, trim( $media_block['html'] ) ) ) {
            return true;
        }

        return false;
    }

    private function enrich_images( $images ) {
        $items = [];
        foreach ( (array) $images as $image ) {
            $src = $image['src'] ?? '';
            $id  = attachment_url_to_postid( $src );
            $items[] = [
                'id'      => $id,
                'src'     => $src,
                'alt'     => $id ? get_post_meta( $id, '_wp_attachment_image_alt', true ) : ( $image['alt'] ?? '' ),
                'caption' => $id ? wp_get_attachment_caption( $id ) : '',
                'title'   => $id ? get_the_title( $id ) : '',
            ];
        }
        return $items;
    }

    private function extract_response_text( $decoded ) {
        if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
            return (string) $decoded['choices'][0]['message']['content'];
        }
        return '';
    }

    private function empty_ai_payload() {
        return [
            'title'             => '',
            'slug'              => '',
            'h1'                => '',
            'excerpt'           => '',
            'content'           => '',
            'focus_keyword'     => '',
            'seo_title'         => '',
            'meta_description'  => '',
            'quick_answer'      => '',
            'faqs'              => [],
            'image_suggestions' => [],
            'additional_image_suggestions' => [],
            'terms'             => [],
            'reviewer_notes'    => [],
        ];
    }

    private function is_enabled() {
        return ! empty( Shynh_SEO_Core::get_settings()['ai_sync_enabled'] );
    }

    private function get_supported_post_types() {
        $settings = Shynh_SEO_Core::get_settings();
        $public   = array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), [ 'attachment' ] ) );

        if ( ! empty( $settings['ai_sync_auto_include_post_types'] ) ) {
            return array_values( array_diff( $public, (array) ( $settings['ai_sync_excluded_post_types'] ?? [] ) ) );
        }

        return array_values( array_intersect( (array) ( $settings['ai_sync_post_types'] ?? [] ), $public ) );
    }

    private function get_supported_taxonomies() {
        $settings = Shynh_SEO_Core::get_settings();
        $public   = array_values( array_diff( get_taxonomies( [ 'public' => true ], 'names' ), [ 'post_format' ] ) );

        if ( ! empty( $settings['ai_sync_auto_include_taxonomies'] ) ) {
            return array_values( array_diff( $public, (array) ( $settings['ai_sync_excluded_taxonomies'] ?? [] ) ) );
        }

        return array_values( array_intersect( (array) ( $settings['ai_sync_taxonomies'] ?? [] ), $public ) );
    }

    private function verify_ajax() {
        check_ajax_referer( 'shynh_seo_ai_sync', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Ban khong co quyen thuc hien thao tac nay.', 'shynh-seo' ) ] );
        }
    }
}
