<?php
/**
 * Shynh SEO — Interactive AI Assistant Module (OpenAI Integration & Floating Drawer UI).
 *
 * Provides a flexible floating AI Assistant Drawer at the bottom-right of edit screens
 * with comprehensive Beauty, Spa, Cosmeceuticals & SEO/GEO/AEO 2026 preset chips.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_AI_Assistant {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_footer', [ $this, 'render_floating_assistant' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_shynh_seo_ai_assistant_generate', [ $this, 'ajax_generate' ] );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ], true ) ) {
            return;
        }

        wp_enqueue_script(
            'shynh-seo-ai-assistant',
            SHYNH_SEO_URL . 'assets/js/ai-assistant.js',
            [ 'jquery' ],
            SHYNH_SEO_VERSION,
            true
        );

        wp_localize_script( 'shynh-seo-ai-assistant', 'shynhSeoAiAssistant', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'shynh_seo_ai_assistant' ),
            'i18n'    => [
                'generating'  => __( '⏳ OpenAI đang xử lý & sinh nội dung...', 'shynh-seo' ),
                'applied'     => __( '✅ Đã áp dụng tất cả vào Form!', 'shynh-seo' ),
                'error'       => __( 'Có lỗi khi kết nối OpenAI API. Vui lòng thử lại.', 'shynh-seo' ),
                'enterPrompt' => __( 'Vui lòng nhập yêu cầu hoặc chọn gợi ý nhanh bên trên.', 'shynh-seo' ),
            ],
        ] );
    }

    public function render_floating_assistant() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'post', 'term', 'edit-tags' ], true ) ) {
            return;
        }

        $object_id   = 0;
        $object_type = 'post';
        $taxonomy    = '';

        if ( 'post' === $screen->base && isset( $GLOBALS['post']->ID ) ) {
            $object_id   = $GLOBALS['post']->ID;
            $object_type = 'post';
        } elseif ( in_array( $screen->base, [ 'term', 'edit-tags' ], true ) ) {
            $object_type = 'term';
            $taxonomy    = $screen->taxonomy ?: '';
            if ( isset( $_GET['tag_ID'] ) ) {
                $object_id = absint( $_GET['tag_ID'] );
            }
        }

        $source = 'term' === $object_type ? $this->collect_term_source( $object_id, $taxonomy ) : $this->collect_post_source( $object_id );
        $dynamic_chips = $this->get_dynamic_service_chips( $source );

        ?>
        <!-- Floating AI Trigger Badge -->
        <button type="button" id="shynh-seo-ai-floating-trigger" title="Mở Trợ Lý AI Tương Tác (Alt + A)">
            <span class="shynh-seo-ai-icon">🤖</span>
            <span class="shynh-seo-ai-label">Trợ Lý AI</span>
            <span class="shynh-seo-ai-pulse"></span>
        </button>

        <!-- Floating AI Drawer Container -->
        <div id="shynh-seo-ai-drawer" class="shynh-seo-ai-drawer-wrap" data-object-type="<?php echo esc_attr( $object_type ); ?>" data-object-id="<?php echo esc_attr( $object_id ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" style="display:none;">
            <div class="shynh-seo-ai-drawer-header">
                <div class="shynh-seo-ai-drawer-title">
                    <span class="shynh-seo-ai-badge-icon">🤖</span>
                    <div>
                        <strong>Shynh AI Assistant</strong>
                        <span class="shynh-seo-ai-subtitle">GPT-5.6 Sol / Terra / Luna • Brand Brain & GEO 2026</span>
                    </div>
                </div>
                <div class="shynh-seo-ai-drawer-controls">
                    <button type="button" id="shynh-seo-ai-drawer-close" title="Đóng cửa sổ">✕</button>
                </div>
            </div>

            <div class="shynh-seo-ai-drawer-body">
                <!-- Preset Category Tabs -->
                <div class="shynh-seo-ai-tab-nav">
                    <?php if ( ! empty( $dynamic_chips ) ) : ?>
                        <button type="button" class="shynh-seo-ai-tab-btn active" data-tab="dynamic_service">⭐ Dịch Vụ Hiện Tại</button>
                    <?php endif; ?>
                    <button type="button" class="shynh-seo-ai-tab-btn <?php echo empty( $dynamic_chips ) ? 'active' : ''; ?>" data-tab="med_spa">🏥 Thẩm Mỹ Y Khoa</button>
                    <button type="button" class="shynh-seo-ai-tab-btn" data-tab="luxury_spa">💆 Luxury Spa</button>
                    <button type="button" class="shynh-seo-ai-tab-btn" data-tab="cosmetics">🧴 Dược Mỹ Phẩm</button>
                    <button type="button" class="shynh-seo-ai-tab-btn" data-tab="seo_geo_aeo">🚀 SEO/GEO/AEO 2026</button>
                </div>

                <?php if ( ! empty( $dynamic_chips ) ) : ?>
                    <!-- Tab 0: Dynamic Service Chips -->
                    <div class="shynh-seo-ai-tab-pane active" id="shynh-tab-dynamic_service">
                        <div class="shynh-seo-ai-chips">
                            <?php foreach ( $dynamic_chips as $chip ) : ?>
                                <button type="button" class="shynh-seo-ai-chip" data-prompt="<?php echo esc_attr( $chip['prompt'] ); ?>"><?php echo esc_html( $chip['label'] ); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tab 1: Medical Aesthetics -->
                <div class="shynh-seo-ai-tab-pane <?php echo empty( $dynamic_chips ) ? 'active' : ''; ?>" id="shynh-tab-med_spa">
                    <div class="shynh-seo-ai-chips">
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Nhấn mạnh chuẩn Y Khoa, Bác sĩ CKI chuyên khoa da liễu, phác đồ cá nhân hóa và tiêu chuẩn vô trùng phòng Lab.">🩺 Chuẩn Y Khoa & Bác Sĩ</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Nhấn mạnh công nghệ cao FDA Hoa Kỳ (Thermage FLX / Ultherapy / Picoway chính hãng), bước sóng thông minh, hiệu quả không xâm lấn.">⚡ Công Nghệ Cao FDA</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Nhấn mạnh thời gian nghỉ dưỡng 0 ngày, chứng nhận an toàn y tế y khoa, cam kết không tác dụng phụ.">🛡️ An Toàn & Phục Hồi Y Khoa</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Nhấn mạnh kỹ thuật tiêm vi điểm sinh học (Botox / Filler / Exosome chính hãng Galderma/Allergan), tỷ lệ vàng gương mặt tự nhiên.">💉 Tiêm Thẩm Mỹ & Trẻ Hóa</button>
                    </div>
                </div>

                <!-- Tab 2: Luxury Spa -->
                <div class="shynh-seo-ai-tab-pane" id="shynh-tab-luxury_spa">
                    <div class="shynh-seo-ai-chips">
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Tông giọng quý phái thượng lưu, phòng VIP riêng tư 1:1, âm nhạc & hương thơm liệu trình chuẩn Pháp/Thụy Sĩ.">💎 Đẳng Cấp Spa 5 Sao</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Trẻ hóa da chuyên sâu, phục hồi hàng rào bảo vệ da (Skin Barrier), cấp ẩm đa tầng da bóng khỏe Glow.">🌿 Skin Rejuvenation & Detox</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Liệu trình trị liệu vóc dáng cao cấp, giải tỏa căng thẳng kết hợp công nghệ hiện đại và dưỡng sinh Đông y.">🧘 Body Sculpting & Relaxation</button>
                    </div>
                </div>

                <!-- Tab 3: Cosmeceuticals -->
                <div class="shynh-seo-ai-tab-pane" id="shynh-tab-cosmetics">
                    <div class="shynh-seo-ai-chips">
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Hoạt chất điều trị nồng độ chuẩn y khoa (Niacinamide, Retinol, HA, Peptide, Exosome, Vitamin C) chuẩn y sĩ da liễu.">🧪 Dược Mỹ Phẩm Chuẩn Bác Sĩ</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Công thức dưỡng ẩm căng bóng Glass Skin, ức chế sắc tố Melanin, mờ thâm nám sạm da rạng rỡ.">✨ Da Căng Bóng Glass Skin</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Giảm đỏ, làm dịu rát, tái tạo cấu trúc da tổn thương nhạy cảm sau laser/peel cấp tốc.">🛑 Phục Hồi Da Nhạy Cảm</button>
                    </div>
                </div>

                <!-- Tab 4: SEO / GEO / AEO 2026 -->
                <div class="shynh-seo-ai-tab-pane" id="shynh-tab-seo_geo_aeo">
                    <div class="shynh-seo-ai-chips">
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Sử dụng linh hoạt các biến snippet macro %%title%% %%sep%% %%sitename%% để tối ưu tiêu đề SEO.">🏷️ Dùng Biến Macro %%title%% %%sitename%%</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Phân tích đối chiếu với kết quả Top 1-3 Google Index hiện tại, tự động phát hiện và bổ sung Lỗ Hổng Thông Tin (Content Gaps) để tạo câu trả lời vượt trội chuẩn Answer-First, giúp Google SGE, Gemini & ChatGPT ưu tiên trích dẫn Top 1.">🔥 GEO Superiority (Đánh Bại Top 1 Google)</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Viết theo cấu trúc Answer-First (trả lời trực tiếp 2-3 câu ngắn gọn ở đầu) để các công cụ tìm kiếm AI (ChatGPT, Perplexity, Google SGE/Gemini) ưu tiên trích dẫn Top 1.">🎯 GEO Answer-First (SGE/AI)</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Trực tiếp trả lời ý định tìm kiếm bằng giọng nói thực tế của khách hàng: Giá bao nhiêu, Giữ được bao lâu, Có đau không, Liệu trình mấy buổi.">❓ AEO Voice Search Intent</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Bổ sung bằng chứng chuyên gia, chứng nhận y tế và câu tuyên bố miễn trừ y tế theo chuẩn Google E-E-A-T & YMYL Guidelines.">🏆 E-E-A-T & YMYL Trust</button>
                        <button type="button" class="shynh-seo-ai-chip" data-prompt="Thêm Lời kêu gọi hành động (CTA) đặt lịch soi da AI miễn phí và khám trực tiếp cùng Bác sĩ Trưởng khoa.">🔥 VIP Booking & Conversion CTA</button>
                    </div>
                </div>

                <div class="shynh-seo-ai-field-group" style="margin-top:10px;">
                    <label for="shynh_seo_ai_gen_mode">⚙️ Chọn chế độ tạo sinh:</label>
                    <select class="shynh-seo-ai-gen-mode">
                        <option value="preserve_enrich">⚡ Nâng cấp & Bổ sung dựa trên Cấu Trúc Có Sẵn (Bảo tồn Gutenberg/H2/H3)</option>
                        <option value="fresh_generation">✨ Tạo sinh mới hoàn toàn (Khởi tạo dàn ý mới)</option>
                    </select>
                </div>

                <div class="shynh-seo-ai-field-group" style="margin-top:10px;">
                    <label for="shynh_seo_ai_target_field">🎯 Chọn mục cần sinh / tối ưu:</label>
                    <select class="shynh-seo-ai-target-field">
                        <option value="all">🚀 TẤT CẢ CÁC MỤC (Trọn bộ SEO, GEO & Social 1-Click)</option>
                        <option value="title">Tiêu đề SEO (SEO Title 50-60 ký tự)</option>
                        <option value="meta_desc">Mô tả Meta (Meta Description 140-160 ký tự)</option>
                        <option value="keyword">Từ khóa chính (Focus Keyword)</option>
                        <option value="quick_answer">Quick Answer (Trích đoạn GEO/AEO 2-3 câu)</option>
                        <option value="slug">Đường dẫn URL (Slug)</option>
                        <option value="term_description">Mô tả danh mục (Term Description)</option>
                        <option value="fb_title">Tiêu đề Facebook Social</option>
                        <option value="fb_desc">Mô tả Facebook Social</option>
                    </select>
                </div>

                <div class="shynh-seo-ai-field-group">
                    <label for="shynh_seo_ai_user_prompt">✍️ Yêu cầu tùy chỉnh của bạn:</label>
                    <textarea class="shynh-seo-ai-user-prompt" rows="3" placeholder="Nhập mong muốn riêng (ví dụ: Nhấn mạnh công nghệ Thermage FLX 2026 và dùng biến %%title%% %%sitename%%)..."></textarea>
                </div>

                <button type="button" class="button button-primary shynh-seo-ai-generate-btn">
                    ✨ OpenAI Sinh Theo Yêu Cầu
                </button>

                <div class="shynh-seo-ai-preview-card" style="display: none;">
                    <div class="shynh-seo-ai-preview-header">
                        <span>🎉 Kết quả từ OpenAI API</span>
                        <span class="shynh-seo-ai-char-counter"></span>
                    </div>
                    <div class="shynh-seo-ai-preview-body"></div>
                    <div class="shynh-seo-ai-preview-footer">
                        <button type="button" class="button button-primary shynh-seo-ai-apply-btn">✅ Áp dụng vào Form</button>
                        <button type="button" class="button shynh-seo-ai-retry-btn">🔄 Sinh lại</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_dynamic_service_chips( $source ) {
        $chips = [];
        $title = strtolower( ( $source['title'] ?? '' ) . ' ' . ( $source['name'] ?? '' ) . ' ' . ( $source['plainText'] ?? '' ) );

        if ( preg_match( '/\b(thermage|nâng cơ|trẻ hóa|ultherapy|hifu|rf)\b/i', $title ) ) {
            $chips[] = [ 'label' => '⚡ Thermage FLX 2026', 'prompt' => 'Nhấn mạnh công nghệ Thermage FLX 2026 sóng RF đơn cực, nảy sinh collagen tự thân, không xâm lấn.' ];
            $chips[] = [ 'label' => '🩺 Phác Đồ Nâng Cơ Y Khoa', 'prompt' => 'Ép phác đồ nâng cơ xóa nhăn chuẩn y khoa do Bác sĩ CKI thực hiện.' ];
        }
        if ( preg_match( '/\b(nám|picoway|tàn nhang|sắc tố|laser)\b/i', $title ) ) {
            $chips[] = [ 'label' => '🎯 Laser Picoway Trị Nám', 'prompt' => 'Nhấn mạnh Laser Picoway bước sóng Pico giây bắn phá sắc tố Melanin, mờ nám sạm 90% không tổn thương da.' ];
            $chips[] = [ 'label' => '🛡️ Phục Hồi Màng Da', 'prompt' => 'Phục hồi màng da nhạy cảm sau laser, ngăn ngừa tái phát nám.' ];
        }
        if ( preg_match( '/\b(filler|botox|exosome|tiêm|tiêm vi điểm)\b/i', $title ) ) {
            $chips[] = [ 'label' => '💉 Tiêm Vi Điểm Exosome/Filler', 'prompt' => 'Kỹ thuật tiêm vi điểm sinh học chính hãng Galderma/Allergan, làm đầy tự nhiên tỷ lệ vàng.' ];
        }
        if ( preg_match( '/\b(spa|chăm sóc da|tắm trắng|thải độc|detox)\b/i', $title ) ) {
            $chips[] = [ 'label' => '💎 Spa 5 Sao Thượng Lưu', 'prompt' => 'Liệu trình chăm sóc da chuẩn Spa 5 sao Thụy Sĩ/Pháp, không gian VIP riêng tư.' ];
        }

        if ( empty( $chips ) && ! empty( $source['title'] ) ) {
            $chips[] = [ 'label' => '💎 Thương Hiệu Shynh Luxury', 'prompt' => 'Nhấn mạnh đẳng cấp 5 sao Shynh Super Luxury và trải nghiệm chăm sóc khách hàng VIP.' ];
            $chips[] = [ 'label' => '🎯 Tối Ưu Tốc Độ Chuyển Đổi', 'prompt' => 'Nâng cao tỷ lệ chuyển đổi, thúc đẩy khách hàng đăng ký khám cùng Bác sĩ Trưởng khoa.' ];
        }

        return $chips;
    }

    public function ajax_generate() {
        check_ajax_referer( 'shynh_seo_ai_assistant', 'nonce' );

        $object_type   = isset( $_POST['objectType'] ) ? sanitize_key( wp_unslash( $_POST['objectType'] ) ) : 'post';
        $object_id     = isset( $_POST['objectId'] ) ? absint( $_POST['objectId'] ) : 0;
        $taxonomy      = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $target_field  = isset( $_POST['targetField'] ) ? sanitize_key( wp_unslash( $_POST['targetField'] ) ) : 'title';
        $gen_mode      = isset( $_POST['genMode'] ) ? sanitize_key( wp_unslash( $_POST['genMode'] ) ) : 'preserve_enrich';
        $user_prompt   = isset( $_POST['userPrompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['userPrompt'] ) ) : '';
        $from_tag      = isset( $_POST['fromTag'] ) ? absint( $_POST['fromTag'] ) : 0;
        $current_kw    = isset( $_POST['currentKeyword'] ) ? sanitize_text_field( wp_unslash( $_POST['currentKeyword'] ) ) : '';
        $current_title = isset( $_POST['currentTitle'] ) ? sanitize_text_field( wp_unslash( $_POST['currentTitle'] ) ) : '';
        $current_desc  = isset( $_POST['currentDesc'] ) ? sanitize_text_field( wp_unslash( $_POST['currentDesc'] ) ) : '';

        $source = 'term' === $object_type
            ? $this->collect_term_source( $object_id, $taxonomy, $from_tag )
            : $this->collect_post_source( $object_id );

        if ( is_wp_error( $source ) ) {
            wp_send_json_error( [ 'message' => $source->get_error_message() ] );
        }

        $existing_keywords = class_exists( 'Shynh_SEO_Cannibalization' ) ? Shynh_SEO_Cannibalization::get_existing_keywords( $object_id, $object_type ) : [];
        $existing_titles   = class_exists( 'Shynh_SEO_Cannibalization' ) ? Shynh_SEO_Cannibalization::get_existing_titles( $object_id, $object_type ) : [];

        $settings = Shynh_SEO_Core::get_settings();
        $api_key  = trim( $settings['ai_sync_openai_api_key'] ?? '' );
        $model    = $settings['ai_sync_openai_model_economy'] ?? 'gpt-5.6-luna';

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Chưa cấu hình OpenAI API Key trong cài đặt Shynh SEO.', 'shynh-seo' ) ] );
        }

        $response = $this->query_openai_fused_field( $target_field, $user_prompt, $source, $current_kw, $current_title, $current_desc, $existing_keywords, $existing_titles, $api_key, $model, $settings, $gen_mode );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        wp_send_json_success( $response );
    }

    private function query_openai_fused_field( $target_field, $user_prompt, $source, $current_kw, $current_title, $current_desc, $existing_keywords, $existing_titles, $api_key, $model, $settings = [], $gen_mode = 'preserve_enrich' ) {
        $macro_note = ' You MAY use plugin snippet macros like %%title%%, %%sitename%%, %%sitedesc%%, %%sep%%, %%page%% in the text where appropriate (e.g. "[Keyword / Benefit] %%sep%% %%sitename%%").';

        $system_instructions = [
            'all'       => 'You are a Senior SEO, GEO & Social Media Strategist for Shynh Super Luxury.' . $macro_note . ' Generate a complete optimal JSON payload containing: "title" (SEO title 50-60 chars using %%title%% %%sep%% %%sitename%% where appropriate), "meta_desc" (Meta desc 140-160 chars), "keyword" (Focus keyword 2-4 words), "quick_answer" (Answer-First GEO 2-3 sentences), "slug" (lowercase-ascii-slug), "fb_title" (Facebook title), "fb_desc" (Facebook desc), "term_description" (Rich description 150-250 words), and "blocks" (An array of block objects adhering strictly to the provided block JSON schema). Return JSON only.',
            'title'     => 'You are a Senior SEO Title Strategist for Shynh Super Luxury.' . $macro_note . ' Generate a Google-optimized SEO Title (strictly 50-60 characters).' . ( $current_kw ? ' MUST contain focus keyword: "' . $current_kw . '" near the beginning.' : '' ) . ' You can use format like: "[Focus Keyword / Benefit] %%sep%% %%sitename%%". MUST NOT match existing titles: ' . implode( ', ', array_slice( $existing_titles, 0, 30 ) ) . '. Return JSON {"value": "title"}',
            'meta_desc' => 'You are a Senior Copywriter & SEO Specialist.' . $macro_note . ' Generate a Google-optimized Meta Description (strictly 140-160 characters) for Shynh Super Luxury.' . ( $current_kw ? ' MUST naturally include focus keyword: "' . $current_kw . '".' : '' ) . ' Highlight luxury aesthetic benefits and end with a strong Call-To-Action. Return JSON {"value": "description"}',
            'keyword'   => 'You are a Senior Google SEO Specialist for Shynh Super Luxury. Extract or generate ONE high-intent Focus Keyword (2-4 words). MUST NOT match existing keywords: ' . implode( ', ', array_slice( $existing_keywords, 0, 30 ) ) . '. Return JSON {"value": "keyword"}',
            'quick_answer' => 'You are an AEO / AI Search Expert (Perplexity, ChatGPT, Google SGE). Write a concise 2-3 sentence Answer-First Quick Answer paragraph summarizing the medical aesthetic solution according to GEO 2026 best practices.' . ( $current_kw ? ' Target keyword: "' . $current_kw . '".' : '' ) . ' Return JSON {"value": "quick_answer"}',
            'slug'      => 'Generate a clean, SEO-friendly ASCII hyphenated slug in lowercase for this topic. Return JSON {"value": "slug"}',
            'term_description' => 'You are a Senior Copywriter for Shynh Super Luxury. Write a rich, compelling 2-3 paragraph Term Description (150-250 words) for this category topic.' . ( $current_kw ? ' MUST incorporate focus keyword: "' . $current_kw . '".' : '' ) . ' Return JSON {"value": "description"}',
            'fb_title'  => 'Generate an engaging Facebook Title for social sharing. Target keyword: "' . $current_kw . '". Return JSON {"value": "fb_title"}',
            'fb_desc'   => 'Generate an engaging 1-2 sentence Facebook Description for social sharing. Target keyword: "' . $current_kw . '". Return JSON {"value": "fb_desc"}',
        ];

        $system_prompt = $system_instructions[ $target_field ] ?? $system_instructions['title'];
        $system_prompt .= "\n\nBRAND AUDIT POLICY: Use the Shynh Brand Intelligence context as binding guidance. Do not invent doctors, credentials, awards, prices, addresses, reviews, ratings, technologies, outcomes, or medical guarantees. Avoid absolute claims such as 100%, risk-free, permanent, no complications, or guaranteed results. If proof is missing, write cautious language and keep the content aligned with visible page content, approved fields, and Knowledge Vault facts.";

        $page_title = ! empty( $source['title'] ) ? $source['title'] : ( $source['name'] ?? '' );
        $page_desc  = ! empty( $source['plainText'] ) ? $source['plainText'] : ( $source['description'] ?? '' );
        $page_url   = $source['url'] ?? '';

        $user_content  = "=== USER CUSTOM INSTRUCTION (HIGHEST PRIORITY) ===\n";
        $user_content .= ! empty( $user_prompt ) ? $user_prompt . "\n\n" : "Generate optimal content according to brand rules.\n\n";
        $user_content .= "=== SHYNH BRAND BRAIN (6 PILLARS OF BRAND INTELLIGENCE) ===\n";
        if ( ! empty( $settings['brand_vision'] ) ) {
            $user_content .= "1. Brand Vision & Position: " . $settings['brand_vision'] . "\n";
        }
        if ( ! empty( $settings['brand_technologies'] ) ) {
            $user_content .= "2. Flagship Technologies: " . $settings['brand_technologies'] . "\n";
        }
        if ( ! empty( $settings['brand_medical_credentials'] ) ) {
            $user_content .= "3. Medical Credentials & YMYL Safety: " . $settings['brand_medical_credentials'] . "\n";
        }
        if ( ! empty( $settings['brand_market_trends'] ) ) {
            $user_content .= "4. Market Trends 2026 & Voice Intent: " . $settings['brand_market_trends'] . "\n";
        }
        if ( ! empty( $settings['brand_usps'] ) ) {
            $user_content .= "5. 5 Exclusive USPs: " . $settings['brand_usps'] . "\n";
        }
        if ( ! empty( $settings['brand_ctas'] ) ) {
            $user_content .= "6. Luxury CTAs: " . $settings['brand_ctas'] . "\n";
        }
        $user_content .= $this->build_brand_intelligence_context( $settings );
        $user_content .= "\n=== PAGE & SITE CONTEXT ===\n";
        $user_content .= "Brand Name: Shynh Super Luxury\n";
        $user_content .= "Target Field: " . $target_field . "\n";
        $user_content .= "Page Title: " . $page_title . "\n";
        if ( ! empty( $page_url ) ) {
            $user_content .= "Page URL: " . $page_url . "\n";
        }
        if ( ! empty( $page_desc ) ) {
            $user_content .= "Page Context: " . mb_substr( wp_strip_all_tags( $page_desc ), 0, 1200, 'UTF-8' ) . "\n";
        }
        if ( ! empty( $current_kw ) ) {
            $user_content .= "Current Focus Keyword: " . $current_kw . "\n";
        }

        $user_content .= "\n=== REAL DATABASE SERVICE TREE (STRICT WHITELIST) ===\n";
        $user_content .= "STRICT RULE: You MUST ONLY write about and mention REAL existing services, child categories, and service posts listed below. You are STRICTLY FORBIDDEN from inventing non-existent services or fake technologies.\n";
        if ( ! empty( $source['primaryCategory'] ) ) {
            $user_content .= "Primary Category: " . $source['primaryCategory'] . "\n";
        }
        if ( ! empty( $source['childCategories'] ) ) {
            $user_content .= "Real Sub-Categories / Child Services: " . implode( ', ', $source['childCategories'] ) . "\n";
        }
        if ( ! empty( $source['siblingServicePosts'] ) ) {
            $user_content .= "Real Existing Service Posts In Database: " . implode( ' | ', $source['siblingServicePosts'] ) . "\n";
        }

        if ( ! empty( $source['gutenbergBlocks'] ) ) {
            $user_content .= "\n=== GUTENBERG BLOCKS CONTEXT ===\n";
            $user_content .= "Preserve Gutenberg Block markup structure: " . implode( ', ', $source['gutenbergBlocks'] ) . "\n";
        }

        if ( ! empty( $source['vaultKnowledge']['matchedProfile'] ) ) {
            $vk = $source['vaultKnowledge'];
            $user_content .= "\n=== KHO TÀI NGUYÊN & MULTI-PLATFORM MEDIA VAULT ===\n";
            if ( ! empty( $vk['inheritedDocs'] ) ) {
                $user_content .= "Official Clinical Docs: " . implode( ' ; ', $vk['inheritedDocs'] ) . "\n";
            }
            if ( ! empty( $vk['inheritedVideos'] ) ) {
                $user_content .= "Authentic Social Video Links (YouTube/FB/TikTok): " . implode( ' ; ', $vk['inheritedVideos'] ) . "\n";
                $embed_data = Shynh_SEO_Knowledge_Vault::build_social_video_embeds( $vk['inheritedVideos'] );
                if ( ! empty( $embed_data['html'] ) ) {
                    $user_content .= "Social Responsive Video Embed Code To Include: " . $embed_data['html'] . "\n";
                }
            }
        }
        $token_map = [];
        if ( $target_field === 'all' && ! empty( $source['rawContent'] ) ) {
            if ( $gen_mode === 'preserve_enrich' ) {
                $user_content .= "\n=== ORIGINAL GUTENBERG CONTENT (RAW HTML) ===\n";
                $user_content .= $source['rawContent'] . "\n\n";
                $user_content .= "CRITICAL RULE (PRESERVE AND ENRICH):\n";
                $user_content .= "1. BẢO TỒN TUYỆT ĐỐI CẤU TRÚC BLOCK: Bài viết chứa mã HTML thô kèm các comment Gutenberg (<!-- wp:... -->). Bạn KHÔNG ĐƯỢC PHÉP sửa đổi tên thẻ Block, các class CSS, hay phá vỡ cấu trúc thẻ HTML của Block. Nếu có chuỗi JSON trong comment (ví dụ: <!-- wp:heading {\"level\":3} -->), PHẢI đồng bộ với thẻ HTML bên trong (ví dụ <h3>).\n";
                $user_content .= "2. TỐI ƯU DỮ LIỆU: Bạn chỉ được phép sửa chữ (text) bên trong các Block. Ví dụ: sửa nội dung thẻ <p>, nội dung thẻ <li>, nội dung trả lời trong FAQ. Nếu thêm FAQ hoặc danh sách, hãy copy y nguyên cấu trúc HTML của phần tử trước đó và điền nội dung mới vào.\n";
                $user_content .= "3. THÊM HOẶC XÓA BLOCK: Bạn có thể thêm các block cơ bản (<!-- wp:paragraph -->, <!-- wp:heading -->, <!-- wp:list -->). Cấm sinh HTML trần trụi (như <p> không có comment bọc). Nếu cần xóa block vô giá trị, hãy xóa cả thẻ comment mở và đóng.\n";
                $user_content .= "4. CUSTOM BLOCKS: Theme 'kenone' hỗ trợ các khối: kenone/faq, kenone/kp-split-content, kenone/kp-service-grid, v.v. Nếu muốn tạo mới, hãy copy chính xác cấu trúc mẫu.\n";
                
                $user_content .= "\n=== TỰ ĐỘNG SINH FAQ (CHUẨN GEO/AEO) ===\n";
                $user_content .= "CRITICAL RULE (BẮT BUỘC TẠO FAQ MỚI):\n";
                $user_content .= "- Bạn PHẢI tạo thêm từ 3 đến 5 câu hỏi FAQ mới trong nội dung bài viết để giải đáp các Lỗ Hổng Nội Dung (Content Gaps) của người dùng.\n";
                $user_content .= "- ĐỊNH DẠNG: Nếu bài viết đang dùng Custom Block FAQ, hãy copy chính xác cấu trúc HTML đó. Nếu không, hãy sử dụng thẻ Heading H3 cho câu hỏi và Paragraph cho câu trả lời.\n";
                $user_content .= "- CHUẨN AEO/GEO: Câu trả lời phải bám sát vào Knowledge Vault hiện có, KHÔNG bịa đặt thông tin y khoa. Phải viết theo cơ chế Answer-First (trả lời trực diện, đúng trọng tâm ngay ở 2-3 câu đầu tiên, sử dụng gạch đầu dòng nếu cần thiết) để giành vị trí Top 1 Snippet trên Google/SGE.\n";
                
                $existing_faqs = current_user_can('edit_posts') ? $this->get_existing_contextual_faqs( $source['objectType'], $source['objectId'], $current_kw, $current_title ) : [];
                if ( ! empty( $existing_faqs ) ) {
                    $user_content .= "\n=== ANTI-DUPLICATE: DANH SÁCH FAQ ĐÃ TỒN TẠI TRONG DATABASE ===\n";
                    $user_content .= "Dưới đây là danh sách các câu hỏi FAQ đã tồn tại trên website. BẠN BỊ CẤM TẠO RA CÁC CÂU HỎI MỚI BỊ TRÙNG LẶP HOẶC CÓ Ý NGHĨA TƯƠNG ĐƯƠNG với các câu hỏi này:\n";
                    foreach ( $existing_faqs as $i => $q ) {
                        $user_content .= "- " . $q . "\n";
                    }
                }

            } else {
                $user_content .= "\n=== FRESH GENERATION ===\n";
                $user_content .= "Bạn được tự do viết lại từ đầu một bài viết Gutenberg mới hoàn toàn dựa trên tri thức được cung cấp (Không cần phụ thuộc bài viết cũ).\n";
                $user_content .= "CRITICAL RULE: MỌI ĐOẠN VĂN, TIÊU ĐỀ, DANH SÁCH đều phải được bọc trong comment Gutenberg (ví dụ: <!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->). Cấm sinh HTML trần trụi!\n";
            }
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
        $context = ( $source['objectType'] === 'post' ) ? 'article' : 'service'; // simplified logic
        
        $body = [
            'model' => $model,
            'messages' => [
                [ 'role' => 'system', 'content' => $system_prompt ],
                [ 'role' => 'user', 'content' => $user_content ],
            ]
        ];

        if ( $target_field === 'all' ) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'shynh_seo_content',
                    'strict' => false,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [ 'type' => 'string' ],
                            'meta_desc' => [ 'type' => 'string' ],
                            'keyword' => [ 'type' => 'string' ],
                            'quick_answer' => [ 'type' => 'string' ],
                            'slug' => [ 'type' => 'string' ],
                            'fb_title' => [ 'type' => 'string' ],
                            'fb_desc' => [ 'type' => 'string' ],
                            'term_description' => [ 'type' => 'string' ],
                            'blocks' => $composer->build_schema_for_context( $context )['properties']['blocks'],
                            'brand_alignment_notes' => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                            'claim_map' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'claim'  => [ 'type' => 'string' ],
                                        'type'   => [ 'type' => 'string' ],
                                        'source' => [ 'type' => 'string' ],
                                        'risk'   => [ 'type' => 'string' ],
                                    ],
                                    'required' => [ 'claim', 'type', 'source', 'risk' ],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'source_map' => [
                                'type'                 => 'object',
                                'additionalProperties' => [ 'type' => 'string' ],
                            ],
                            'risk_flags' => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                            'requires_human_review' => [ 'type' => 'boolean' ]
                        ],
                        'required' => ['title', 'meta_desc', 'keyword', 'quick_answer', 'slug', 'fb_title', 'fb_desc', 'term_description', 'blocks', 'brand_alignment_notes', 'claim_map', 'source_map', 'risk_flags', 'requires_human_review'],
                        'additionalProperties' => false
                    ]
                ]
            ];
        } else {
            $body['response_format'] = [ 'type' => 'json_object' ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $text    = $this->extract_response_text( $decoded );
        $payload = json_decode( $text, true );

        if ( ! is_array( $payload ) ) {
            $err_msg = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : wp_remote_retrieve_body( $response );
            return new WP_Error( 'invalid_json', __( 'OpenAI API không trả về định dạng JSON hợp lệ.', 'shynh-seo' ) . ' Details: ' . $err_msg );
        }

        if ( 'all' === $target_field ) {
            if ( ! empty( $payload['blocks'] ) && is_array( $payload['blocks'] ) ) {
                $compose_result = $composer->compose_markup( $payload, $context );
                $payload['post_content'] = $compose_result['markup'];
                $payload['warnings'] = $compose_result['warnings'];
                $payload['dropped'] = $compose_result['dropped'];
                unset( $payload['blocks'] ); // Remove JSON blocks structure, replace with serialized HTML for JS
            }

            return [
                'isAll'       => true,
                'targetField' => 'all',
                'allData'     => $payload,
            ];
        }

        $value = reset( $payload );
        return [
            'value'       => is_string( $value ) ? trim( $value ) : '',
            'targetField' => $target_field,
            'charCount'   => mb_strlen( is_string( $value ) ? trim( $value ) : '', 'UTF-8' ),
        ];
    }

    private function build_brand_intelligence_context( $settings ) {
        if ( empty( $settings['brand_ai_audit_enabled'] ) ) {
            return '';
        }

        $fields = [
            'brand_positioning'        => 'Brand Positioning',
            'brand_mission'            => 'Mission',
            'brand_values'             => 'Core Values',
            'brand_tone'               => 'Tone of Voice',
            'brand_personas'           => 'Target Personas',
            'brand_content_principles' => 'Content Principles',
            'brand_proof_library'      => 'Verified Proof Library',
            'brand_source_policy'      => 'Source Policy',
            'brand_allowed_claims'     => 'Allowed Claims',
            'brand_banned_claims'      => 'Blocked Claims',
        ];

        $context = "\n=== BRAND INTELLIGENCE AUDIT RULES ===\n";
        foreach ( $fields as $key => $label ) {
            if ( ! empty( $settings[ $key ] ) ) {
                $context .= $label . ': ' . trim( (string) $settings[ $key ] ) . "\n";
            }
        }

        $context .= "\n=== AI OUTPUT GOVERNANCE ===\n";
        $context .= ! empty( $settings['brand_ai_entity_lock'] ) ? "- Entity lock is ON: never change brand name, branch facts, phone, address, approved credentials, or technology names unless present in source.\n" : '';
        $context .= ! empty( $settings['brand_ai_proof_required'] ) ? "- Verified proof is required for strong claims, awards, ratings, doctor credentials, and technology certification.\n" : '';
        $context .= ! empty( $settings['brand_ai_require_source_map'] ) ? "- For full content generation, include source_map fields where the response schema allows it.\n" : '';
        $context .= ! empty( $settings['brand_ai_require_claim_map'] ) ? "- For full content generation, include claim_map fields where the response schema allows it.\n" : '';
        $context .= ! empty( $settings['brand_ai_require_human_review'] ) ? "- Mark medical-aesthetic, YMYL, or unverified proof output as requiring human review.\n" : '';
        $context .= "- Minimum Brand Fit Score: " . absint( $settings['brand_ai_min_brand_fit'] ?? 85 ) . "/100.\n";
        $context .= "- Minimum Claim Safety Score: " . absint( $settings['brand_ai_min_claim_safety'] ?? 95 ) . "/100.\n";

        return $context;
    }

    private function extract_response_text( $decoded ) {
        if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
            return $decoded['choices'][0]['message']['content'];
        }
        if ( isset( $decoded['output'][0]['content'][0]['text'] ) ) {
            return $decoded['output'][0]['content'][0]['text'];
        }
        return '';
    }

    private function get_existing_contextual_faqs( $object_type, $object_id, $keyword, $title ) {
        $faqs = [];
        $args = [
            'post_type'      => [ 'post', 'page', 'dich-vu' ],
            'posts_per_page' => 15,
            'meta_key'       => '_shynh_seo_schema_faq',
            'meta_compare'   => 'EXISTS',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ];

        if ( ! empty( $keyword ) ) {
            $args['s'] = $keyword;
        } elseif ( $object_type === 'post' && $object_id ) {
            $terms = wp_get_post_terms( $object_id, 'category', ['fields' => 'ids'] );
            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                $terms = wp_get_post_terms( $object_id, 'loai-dich-vu', ['fields' => 'ids'] );
            }
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => wp_get_post_terms( $object_id, 'category' ) ? 'category' : 'loai-dich-vu',
                        'field'    => 'term_id',
                        'terms'    => $terms,
                    ]
                ];
            }
        } elseif ( $object_type === 'term' && $object_id ) {
            $term = get_term( $object_id );
            if ( $term && ! is_wp_error( $term ) ) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => $term->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ]
                ];
            }
        }

        $query = new WP_Query( $args );

        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $pid ) {
                if ( $object_type === 'post' && $pid == $object_id ) {
                    continue;
                }
                $faq_json = get_post_meta( $pid, '_shynh_seo_schema_faq', true );
                if ( ! empty( $faq_json ) ) {
                    $decoded = json_decode( html_entity_decode( $faq_json ), true );
                    if ( is_array( $decoded ) ) {
                        foreach ( $decoded as $item ) {
                            if ( ! empty( $item['question'] ) ) {
                                $q = trim( wp_strip_all_tags( $item['question'] ) );
                                if ( ! in_array( $q, $faqs, true ) ) {
                                    $faqs[] = $q;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ( count( $faqs ) > 30 ) {
            shuffle( $faqs );
            $faqs = array_slice( $faqs, 0, 30 );
        }

        return $faqs;
    }

    private function collect_post_source( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Không tìm thấy bài viết.', 'shynh-seo' ) );
        }

        $blocks = function_exists( 'parse_blocks' ) ? parse_blocks( $post->post_content ) : [];
        $block_names = [];
        foreach ( $blocks as $b ) {
            if ( ! empty( $b['blockName'] ) ) {
                $block_names[] = $b['blockName'];
            }
        }

        // Get terms & child terms
        $terms = wp_get_post_terms( $post->ID, 'category' );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            $terms = wp_get_post_terms( $post->ID, 'loai-dich-vu' );
        }
        $primary_term = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0] : null;

        $child_terms = [];
        $category_posts = [];
        if ( $primary_term ) {
            $children = get_terms( [
                'taxonomy'   => $primary_term->taxonomy,
                'hide_empty' => false,
                'parent'     => $primary_term->term_id,
            ] );
            if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
                foreach ( $children as $c ) {
                    $child_terms[] = $c->name;
                }
            }

            $cat_posts = get_posts( [
                'post_type'      => $post->post_type,
                'posts_per_page' => 15,
                'tax_query'      => [
                    [
                        'taxonomy' => $primary_term->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $primary_term->term_id,
                    ],
                ],
            ] );
            foreach ( $cat_posts as $cp ) {
                $category_posts[] = $cp->post_title;
            }
        }

        $vault = class_exists( 'Shynh_SEO_Knowledge_Vault' ) ? Shynh_SEO_Knowledge_Vault::resolve_inherited_knowledge( $primary_term ? $primary_term->term_id : 0, $primary_term ? $primary_term->taxonomy : 'category', $post->ID ) : [];

        return [
            'objectType'         => 'post',
            'objectId'           => $post->ID,
            'postType'           => $post->post_type,
            'title'              => $post->post_title,
            'url'                => get_permalink( $post->ID ),
            'plainText'          => wp_strip_all_tags( $post->post_content ),
            'rawContent'         => $post->post_content,
            'gutenbergBlocks'    => array_unique( $block_names ),
            'primaryCategory'    => $primary_term ? $primary_term->name : '',
            'childCategories'    => $child_terms,
            'siblingServicePosts'=> $category_posts,
            'vaultKnowledge'     => $vault,
        ];
    }

    private function collect_term_source( $term_id, $taxonomy = '', $from_tag = 0 ) {
        $term = get_term( $term_id, $taxonomy );

        if ( ( ! $term || is_wp_error( $term ) ) && $from_tag > 0 ) {
            $from_term = get_term( $from_tag );
            if ( $from_term && ! is_wp_error( $from_term ) ) {
                $term = $from_term;
            }
        }

        if ( ! $term || is_wp_error( $term ) ) {
            return [
                'objectType'          => 'term',
                'objectId'            => 0,
                'taxonomy'            => $taxonomy,
                'title'               => '',
                'name'                => '',
                'description'         => '',
                'plainText'           => '',
                'childCategories'     => [],
                'siblingServicePosts' => [],
                'vaultKnowledge'      => [],
            ];
        }

        $child_terms = [];
        $children = get_terms( [
            'taxonomy'   => $term->taxonomy,
            'hide_empty' => false,
            'parent'     => $term->term_id,
        ] );
        if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
            foreach ( $children as $c ) {
                $child_terms[] = $c->name;
            }
        }

        $category_posts = [];
        $cat_posts = get_posts( [
            'post_type'      => [ 'post', 'page', 'dich-vu' ],
            'posts_per_page' => 15,
            'tax_query'      => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
        ] );
        foreach ( $cat_posts as $cp ) {
            $category_posts[] = $cp->post_title;
        }

        $vault = class_exists( 'Shynh_SEO_Knowledge_Vault' ) ? Shynh_SEO_Knowledge_Vault::resolve_inherited_knowledge( $term->term_id, $term->taxonomy ) : [];

        return [
            'objectType'          => 'term',
            'objectId'            => $term->term_id,
            'taxonomy'            => $term->taxonomy,
            'title'               => $term->name,
            'name'                => $term->name,
            'description'         => $term->description,
            'plainText'           => wp_strip_all_tags( $term->description ),
            'childCategories'     => $child_terms,
            'siblingServicePosts' => $category_posts,
            'vaultKnowledge'      => $vault,
        ];
    }
}
