<?php
/**
 * SEO Analysis Logic (Server-side).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Analysis {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_shynh_seo_analyze', [ $this, 'ajax_analyze' ] );
        add_action( 'save_post', [ $this, 'handle_save_post' ], 50, 3 );
        add_action( 'edited_term', [ $this, 'handle_edited_term' ], 50, 3 );
    }

    public function ajax_analyze() {
        check_ajax_referer( 'shynh_seo_nonce', 'nonce' );

        try {
            $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
            $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

            if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error(
                    [
                        'message' => __( 'You are not allowed to analyze this post.', 'shynh-seo' ),
                    ],
                    403
                );
            }

            if ( $term_id && ! current_user_can( 'edit_term', $term_id ) ) {
                wp_send_json_error(
                    [
                        'message' => __( 'You are not allowed to analyze this term.', 'shynh-seo' ),
                    ],
                    403
                );
            }

            $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
            $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $desc    = isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '';
            $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
            $slug    = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
            $geo_aeo_mode = isset( $_POST['geo_aeo_mode'] ) ? sanitize_key( wp_unslash( $_POST['geo_aeo_mode'] ) ) : null;

            if ( null !== $geo_aeo_mode && ! in_array( $geo_aeo_mode, [ '', 'on', 'off' ], true ) ) {
                $geo_aeo_mode = '';
            }

            $object_type    = $term_id ? 'term' : 'post';
            $object_id      = $term_id ? $term_id : $post_id;
            $object_subtype = '';

            if ( $term_id ) {
                $term = get_term( $term_id );
                if ( $term && ! is_wp_error( $term ) ) {
                    $object_subtype = $term->taxonomy;
                }
            } else {
                $object_subtype = get_post_type( $post_id );
            }

            $analysis = $this->analyze_data( $post_id, $keyword, $title, $desc, $content, $slug, $object_type, $object_id, $object_subtype, $geo_aeo_mode );

            if ( $post_id ) {
                $this->persist_post_analysis( $post_id, $analysis );
            } elseif ( $term_id ) {
                $this->persist_term_analysis( $term_id, $analysis );
            }

            unset( $analysis['_index'], $analysis['_context'] );

            wp_send_json_success( $analysis );
        } catch ( Throwable $e ) {
            wp_send_json_error(
                [
                    'message' => $e->getMessage(),
                    'code'    => 'shynh_seo_analysis_failed',
                ],
                500
            );
        }
    }

    public function analyze_post( $post_id ) {
        $post    = get_post( $post_id );
        if ( ! $post ) return null;

        $keyword = get_post_meta( $post_id, '_shynh_seo_focus_keyword', true );
        $title   = get_post_meta( $post_id, '_shynh_seo_title', true ) ?: $post->post_title;
        $desc    = get_post_meta( $post_id, '_shynh_seo_meta_desc', true );
        $content = $post->post_content;
        $slug    = $post->post_name;

        return $this->analyze_data( $post_id, $keyword, $title, $desc, $content, $slug, 'post', $post_id, $post->post_type );
    }

    public function handle_save_post( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! $post || ! in_array( $post->post_type, Shynh_SEO_Core::get_seo_post_types(), true ) ) {
            return;
        }

        $analysis = $this->analyze_post( $post_id );
        if ( $analysis ) {
            $this->persist_post_analysis( $post_id, $analysis );
        }
    }

    public function handle_edited_term( $term_id, $tt_id, $taxonomy ) {
        if ( ! class_exists( 'Shynh_SEO_Core' ) ) {
            return;
        }

        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }

        if ( ! $this->should_run_geo_aeo( 'term', $term_id, $taxonomy ) ) {
            delete_term_meta( $term_id, '_shynh_seo_geo_aeo_score' );
            delete_term_meta( $term_id, '_shynh_seo_ai_search_score' );
            return;
        }

        $keyword = get_term_meta( $term_id, '_shynh_seo_focus_keyword', true );
        $title   = get_term_meta( $term_id, '_shynh_seo_title', true ) ?: $term->name;
        $desc    = get_term_meta( $term_id, '_shynh_seo_meta_desc', true );
        $content = $term->description;
        $slug    = $term->slug;

        $analysis = $this->analyze_data( 0, $keyword, $title, $desc, $content, $slug, 'term', $term_id, $taxonomy );
        $this->persist_term_analysis( $term_id, $analysis );
    }

    public function analyze_data( $post_id, $keyword, $title, $desc, $content, $slug, $object_type = 'post', $object_id = 0, $object_subtype = '', $geo_aeo_mode_override = null ) {
        $rules   = $this->normalize_rules( get_option( 'shynh_seo_rules', Shynh_SEO_Core::get_default_rules() ) );
        $results = [];

        $context = class_exists( 'Shynh_SEO_Content_Extractor' )
            ? Shynh_SEO_Content_Extractor::extract( $post_id, $content )
            : [
                'html'                => $content,
                'plain_text'          => wp_strip_all_tags( $content ),
                'headings'            => [],
                'images'              => [],
                'internal_links'      => [],
                'bold_internal_links' => [],
                'internal_link_index' => [],
            ];

        $keyword_lower = $this->strtolower( $keyword );
        $content_clean = $context['plain_text'];
        $word_count    = $this->word_count( $content_clean );

        // Nạp dữ liệu FE Rendered Content CHỈ KHI có yêu cầu fetch_fe từ phía client khi người dùng click
        if ( ! empty( $_POST['fetch_fe'] ) && $object_id && class_exists( 'Shynh_SEO_Frontend_Fetcher' ) ) {
            $fe = Shynh_SEO_Frontend_Fetcher::fetch_content( $object_id, $object_type, $object_subtype );
            if ( ! is_wp_error( $fe ) && ! empty( $fe['word_count'] ) && $fe['word_count'] >= $word_count ) {
                $content_clean = $fe['plain_text'];
                $word_count    = $fe['word_count'];
                if ( ! empty( $fe['headings'] ) ) {
                    $context['headings'] = $fe['headings'];
                }
                if ( ! empty( $fe['images'] ) ) {
                    $context['images'] = $fe['images'];
                }
                if ( ! empty( $fe['internal_links'] ) ) {
                    $context['internal_links'] = $fe['internal_links'];
                }
            }
        }

        // 1. Keyword in Title
        if ( $rules['keyword_in_title']['enabled'] ) {
            $status = 'red';
            if ( $keyword ) {
                if ( $this->strpos( $this->strtolower( $title ), $keyword_lower ) !== false ) {
                    $status = 'green';
                }
            }
            $results['keyword_in_title'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? 'Từ khóa xuất hiện trong Tiêu đề SEO.' : 'Từ khóa chưa xuất hiện trong Tiêu đề SEO.',
                'weight'         => $rules['keyword_in_title']['weight'],
                'recommendation' => 'Google khuyến nghị chèn Focus Keyword ở ngay 3-4 từ đầu tiên của Tiêu đề SEO để tối ưu CTR và thứ hạng tìm kiếm.',
                'details'        => $title ? 'Tiêu đề hiện tại: "' . $title . '"' : 'Chưa nhập tiêu đề SEO.',
                'action_tip'     => $status === 'green' ? 'Tiêu đề đã chứa từ khóa chính!' : 'Bấm nút "✨ AI Sinh Title" để AI chèn từ khóa vào tiêu đề tự nhiên nhất.'
            ];
        }

        // 2. Keyword in Meta Desc
        if ( $rules['keyword_in_meta_desc']['enabled'] ) {
            $status = 'red';
            if ( $keyword && $this->strpos( $this->strtolower( $desc ), $keyword_lower ) !== false ) {
                $status = 'green';
            }
            $results['keyword_in_meta_desc'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? 'Từ khóa xuất hiện trong Meta Description.' : 'Từ khóa chưa xuất hiện trong Meta Description.',
                'weight'         => $rules['keyword_in_meta_desc']['weight'],
                'recommendation' => 'Meta Description nên chứa từ khóa chính một cách tự nhiên và kết thúc bằng Lời kêu gọi hành động (CTA).',
                'details'        => $desc ? 'Meta Description: "' . wp_trim_words( $desc, 20, '...' ) . '"' : 'Chưa nhập Meta Description.',
                'action_tip'     => $status === 'green' ? 'Meta Description đã chứa từ khóa!' : 'Bấm nút "✨ AI Sinh Mô Tả" để sinh đoạn mô tả thu hút có từ khóa.'
            ];
        }

        // 3. Keyword in Slug
        if ( $rules['keyword_in_slug']['enabled'] ) {
            $status = 'red';
            $keyword_slug = sanitize_title( $keyword );
            if ( $keyword_slug && strpos( $slug, $keyword_slug ) !== false ) {
                $status = 'green';
            }
            $results['keyword_in_slug'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? 'Từ khóa xuất hiện trong URL (Slug).' : 'Từ khóa chưa xuất hiện trong URL.',
                'weight'         => $rules['keyword_in_slug']['weight'],
                'recommendation' => 'Đường dẫn (slug) nên ngắn gọn, chứa từ khóa chính không dấu ngăn cách bởi dấu gạch ngang (-).',
                'details'        => 'Đường dẫn hiện tại: /' . ( $slug ?: 'chua-co-slug' ),
                'action_tip'     => $status === 'green' ? 'Đường dẫn đã chứa từ khóa chính.' : 'Bấm nút "✨ AI Sinh Slug" để tự động tạo đường dẫn tối ưu.'
            ];
        }

        // 4. Content Length
        if ( $rules['content_length']['enabled'] ) {
            $rule = $rules['content_length'];
            $status = 'red';
            if ( $word_count >= $rule['green_min'] ) {
                $status = 'green';
            } elseif ( $word_count >= $rule['orange_min'] ) {
                $status = 'orange';
            }
            $results['content_length'] = [
                'status'         => $status,
                'message'        => "Độ dài nội dung: {$word_count} từ.",
                'weight'         => $rule['weight'],
                'recommendation' => "Nội dung bài viết y khoa / dịch vụ thẩm mỹ nên đạt tối thiểu {$rule['green_min']} từ để đủ chiều sâu thông tin.",
                'details'        => "Tổng số từ đếm được: {$word_count} từ.",
                'action_tip'     => $word_count >= $rule['green_min'] ? 'Độ dài nội dung đã đạt tiêu chuẩn!' : 'Bổ sung thêm quy trình, bảng giá hoặc câu hỏi thường gặp.'
            ];
        }

        // 5. Title Length
        if ( $rules['title_length']['enabled'] ) {
            $rule   = $rules['title_length'];
            $len    = $this->strlen( $title );
            $status = 'red';
            if ( $len >= $rule['green_min'] && $len <= $rule['green_max'] ) {
                $status = 'green';
            } elseif ( $len >= $rule['orange_min'] && $len <= $rule['orange_max'] ) {
                $status = 'orange';
            }
            $results['title_length'] = [
                'status'         => $status,
                'message'        => "Độ dài tiêu đề SEO: {$len} ký tự.",
                'weight'         => $rule['weight'],
                'recommendation' => "Độ dài tiêu đề SEO lý tưởng trên Google là từ {$rule['green_min']} đến {$rule['green_max']} ký tự để tránh bị cắt góc (...).",
                'details'        => "Tiêu đề hiện tại dài {$len} ký tự.",
                'action_tip'     => $len < $rule['green_min'] ? 'Tiêu đề hơi ngắn, bổ sung tên thương hiệu Shynh Super Luxury.' : ( $len > $rule['green_max'] ? 'Tiêu đề quá dài, hãy rút gọn bớt các từ thừa.' : 'Độ dài tiêu đề hoàn hảo!' )
            ];
        }

        // 6. Meta Desc Length
        if ( $rules['meta_desc_length']['enabled'] ) {
            $rule   = $rules['meta_desc_length'];
            $len    = $this->strlen( $desc );
            $status = 'red';
            if ( $len >= $rule['green_min'] && $len <= $rule['green_max'] ) {
                $status = 'green';
            } elseif ( $len >= $rule['orange_min'] && $len <= $rule['orange_max'] ) {
                $status = 'orange';
            }
            $results['meta_desc_length'] = [
                'status'         => $status,
                'message'        => "Độ dài mô tả meta: {$len} ký tự.",
                'weight'         => $rule['weight'],
                'recommendation' => "Meta Description nên có độ dài từ {$rule['green_min']} đến {$rule['green_max']} ký tự để hiển thị vừa đủ 2 dòng trên SERP.",
                'details'        => "Đoạn mô tả hiện tại dài {$len} ký tự.",
                'action_tip'     => $len < $rule['green_min'] ? 'Mô tả hơi ngắn, chèn thêm Lời kêu gọi hành động (CTA).' : ( $len > $rule['green_max'] ? 'Mô tả quá dài, cắt bớt để không bị đứt câu.' : 'Độ dài mô tả hoàn hảo!' )
            ];
        }

        // 7. Keyword in headings from rendered/core/custom blocks.
        if ( isset( $rules['keyword_in_headings'] ) && $rules['keyword_in_headings']['enabled'] ) {
            $status = 'red';
            $matched_heading = '';
            if ( $keyword ) {
                foreach ( $context['headings'] as $heading ) {
                    $heading_text = isset( $heading['text'] ) ? $heading['text'] : '';
                    if ( $this->strpos( $this->strtolower( wp_strip_all_tags( $heading_text ) ), $keyword_lower ) !== false ) {
                        $status = 'green';
                        $matched_heading = wp_strip_all_tags( $heading_text );
                        break;
                    }
                }
            }
            $details_headings = $status === 'green'
                ? "Heading phát hiện chứa từ khóa:\n\"" . $matched_heading . "\""
                : "Số lượng thẻ Heading quét được: " . count( $context['headings'] ) . " thẻ (Chưa có thẻ chứa từ khóa).";

            $results['keyword_in_headings'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? 'Từ khóa xuất hiện trong heading của nội dung.' : 'Từ khóa chưa xuất hiện trong heading của nội dung.',
                'weight'         => $rules['keyword_in_headings']['weight'],
                'recommendation' => 'Từ khóa chính nên lồng ghép vào ít nhất một thẻ H2 hoặc H3 để nhấn mạnh chủ đề bài viết với Googlebot.',
                'details'        => $details_headings,
                'action_tip'     => $status === 'green' ? 'Heading đã chứa từ khóa chính!' : 'Chèn từ khóa chính vào tiêu đề phụ (H2/H3) trong bài.'
            ];
        }

        // 8. Keyword in Intro
        if ( isset( $rules['keyword_in_intro'] ) && $rules['keyword_in_intro']['enabled'] ) {
            $rule   = $rules['keyword_in_intro'];
            $status = 'red';
            if ( $keyword ) {
                $intro_100 = $this->strtolower( wp_trim_words( $content_clean, $rule['green_max'] ?? 100, '' ) );
                $intro_200 = $this->strtolower( wp_trim_words( $content_clean, $rule['orange_max'] ?? 200, '' ) );
                
                if ( $this->strpos( $intro_100, $keyword_lower ) !== false ) {
                    $status = 'green';
                } elseif ( $this->strpos( $intro_200, $keyword_lower ) !== false ) {
                    $status = 'orange';
                }
            }
            $intro_snippet = wp_trim_words( $content_clean, 35, '...' );
            $details_intro = $status === 'green'
                ? "Trích đoạn mở đầu 100 từ:\n\"" . $intro_snippet . "\""
                : "Đoạn mở đầu hiện tại quét được:\n\"" . ( $intro_snippet ?: 'Chưa có nội dung đoạn mở đầu' ) . "\"";

            $results['keyword_in_intro'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? 'Từ khóa xuất hiện trong đoạn mở đầu.' : 'Từ khóa nên xuất hiện trong đoạn mở đầu.',
                'weight'         => $rule['weight'],
                'recommendation' => 'Từ khóa chính nên xuất hiện ngay ở 100 từ đầu tiên (đoạn Sapô mở đầu) để AI Search và người đọc hiểu ngay giải pháp.',
                'details'        => $details_intro,
                'action_tip'     => $status === 'green' ? 'Đoạn mở đầu đạt chuẩn!' : 'Đưa từ khóa chính vào ngay câu đầu tiên hoặc câu thứ hai.'
            ];
        }

        // 9. Image Alt
        if ( isset( $rules['image_alt'] ) && $rules['image_alt']['enabled'] ) {
            $status = 'red';
            $msg    = 'Chưa có ảnh nào chứa thuộc tính alt chứa từ khóa.';
            $alt_list = [];
            if ( $keyword ) {
                if ( ! empty( $context['images'] ) ) {
                    $has_alt_keyword = false;
                    foreach ( $context['images'] as $image ) {
                        $alt_text = isset( $image['alt'] ) ? trim( $image['alt'] ) : '';
                        if ( '' !== $alt_text ) {
                            $alt_list[] = $alt_text;
                        }
                        if ( $this->strpos( $this->strtolower( $alt_text ), $keyword_lower ) !== false ) {
                            $has_alt_keyword = true;
                        }
                    }
                    if ( $has_alt_keyword ) {
                        $status = 'green';
                        $msg    = 'Tuyệt vời, có ảnh chứa alt text liên quan đến từ khóa.';
                    } else {
                        $status = 'orange';
                        $msg    = 'Có ảnh nhưng chưa ảnh nào có alt text chứa từ khóa.';
                    }
                } else {
                    $msg = 'Bài viết không có ảnh hoặc ảnh chưa có thẻ alt.';
                }
            }
            $details_alt = ! empty( $alt_list )
                ? "Thẻ Alt ảnh phát hiện được:\n- " . implode( "\n- ", array_slice( array_unique( $alt_list ), 0, 4 ) )
                : "Tổng số ảnh phát hiện: " . count( $context['images'] ) . " ảnh (Chưa có thẻ Alt Text).";

            $results['image_alt'] = [
                'status'         => $status,
                'message'        => $msg,
                'weight'         => $rules['image_alt']['weight'],
                'recommendation' => 'Mỗi hình ảnh minh họa cần đi kèm văn bản Alt (Mô tả ảnh) chứa từ khóa để phục vụ Google Image Search.',
                'details'        => $details_alt,
                'action_tip'     => $status === 'green' ? 'Ảnh đã có alt text chuẩn!' : 'Vào trình soạn thảo bấm vào ảnh và bổ sung Alt Text chứa từ khóa.'
            ];
        }

        // 10. Keyword Density
        if ( isset( $rules['keyword_density'] ) && $rules['keyword_density']['enabled'] ) {
            $rule   = $rules['keyword_density'];
            $status = 'red';
            $msg    = 'Từ khóa xuất hiện 0 lần. Mật độ: 0%.';
            if ( $keyword && $word_count > 0 ) {
                $kw_count = $this->substr_count( $this->strtolower( $content_clean ), $keyword_lower );
                $density  = round( ( $kw_count * $this->word_count( $keyword ) / $word_count ) * 100, 2 );
                
                if ( $density >= $rule['green_min'] && $density <= $rule['green_max'] ) {
                    $status = 'green';
                } elseif ( $density >= $rule['orange_min'] && $density <= $rule['orange_max'] ) {
                    $status = 'orange';
                }
                $msg = sprintf( 'Từ khóa xuất hiện %d lần. Mật độ: %s%%.', $kw_count, $density );
            }
            $results['keyword_density'] = [
                'status'         => $status,
                'message'        => $msg,
                'weight'         => $rule['weight'],
                'recommendation' => "Mật độ từ khóa lý tưởng là từ {$rule['green_min']}% đến {$rule['green_max']}%. Tránh lặp từ khóa quá dày đặc gây spam.",
                'details'        => $msg,
                'action_tip'     => $status === 'green' ? 'Mật độ từ khóa hoàn hảo!' : 'Điều chỉnh tần suất từ khóa lặp lại trong nội dung.'
            ];
        }

        // 11. Internal Links
        if ( isset( $rules['internal_links'] ) && $rules['internal_links']['enabled'] ) {
            $rule   = $rules['internal_links'];
            $status = 'red';
            $internal_links_list = isset( $context['internal_links'] ) && is_array( $context['internal_links'] )
                ? array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                static function ( $link ) {
                                    if ( is_array( $link ) ) {
                                        return isset( $link['href'] ) ? esc_url_raw( $link['href'] ) : '';
                                    }

                                    return is_scalar( $link ) ? esc_url_raw( (string) $link ) : '';
                                },
                                $context['internal_links']
                            )
                        )
                    )
                )
                : [];
            $internal_count = count( $internal_links_list );
            
            if ( $internal_count >= $rule['green_min'] ) {
                $status = 'green';
            } elseif ( $internal_count >= $rule['orange_min'] ) {
                $status = 'orange';
            }
            
            $details_links = ! empty( $internal_links_list )
                ? "Danh sách URL liên kết nội bộ quét được:\n- " . implode( "\n- ", array_slice( $internal_links_list, 0, 5 ) )
                : "Không tìm thấy liên kết nội bộ nào trong bài.";

            $results['internal_links'] = [
                'status'         => $status,
                'message'        => sprintf( 'Tìm thấy %d liên kết nội bộ.', $internal_count ),
                'weight'         => $rule['weight'],
                'recommendation' => "Bài viết nên chứa tối thiểu {$rule['green_min']} liên kết nội bộ chỉ tới các dịch vụ / bài viết liên quan.",
                'details'        => $details_links,
                'action_tip'     => $internal_count >= $rule['green_min'] ? 'Số lượng liên kết nội bộ đạt chuẩn!' : 'Chèn thêm liên kết giới thiệu các dịch vụ liên quan.'
            ];
        }

        // 12. Bold Internal Links
        if ( isset( $rules['bold_internal_links'] ) && $rules['bold_internal_links']['enabled'] ) {
            $rule   = $rules['bold_internal_links'];
            $status = 'red';
            $bold_links_list = isset( $context['bold_internal_links'] ) && is_array( $context['bold_internal_links'] )
                ? array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                static function ( $link ) {
                                    if ( is_array( $link ) ) {
                                        return isset( $link['href'] ) ? esc_url_raw( $link['href'] ) : '';
                                    }

                                    return is_scalar( $link ) ? esc_url_raw( (string) $link ) : '';
                                },
                                $context['bold_internal_links']
                            )
                        )
                    )
                )
                : [];
            $bold_internal_count = count( $bold_links_list );

            if ( $bold_internal_count >= $rule['green_min'] ) {
                $status = 'green';
            } elseif ( $bold_internal_count >= $rule['orange_min'] ) {
                $status = 'orange';
            }

            $details_bold = ! empty( $bold_links_list )
                ? "Liên kết nội bộ in đậm (Bold) phát hiện:\n- " . implode( "\n- ", array_slice( $bold_links_list, 0, 5 ) )
                : "Chưa có liên kết nội bộ nào được in đậm trong bài.";

            $results['bold_internal_links'] = [
                'status'         => $status,
                'message'        => sprintf( 'Tìm thấy %d liên kết nội bộ được bold/strong.', $bold_internal_count ),
                'weight'         => $rule['weight'],
                'recommendation' => "In đậm (Bold) thẻ văn bản chứa liên kết nội bộ giúp chuyển tải tín hiệu ưu tiên nổi bật cho người dùng & Googlebot.",
                'details'        => $details_bold,
                'action_tip'     => $bold_internal_count >= $rule['green_min'] ? 'Liên kết in đậm đạt chuẩn!' : 'Bôi đen từ khóa chèn link và nhấn Ctrl+B để in đậm.'
            ];
        }

        // 13. Previously Used Keyword (Keyword Cannibalization)
        if ( isset( $rules['previously_used_keyword'] ) && $rules['previously_used_keyword']['enabled'] ) {
            $status = 'green';
            $msg    = 'Tuyệt vời! Bạn chưa từng sử dụng Focus Keyword này trước đây.';
            if ( $keyword ) {
                $used_count = class_exists( 'Shynh_SEO_Cannibalization' )
                    ? Shynh_SEO_Cannibalization::count_keyword_collisions( $keyword, $object_id, $object_type )
                    : 0;
                if ( $used_count > 0 ) {
                    $status = 'red';
                    $msg    = sprintf( 'CẢNH BÁO: Từ khóa này đã được sử dụng ở %d bài viết khác (Keyword Cannibalization).', $used_count );
                }
            } else {
                $status = 'orange';
                $msg    = 'Chưa có Focus Keyword để kiểm tra độ trùng lặp.';
            }
            $results['previously_used_keyword'] = [
                'status'         => $status,
                'message'        => $msg,
                'weight'         => $rules['previously_used_keyword']['weight'],
                'recommendation' => 'Mỗi bài viết/danh mục chỉ nên nhắm một Focus Keyword duy nhất để tránh xung đột từ khóa trên Google.',
                'details'        => $msg,
                'action_tip'     => $status === 'green' ? 'Từ khóa hoàn toàn duy nhất!' : 'Đổi từ khóa chính mới hoặc gộp bài viết trùng lặp lại.'
            ];
        }

        // 14. Readability (Tính dễ đọc)
        if ( isset( $rules['readability'] ) && $rules['readability']['enabled'] ) {
            $rule   = $rules['readability'];
            $status = 'green';
            
            $sentences = preg_split('/[.?!]+/', $content_clean, -1, PREG_SPLIT_NO_EMPTY);
            $total_sentences = count($sentences);
            $long_sentences = 0;
            
            foreach ($sentences as $sentence) {
                if (str_word_count($sentence) > 20) { // 20 words is often considered a long sentence
                    $long_sentences++;
                }
            }
            
            $long_sentence_percentage = $total_sentences > 0 ? round(($long_sentences / $total_sentences) * 100) : 0;
            
            if ($long_sentence_percentage > 25) {
                $status = 'orange';
                $msg = sprintf( '%d%% số câu chứa hơn 20 từ, mức tối đa khuyên dùng là 25%%. Hãy thử tách thành các câu ngắn hơn.', $long_sentence_percentage );
            } else {
                $msg = 'Độ dài các câu rất tuyệt vời.';
            }
            
            $results['readability'] = [
                'status'         => $status,
                'message'        => $msg,
                'weight'         => $rule['weight'],
                'recommendation' => 'Để tối ưu trải nghiệm đọc trên điện thoại, các câu văn ngắn dưới 20 từ giúp giữ chân người đọc lâu hơn.',
                'details'        => sprintf( '%d%% số câu vượt quá 20 từ.', $long_sentence_percentage ),
                'action_tip'     => $status === 'green' ? 'Độ dài câu tuyệt vời!' : 'Ngắt các câu quá dài bằng dấu chấm hoặc dấu phẩy.'
            ];
        }
        $score    = $this->calculate_score( $results );
        $response = [
            'results' => $results,
            'score'   => $score,
            'color'   => $score >= 70 ? 'green' : ( $score >= 40 ? 'orange' : 'red' ),
            '_index'  => [
                'internal_links'      => $context['internal_link_index'],
                'bold_internal_count' => count( $context['bold_internal_links'] ),
                'heading_count'       => count( $context['headings'] ),
            ],
            '_context' => [
                'headings'            => $context['headings'],
                'internal_links'      => $context['internal_links'],
                'bold_internal_links' => $context['bold_internal_links'],
            ],
        ];

        if ( $this->should_run_geo_aeo( $object_type, $object_id, $object_subtype, $geo_aeo_mode_override ) ) {
            $geo_aeo = $this->analyze_geo_aeo( $object_type, $object_id, $title, $desc, $content_clean, $context );
            $response['geo_aeo'] = $geo_aeo;
            $response['overall_ai_search_score'] = round( ( $score * 0.6 ) + ( $geo_aeo['score'] * 0.4 ) );
        }

        return $response;
    }

    public function persist_post_analysis( $post_id, $analysis ) {
        if ( ! $post_id || empty( $analysis ) || ! is_array( $analysis ) ) {
            return;
        }

        if ( isset( $analysis['score'] ) ) {
            update_post_meta( $post_id, '_shynh_seo_score', (int) $analysis['score'] );

            // Persist a compact list of SEO fixes (non-green checks + how to fix)
            // so the Report Dashboard "Cần Sửa" tab reads them without re-analysis.
            update_post_meta( $post_id, '_shynh_seo_seo_issues', self::compact_fixes( $analysis['results'] ?? [] ) );
        }

        if ( isset( $analysis['geo_aeo']['score'] ) ) {
            update_post_meta( $post_id, '_shynh_seo_geo_aeo_score', (int) $analysis['geo_aeo']['score'] );

            // Persist a compact list of GEO/AEO issues (red/orange checks) so the
            // Report Dashboard can show "what to fix" without re-running analysis.
            update_post_meta( $post_id, '_shynh_seo_geo_aeo_issues', self::compact_issues( $analysis['geo_aeo']['results'] ?? [] ) );
        } else {
            delete_post_meta( $post_id, '_shynh_seo_geo_aeo_score' );
            delete_post_meta( $post_id, '_shynh_seo_geo_aeo_issues' );
        }

        if ( isset( $analysis['overall_ai_search_score'] ) ) {
            update_post_meta( $post_id, '_shynh_seo_ai_search_score', (int) $analysis['overall_ai_search_score'] );
        } else {
            delete_post_meta( $post_id, '_shynh_seo_ai_search_score' );
        }

        $index = isset( $analysis['_index'] ) && is_array( $analysis['_index'] ) ? $analysis['_index'] : [];
        $context = isset( $analysis['_context'] ) && is_array( $analysis['_context'] ) ? $analysis['_context'] : [];

        $internal_links = isset( $index['internal_links'] ) && is_array( $index['internal_links'] )
            ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $index['internal_links'] ) ) ) )
            : [];

        update_post_meta( $post_id, '_shynh_seo_internal_links', $internal_links );
        update_post_meta( $post_id, '_shynh_seo_internal_link_index', "\n" . implode( "\n", $internal_links ) . "\n" );
        update_post_meta( $post_id, '_shynh_seo_bold_internal_links', isset( $index['bold_internal_count'] ) ? absint( $index['bold_internal_count'] ) : 0 );
        update_post_meta( $post_id, '_shynh_seo_heading_count', isset( $index['heading_count'] ) ? absint( $index['heading_count'] ) : 0 );

        if ( isset( $context['headings'] ) && is_array( $context['headings'] ) ) {
            $heading_summary = [];
            foreach ( $context['headings'] as $heading ) {
                $heading_summary[] = [
                    'level' => isset( $heading['level'] ) ? absint( $heading['level'] ) : 0,
                    'text'  => isset( $heading['text'] ) ? sanitize_text_field( $heading['text'] ) : '',
                ];
            }
            update_post_meta( $post_id, '_shynh_seo_heading_summary', $heading_summary );
        }
    }

    public function persist_term_analysis( $term_id, $analysis ) {
        if ( ! $term_id || empty( $analysis ) || ! is_array( $analysis ) ) {
            return;
        }

        if ( isset( $analysis['score'] ) ) {
            update_term_meta( $term_id, '_shynh_seo_score', (int) $analysis['score'] );
        }

        if ( isset( $analysis['geo_aeo']['score'] ) ) {
            update_term_meta( $term_id, '_shynh_seo_geo_aeo_score', (int) $analysis['geo_aeo']['score'] );
            update_term_meta( $term_id, '_shynh_seo_geo_aeo_issues', self::compact_issues( $analysis['geo_aeo']['results'] ?? [] ) );
        } else {
            delete_term_meta( $term_id, '_shynh_seo_geo_aeo_score' );
            delete_term_meta( $term_id, '_shynh_seo_geo_aeo_issues' );
        }

        if ( isset( $analysis['overall_ai_search_score'] ) ) {
            update_term_meta( $term_id, '_shynh_seo_ai_search_score', (int) $analysis['overall_ai_search_score'] );
        } else {
            delete_term_meta( $term_id, '_shynh_seo_ai_search_score' );
        }
    }

    /**
     * Reduce a GEO/AEO results array to a compact list of non-green checks
     * ({key,status,message}) for fast reporting. Capped to keep meta small.
     *
     * @param array $results
     * @return array
     */
    private static function compact_issues( $results ) {
        if ( ! is_array( $results ) ) {
            return [];
        }
        $out = [];
        foreach ( $results as $key => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $status = isset( $item['status'] ) ? $item['status'] : '';
            if ( 'green' === $status || '' === $status ) {
                continue;
            }
            $out[] = [
                'key'     => is_string( $key ) ? $key : (string) $key,
                'status'  => $status,
                'message' => isset( $item['message'] ) ? wp_strip_all_tags( (string) $item['message'] ) : '',
            ];
            if ( count( $out ) >= 20 ) {
                break;
            }
        }
        return $out;
    }

    /**
     * Reduce SEO check results to a compact, actionable fix list (non-green),
     * keeping the recommendation + action tip + impact weight for reporting.
     *
     * @param array $results
     * @return array
     */
    private static function compact_fixes( $results ) {
        if ( ! is_array( $results ) ) {
            return [];
        }
        $out = [];
        foreach ( $results as $key => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $status = isset( $item['status'] ) ? $item['status'] : '';
            if ( 'green' === $status || '' === $status ) {
                continue;
            }
            $out[] = [
                'key'        => is_string( $key ) ? $key : (string) $key,
                'status'     => $status,
                'weight'     => isset( $item['weight'] ) ? (int) $item['weight'] : 0,
                'message'    => isset( $item['message'] ) ? wp_strip_all_tags( (string) $item['message'] ) : '',
                'action_tip' => isset( $item['action_tip'] ) ? wp_strip_all_tags( (string) $item['action_tip'] ) : '',
            ];
        }
        // Heaviest impact first.
        usort( $out, function ( $a, $b ) { return $b['weight'] <=> $a['weight']; } );
        return array_slice( $out, 0, 15 );
    }

    private function should_run_geo_aeo( $object_type, $object_id, $object_subtype, $geo_aeo_mode_override = null ) {
        if ( ! class_exists( 'Shynh_SEO_Core' ) ) {
            return false;
        }

        $mode = null !== $geo_aeo_mode_override
            ? $geo_aeo_mode_override
            : $this->get_object_meta( $object_type, $object_id, '_shynh_seo_geo_aeo_mode' );

        if ( 'on' === $mode ) {
            return true;
        }

        if ( 'off' === $mode ) {
            return false;
        }

        if ( 'term' === $object_type ) {
            return Shynh_SEO_Core::taxonomy_supports_geo_aeo( $object_subtype );
        }

        return Shynh_SEO_Core::post_type_supports_geo_aeo( $object_subtype );
    }

    private function analyze_geo_aeo( $object_type, $object_id, $title, $desc, $content_clean, $context ) {
        $settings = Shynh_SEO_Core::get_settings();
        $results  = [];
        $combined_lower = $this->strtolower( trim( $title . ' ' . $desc . ' ' . $content_clean ) );

        if ( ! empty( $settings['geo_aeo_require_quick_answer'] ) ) {
            $quick_answer = $this->get_quick_answer_status( $title, $desc, $content_clean, $context );
            $snippet = ! empty( $quick_answer['snippet'] ) ? trim( $quick_answer['snippet'] ) : '';
            $details_qa = ! empty( $snippet )
                ? "Trích đoạn Quick Answer quét được:\n\"" . $snippet . "\""
                : "Chưa phát hiện câu trả lời ngắn 2-3 câu gần đầu bài.";

            $results['geo_aeo_quick_answer'] = [
                'status'         => $quick_answer['status'],
                'message'        => $this->geo_aeo_message( $quick_answer['message_key'] ),
                'weight'         => 15,
                'recommendation' => 'Các công cụ tìm kiếm AI (Perplexity, ChatGPT Search, Google SGE) ưu tiên trích xuất câu trả lời trực tiếp ngắn gọn (2-3 câu) nằm gần đầu bài.',
                'details'        => $details_qa,
                'action_tip'     => $quick_answer['status'] === 'green' ? 'Quick Answer đã sẵn sàng cho AI Search!' : 'Bấm nút "✨ AI Sinh Quick Answer" để AI tạo đoạn tóm tắt 2-3 câu.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_faq'] ) ) {
            $faq_questions = $this->get_faq_items_data( $object_type, $object_id, $content_clean, $context['headings'] );
            $faq_count = count( $faq_questions );
            $details_faq = ! empty( $faq_questions )
                ? "Các câu hỏi FAQ phát hiện được (" . $faq_count . " câu):\n- " . implode( "\n- ", array_slice( $faq_questions, 0, 5 ) )
                : "Chưa phát hiện câu hỏi FAQ nào dưới dạng H3 hoặc Schema FAQ.";

            $results['geo_aeo_faq'] = [
                'status'         => $faq_count >= 4 ? 'green' : ( $faq_count >= 2 ? 'orange' : 'red' ),
                'message'        => sprintf( $this->geo_aeo_message( 'faq_count' ), $faq_count ),
                'weight'         => 15,
                'recommendation' => 'Định dạng câu hỏi thường gặp (FAQ) là tín hiệu quan trọng giúp câu trả lời được chọn làm Featured Snippet trên Google.',
                'details'        => $details_faq,
                'action_tip'     => $faq_count >= 4 ? 'Số lượng FAQ rất tốt!' : 'Bổ sung thêm 2-4 câu hỏi thắc mắc phổ biến của khách hàng dưới dạng H3.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_entity_signals'] ) ) {
            $detected_entities = $this->get_detected_entities( $settings, $combined_lower );
            $entity_count = count( $detected_entities );
            $details_entity = ! empty( $detected_entities )
                ? "Tín hiệu Thực thể / Brand phát hiện được:\n- " . implode( ", ", $detected_entities )
                : "Chưa tìm thấy từ khóa thương hiệu / thực thể trong bài.";

            $results['geo_aeo_entities'] = [
                'status'         => $entity_count >= 3 ? 'green' : ( $entity_count >= 1 ? 'orange' : 'red' ),
                'message'        => sprintf( $this->geo_aeo_message( 'entity_count' ), $entity_count ),
                'weight'         => 15,
                'recommendation' => 'Nội dung chứa tên thương hiệu, địa chỉ, công nghệ chính chủ giúp củng cố Thực thể (Entity) Shynh Super Luxury trong Google Knowledge Graph.',
                'details'        => $details_entity,
                'action_tip'     => $entity_count >= 3 ? 'Tín hiệu Entity đạt chuẩn!' : 'Nhắc tới tên thương hiệu Shynh Super Luxury hoặc công nghệ làm đẹp cao cấp trong bài.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_schema'] ) ) {
            $schema_ready = $this->has_schema_support( $object_type, $object_id, $settings );
            $results['geo_aeo_schema'] = [
                'status'         => $schema_ready ? 'green' : 'red',
                'message'        => $schema_ready ? $this->geo_aeo_message( 'schema_ok' ) : $this->geo_aeo_message( 'schema_missing' ),
                'weight'         => 15,
                'recommendation' => 'Dữ liệu có cấu trúc Schema (JSON-LD) giúp Googlebot và AI trích xuất thông tin dịch vụ, giá cả, và đánh giá chính xác.',
                'details'        => $schema_ready ? 'Schema JSON-LD đã được bật và cấu hình tự động.' : 'Chưa bật cấu hình Schema.',
                'action_tip'     => $schema_ready ? 'Schema đã kích hoạt!' : 'Vào cài đặt Shynh SEO bật Schema cho loại bài viết này.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_search_eligibility'] ) ) {
            $eligibility = $this->get_search_eligibility_status( $object_type, $object_id, $desc );
            $results['geo_aeo_search_eligibility'] = [
                'status'         => $eligibility['status'],
                'message'        => $this->geo_aeo_message( $eligibility['message_key'] ),
                'weight'         => 10,
                'recommendation' => 'Đảm bảo bài viết không bị chặn Index (noindex) và có đủ thông tin để hiển thị Rich Snippet trên Google.',
                'details'        => $eligibility['status'] === 'green' ? 'Bài viết đủ điều kiện Index và hiển thị kết quả.' : 'Cần kiểm tra trạng thái Index.',
                'action_tip'     => $eligibility['status'] === 'green' ? 'Bài viết hoàn toàn đủ điều kiện Index!' : 'Bỏ chọn Noindex trong cài đặt SEO.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_experience_signals'] ) ) {
            $experience_count = $this->count_experience_signals( $combined_lower );
            $results['geo_aeo_experience_signals'] = [
                'status'         => $experience_count >= 3 ? 'green' : ( $experience_count >= 1 ? 'orange' : 'red' ),
                'message'        => sprintf( $this->geo_aeo_message( 'experience_count' ), $experience_count ),
                'weight'         => 10,
                'recommendation' => 'Theo tiêu chuẩn E-E-A-T của Google, bài viết cần chứa tín hiệu Trải nghiệm thực tế (Experience) như hình ảnh ca lâm sàng, kết quả trị liệu thực tế.',
                'details'        => sprintf( 'Tìm thấy %d tín hiệu trải nghiệm/chuyên môn thực tế.', $experience_count ),
                'action_tip'     => $experience_count >= 3 ? 'Tín hiệu E-E-A-T trải nghiệm rất tốt!' : 'Bổ sung cảm nhận khách hàng hoặc quy trình thăm khám thực tế.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_intent_coverage'] ) ) {
            $intent_count = $this->count_intent_facets( $combined_lower, $context['headings'] );
            $results['geo_aeo_intent_coverage'] = [
                'status'         => $intent_count >= 3 ? 'green' : ( $intent_count >= 2 ? 'orange' : 'red' ),
                'message'        => sprintf( $this->geo_aeo_message( 'intent_count' ), $intent_count ),
                'weight'         => 10,
                'recommendation' => 'Nội dung nên giải quyết đa dạng nhu cầu tìm kiếm của người dùng (Ý niệm tìm kiếm: Định nghĩa, Ưu điểm, Bảng giá, Quy trình thực hiện).',
                'details'        => sprintf( 'Nội dung đã phủ %d nhóm ý định tìm kiếm.', $intent_count ),
                'action_tip'     => $intent_count >= 3 ? 'Ý định tìm kiếm đã được phủ đầy đủ!' : 'Bổ sung thêm các phần: Bảng giá, Quy trình thực hiện, Đối tượng phù hợp.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_media_support'] ) ) {
            $media = $this->get_media_support_status( $context, $content_clean );
            $results['geo_aeo_media_support'] = [
                'status'         => $media['status'],
                'message'        => sprintf( $this->geo_aeo_message( $media['message_key'] ), $media['count'] ),
                'weight'         => 10,
                'recommendation' => 'Hình ảnh sắc nét và video minh họa giúp tăng thời gian on-site và được AI Search ưu tiên hiển thị trong câu trả lời trực quan.',
                'details'        => sprintf( 'Đã phát hiện %d hình ảnh / video minh họa trong bài.', $media['count'] ),
                'action_tip'     => $media['count'] >= 2 ? 'Phương tiện minh họa đạt tiêu chuẩn!' : 'Chèn thêm hình ảnh trước/sau điều trị hoặc video giới thiệu.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_schema_visibility'] ) ) {
            $schema_visibility = $this->get_schema_visibility_status( $object_type, $object_id, $content_clean, $context['headings'] );
            $results['geo_aeo_schema_visibility'] = [
                'status'         => $schema_visibility['status'],
                'message'        => $this->geo_aeo_message( $schema_visibility['message_key'] ),
                'weight'         => 10,
                'recommendation' => 'Nội dung khai báo trong Schema JSON-LD (ví dụ FAQ, MedicalProcedure) phải khớp chính xác với văn bản hiển thị cho người đọc.',
                'details'        => $schema_visibility['status'] === 'green' ? 'Nội dung Schema hoàn toàn khớp với văn bản hiển thị.' : 'Cần đồng bộ văn bản hiển thị và Schema.',
                'action_tip'     => $schema_visibility['status'] === 'green' ? 'Schema khớp với giao diện thực tế!' : 'Đảm bảo các câu hỏi trong Schema xuất hiện ngoài bài viết.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_trust_signals'] ) ) {
            $trust_count = $this->count_trust_signals( $object_type, $object_id, $settings, $combined_lower, $context );
            $results['geo_aeo_trust_signals'] = [
                'status'         => $trust_count >= 2 ? 'green' : ( $trust_count >= 1 ? 'orange' : 'red' ),
                'message'        => sprintf( $this->geo_aeo_message( 'trust_count' ), $trust_count ),
                'weight'         => 10,
                'recommendation' => 'Bài viết dịch vụ y khoa/thẩm mỹ cần thể hiện tín hiệu Độ tin cậy (Trust): Giấy phép bộ y tế, chứng nhận FDA, cam kết an toàn.',
                'details'        => sprintf( 'Tìm thấy %d tín hiệu uy tín / độ tin cậy.', $trust_count ),
                'action_tip'     => $trust_count >= 2 ? 'Tín hiệu độ tin cậy (Trust) đạt chuẩn!' : 'Bổ sung thông tin giấy phép hoạt động hoặc chứng nhận công nghệ.'
            ];
        }

        if ( ! empty( $settings['brand_ai_audit_enabled'] ) && ! empty( $settings['geo_aeo_require_brand_alignment'] ) ) {
            $brand_alignment = $this->get_brand_alignment_status( $settings, $combined_lower );
            $results['geo_aeo_brand_alignment'] = [
                'status'         => $brand_alignment['status'],
                'message'        => $this->geo_aeo_message( $brand_alignment['message_key'] ),
                'weight'         => 12,
                'recommendation' => 'Noi dung nen bam sat dinh vi, tam nhin, su menh, gia tri cot loi va persona cua Shynh de AI khong viet generic SEO.',
                'details'        => sprintf( 'Phat hien %d tin hieu brand: %s', $brand_alignment['count'], implode( ', ', $brand_alignment['matches'] ) ),
                'action_tip'     => $brand_alignment['status'] === 'green' ? 'Brand fit dang on dinh.' : 'Bo sung tin hieu ve Shynh, ca nhan hoa, minh bach, an toan, chuyen mon va trai nghiem premium.'
            ];
        }

        if ( ! empty( $settings['brand_ai_audit_enabled'] ) && ! empty( $settings['geo_aeo_require_brand_tone'] ) ) {
            $tone_status = $this->get_brand_tone_status( $settings, $combined_lower );
            $results['geo_aeo_brand_tone'] = [
                'status'         => $tone_status['status'],
                'message'        => $this->geo_aeo_message( $tone_status['message_key'] ),
                'weight'         => 10,
                'recommendation' => 'Tone cua Shynh can premium, calm, knowledgeable, warm, trustworthy va refined, tranh hard-sell hoac body shaming.',
                'details'        => sprintf( 'Tone signals: %d. Risky tone signals: %d.', $tone_status['positive_count'], $tone_status['risk_count'] ),
                'action_tip'     => $tone_status['status'] === 'green' ? 'Tone brand phu hop.' : 'Giam ngon ngu giat tit/sale soc, tang giai thich binh tinh va tu van minh bach.'
            ];
        }

        if ( ! empty( $settings['brand_ai_audit_enabled'] ) && ! empty( $settings['geo_aeo_require_brand_proof'] ) ) {
            $proof_status = $this->get_brand_proof_status( $settings, $combined_lower, $context );
            $results['geo_aeo_brand_proof'] = [
                'status'         => $proof_status['status'],
                'message'        => $this->geo_aeo_message( $proof_status['message_key'] ),
                'weight'         => 10,
                'recommendation' => 'Moi claim manh ve cong nghe, bac si, chung nhan, review, ket qua hoac giai thuong can co proof visible hoac tu Knowledge Vault.',
                'details'        => sprintf( 'Proof signals: %d. Strong-claim signals: %d.', $proof_status['proof_count'], $proof_status['claim_count'] ),
                'action_tip'     => $proof_status['status'] === 'green' ? 'Proof support dat yeu cau.' : 'Them nguon, chung chi, thong tin nguoi duyet, link tham khao hoac giam claim thanh ngon ngu than trong.'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_medical_disclaimer'] ) ) {
            $needs_disclaimer = $this->contains_any( $combined_lower, [ 'treatment', 'procedure', 'medical', 'aesthetic', 'skin', 'laser', 'injection', 'surgery', 'therapy', 'doctor', 'clinic', 'điều trị', 'liệu trình', 'thủ thuật', 'y khoa', 'thẩm mỹ', 'làn da', 'tiêm', 'phẫu thuật', 'bác sĩ', 'phòng khám' ] );
            $has_disclaimer = $this->contains_any( $combined_lower, [ 'consult', 'consultation', 'results may vary', 'not medical advice', 'qualified provider', 'licensed professional', 'safety', 'risk', 'aftercare', 'tư vấn', 'tham khảo ý kiến', 'kết quả tùy cơ địa', 'không thay thế tư vấn y khoa', 'bác sĩ chuyên môn', 'an toàn', 'rủi ro', 'chăm sóc sau', 'tái khám' ] );
            $status = ! $needs_disclaimer || $has_disclaimer ? 'green' : 'orange';
            $disclaimer_found = $this->extract_medical_disclaimer_snippet( $combined_lower );
            $details_disclaimer = $status === 'green'
                ? ( $disclaimer_found ? "Phát hiện câu miễn trừ y tế:\n\"" . $disclaimer_found . "\"" : "Đã có câu tuyên bố miễn trừ y tế chuẩn y khoa." )
                : "Chưa có câu tuyên bố miễn trừ trách nhiệm y tế / tư vấn bác sĩ.";

            $results['geo_aeo_medical_disclaimer'] = [
                'status'         => $status,
                'message'        => $status === 'green' ? $this->geo_aeo_message( 'safety_ok' ) : $this->geo_aeo_message( 'safety_missing' ),
                'weight'         => 10,
                'recommendation' => 'Nội dung Y khoa / Thẩm mỹ cần có câu Khuyến cáo Y tế (Medical Disclaimer) để tuân thủ thuật toán YMYL (Your Money Your Life) của Google.',
                'details'        => $details_disclaimer,
                'action_tip'     => $status === 'green' ? 'Miễn trừ y tế đạt chuẩn an toàn!' : 'Chèn câu: "Hiệu quả tùy thuộc cơ địa mỗi người, vui lòng tham khảo tư vấn bác sĩ chuyên khoa."'
            ];
        }

        if ( ! empty( $settings['geo_aeo_require_review_status'] ) ) {
            $reviewed = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_medical_reviewed' );
            $reviewed_by = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_reviewed_by' );
            $is_reviewed = ! empty( $reviewed ) && ! empty( $reviewed_by );
            $results['geo_aeo_review_status'] = [
                'status'         => $is_reviewed ? 'green' : 'orange',
                'message'        => $is_reviewed ? $this->geo_aeo_message( 'review_ok' ) : $this->geo_aeo_message( 'review_missing' ),
                'weight'         => 10,
                'recommendation' => 'Nội dung được thẩm định chuyên môn bởi Bác sĩ y khoa / Chuyên gia thẩm mỹ (Reviewed by Medical Doctor) làm tăng tối đa điểm uy tín E-E-A-T.',
                'details'        => $is_reviewed ? sprintf( 'Đã được thẩm định bởi: %s', $reviewed_by ) : 'Chưa nhập thông tin người kiểm duyệt chuyên môn.',
                'action_tip'     => $is_reviewed ? 'Nội dung đã được kiểm duyệt bởi chuyên gia!' : 'Vào tab Cấu hình SEO chèn tên Bác sĩ / Chuyên gia kiểm duyệt bài viết.'
            ];
        }

        $risky_claims = $this->contains_any( $combined_lower, [ 'guaranteed', 'permanent', 'risk-free', '100% safe', 'no risk', 'instant cure', 'cam kết', 'chắc chắn', 'vĩnh viễn', 'không rủi ro', '100% an toàn', 'khỏi ngay', 'hiệu quả tuyệt đối' ] );
        $results['geo_aeo_claim_safety'] = [
            'status'         => $risky_claims ? 'red' : 'green',
            'message'        => $risky_claims ? $this->geo_aeo_message( 'claims_risky' ) : $this->geo_aeo_message( 'claims_ok' ),
            'weight'         => 20,
            'recommendation' => 'Tránh sử dụng các từ khẳng định tuyệt đối (vĩnh viễn, 100% an toàn, cam kết 100%) dễ vi phạm quy định quảng cáo y tế của Google.',
            'details'        => $risky_claims ? 'Phát hiện từ ngữ cam kết tuyệt đối có rủi ro.' : 'Nội dung an toàn, không phát hiện tuyên bố rủi ro.',
            'action_tip'     => $risky_claims ? 'Thay các từ "vĩnh viễn / 100%" bằng "hiệu quả lâu dài / an toàn chuẩn y khoa".' : 'Nội dung hoàn toàn an toàn!'
        ];

        $score = $this->calculate_score( $results );

        return [
            'results' => $results,
            'score'   => $score,
            'color'   => $score >= (int) ( $settings['geo_aeo_min_score'] ?? 80 ) ? 'green' : ( $score >= 50 ? 'orange' : 'red' ),
        ];
    }

    private function geo_aeo_message( $key ) {
        $is_vi_admin = 0 === strpos( strtolower( get_user_locale() ), 'vi' );

        $messages = [
            'quick_answer_ok'      => [
                'vi' => 'GEO/AEO: Đã phát hiện câu trả lời ngắn, rõ ý gần đầu bài.',
                'en' => 'GEO/AEO: A concise answer-first intro was detected.',
            ],
            'quick_answer_partial' => [
                'vi' => 'GEO/AEO: Mở đầu có hướng trả lời nhanh nhưng nên cụ thể và gọn hơn.',
                'en' => 'GEO/AEO: The intro has an answer-first signal, but it should be clearer and more concise.',
            ],
            'quick_answer_missing' => [
                'vi' => 'GEO/AEO: Nên mở đầu bằng câu trả lời ngắn, trực tiếp cho nhu cầu tìm kiếm chính.',
                'en' => 'GEO/AEO: Start with a short, direct answer to the main search intent.',
            ],
            'faq_count'            => [
                'vi' => 'GEO/AEO: Tìm thấy %d câu hỏi dạng FAQ.',
                'en' => 'GEO/AEO: Found %d FAQ-style questions.',
            ],
            'entity_count'         => [
                'vi' => 'GEO/AEO: Tìm thấy %d tín hiệu entity/brand đã cấu hình.',
                'en' => 'GEO/AEO: Found %d configured/brand entity signals.',
            ],
            'schema_ok'            => [
                'vi' => 'GEO/AEO: Schema đã được cấu hình.',
                'en' => 'GEO/AEO: Schema support is configured.',
            ],
            'schema_missing'       => [
                'vi' => 'GEO/AEO: Cần chọn Schema Type hoặc bật auto schema.',
                'en' => 'GEO/AEO: Configure schema type or enable auto schema.',
            ],
            'search_eligible_ok'   => [
                'vi' => 'GEO/AEO: Nội dung đủ điều kiện index và hiển thị snippet.',
                'en' => 'GEO/AEO: Content is eligible for indexing and snippets.',
            ],
            'search_eligible_desc_missing' => [
                'vi' => 'GEO/AEO: Có thể index/snippet, nhưng nên thêm meta description rõ ràng hơn.',
                'en' => 'GEO/AEO: Index/snippet is allowed, but add a clearer meta description.',
            ],
            'search_eligible_blocked' => [
                'vi' => 'GEO/AEO: Nội dung đang bị noindex hoặc nosnippet nên khó xuất hiện trong AI Search.',
                'en' => 'GEO/AEO: Content is blocked by noindex or nosnippet, limiting AI Search visibility.',
            ],
            'experience_count'     => [
                'vi' => 'GEO/AEO: Tìm thấy %d tín hiệu kinh nghiệm/chuyên môn thực tế.',
                'en' => 'GEO/AEO: Found %d first-hand experience or expertise signals.',
            ],
            'intent_count'         => [
                'vi' => 'GEO/AEO: Nội dung phủ %d nhóm intent hữu ích của người tìm kiếm.',
                'en' => 'GEO/AEO: Content covers %d helpful search-intent facets.',
            ],
            'media_ok'             => [
                'vi' => 'GEO/AEO: Có %d media hỗ trợ nội dung và có tín hiệu mô tả.',
                'en' => 'GEO/AEO: Found %d supporting media item(s) with descriptive signals.',
            ],
            'media_missing_alt'    => [
                'vi' => 'GEO/AEO: Có %d media hỗ trợ nhưng nên bổ sung alt/caption mô tả.',
                'en' => 'GEO/AEO: Found %d supporting media item(s), but add descriptive alt/caption text.',
            ],
            'media_missing'        => [
                'vi' => 'GEO/AEO: Nên thêm hình ảnh hoặc video liên quan để hỗ trợ nội dung.',
                'en' => 'GEO/AEO: Add relevant images or video to support the content.',
            ],
            'schema_visible_ok'    => [
                'vi' => 'GEO/AEO: Schema phù hợp với nội dung đang hiển thị.',
                'en' => 'GEO/AEO: Schema matches visible page content.',
            ],
            'schema_visible_missing' => [
                'vi' => 'GEO/AEO: FAQ schema cần khớp với câu hỏi/câu trả lời hiển thị trên trang.',
                'en' => 'GEO/AEO: FAQ schema should match questions and answers visible on the page.',
            ],
            'trust_count'          => [
                'vi' => 'GEO/AEO: Tìm thấy %d tín hiệu trust/authority.',
                'en' => 'GEO/AEO: Found %d trust or authority signals.',
            ],
            'safety_ok'            => [
                'vi' => 'GEO/AEO: Nội dung an toàn/y khoa thẩm mỹ đã phù hợp hoặc không bắt buộc.',
                'en' => 'GEO/AEO: Medical-aesthetic safety language is present or not required.',
            ],
            'safety_missing'       => [
                'vi' => 'GEO/AEO: Nên thêm thông tin an toàn, rủi ro, tư vấn hoặc chăm sóc sau liệu trình.',
                'en' => 'GEO/AEO: Add safety, risk, consultation, or aftercare language.',
            ],
            'review_ok'            => [
                'vi' => 'GEO/AEO: Trạng thái kiểm duyệt nội dung đã hoàn tất.',
                'en' => 'GEO/AEO: Human review status is complete.',
            ],
            'review_missing'       => [
                'vi' => 'GEO/AEO: Nên nhập người kiểm duyệt và đánh dấu nội dung đã được review.',
                'en' => 'GEO/AEO: Add reviewer name and mark content reviewed.',
            ],
            'claims_risky'         => [
                'vi' => 'GEO/AEO: Phát hiện claim tuyệt đối hoặc có rủi ro.',
                'en' => 'GEO/AEO: Risky absolute claims detected.',
            ],
            'claims_ok'            => [
                'vi' => 'GEO/AEO: Không phát hiện claim tuyệt đối có rủi ro.',
                'en' => 'GEO/AEO: No risky absolute claims detected.',
            ],
            'brand_alignment_ok'   => [
                'vi' => 'Brand Audit: Noi dung bam sat entity, dinh vi va gia tri Shynh.',
                'en' => 'Brand Audit: Content aligns with Shynh entity, positioning, and values.',
            ],
            'brand_alignment_weak' => [
                'vi' => 'Brand Audit: Noi dung con generic, can them tin hieu brand Shynh.',
                'en' => 'Brand Audit: Content is still generic and needs more Shynh brand signals.',
            ],
            'brand_tone_ok'        => [
                'vi' => 'Brand Audit: Tone of voice phu hop voi chat premium, tinh te.',
                'en' => 'Brand Audit: Tone of voice fits the premium and refined brand.',
            ],
            'brand_tone_risky'     => [
                'vi' => 'Brand Audit: Phat hien tone lech brand hoac hard-sell.',
                'en' => 'Brand Audit: Risky off-brand or hard-sell tone detected.',
            ],
            'brand_proof_ok'       => [
                'vi' => 'Brand Audit: Claim co tin hieu proof ho tro.',
                'en' => 'Brand Audit: Claims have supporting proof signals.',
            ],
            'brand_proof_missing'  => [
                'vi' => 'Brand Audit: Claim manh can them proof hoac nguoi duyet.',
                'en' => 'Brand Audit: Strong claims need proof or reviewer support.',
            ],
        ];

        if ( empty( $messages[ $key ] ) ) {
            return '';
        }

        return $messages[ $key ][ $is_vi_admin ? 'vi' : 'en' ];
    }

    private function contains_any( $haystack, $needles ) {
        $haystack = $this->strtolower( $haystack );

        foreach ( (array) $needles as $needle ) {
            if ( false !== $this->strpos( $haystack, $this->strtolower( $needle ) ) ) {
                return true;
            }
        }

        return false;
    }

    private function get_quick_answer_status( $title, $desc, $content_clean, $context ) {
        $label_terms = [ 'quick answer', 'short answer', 'answer', 'in short', 'summary', 'câu trả lời nhanh', 'trả lời nhanh', 'trả lời ngắn', 'tóm tắt', 'tóm lại', 'giải đáp nhanh' ];

        if ( $this->has_heading_match( $context['headings'], $label_terms ) || $this->contains_any( $this->first_words( $content_clean, 180 ), $label_terms ) ) {
            return [
                'status'      => 'green',
                'message_key' => 'quick_answer_ok',
            ];
        }

        $intro = $this->first_words( trim( $desc . ' ' . $content_clean ), 140 );
        $candidate = $this->first_sentence( $intro );

        if ( '' === $candidate ) {
            return [
                'status'      => 'red',
                'message_key' => 'quick_answer_missing',
            ];
        }

        $words = $this->word_count( $candidate );
        $candidate_lower = $this->strtolower( $candidate );
        $title_terms = array_filter( preg_split( '/\s+/u', $this->strtolower( wp_strip_all_tags( $title ) ) ) );
        $specificity = 0;

        foreach ( $title_terms as $term ) {
            if ( $this->strlen( $term ) >= 4 && false !== $this->strpos( $candidate_lower, $term ) ) {
                $specificity++;
                break;
            }
        }

        if ( $this->contains_any( $candidate_lower, [ 'là', 'giúp', 'phù hợp', 'hỗ trợ', 'cải thiện', 'dành cho', 'nên', 'có thể', 'is', 'helps', 'supports', 'improves', 'suitable', 'designed for', 'can' ] ) ) {
            $specificity++;
        }

        if ( $this->contains_any( $candidate_lower, [ 'khách hàng', 'làn da', 'tình trạng', 'liệu trình', 'dịch vụ', 'bác sĩ', 'chuyên viên', 'patient', 'client', 'skin', 'treatment', 'service', 'provider' ] ) ) {
            $specificity++;
        }

        $is_concise = $words >= 14 && $words <= 55;
        $is_too_salesy = $this->contains_any( $candidate_lower, [ 'tuyệt vời nhất', 'số 1', 'duy nhất', 'cam kết', 'chắc chắn', 'hiệu quả tuyệt đối', 'best ever', 'guaranteed', 'perfect', 'ultimate' ] );

        if ( $is_concise && $specificity >= 2 && ! $is_too_salesy ) {
            return [
                'status'      => 'green',
                'message_key' => 'quick_answer_ok',
            ];
        }

        if ( $words >= 10 && $words <= 80 && $specificity >= 1 ) {
            return [
                'status'      => 'orange',
                'message_key' => 'quick_answer_partial',
            ];
        }

        return [
            'status'      => 'red',
            'message_key' => 'quick_answer_missing',
        ];
    }

    private function first_words( $text, $limit ) {
        $words = preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) $text ) ) );
        if ( ! is_array( $words ) ) {
            return '';
        }

        return implode( ' ', array_slice( array_filter( $words ), 0, max( 1, (int) $limit ) ) );
    }

    private function first_sentence( $text ) {
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
        if ( '' === $text ) {
            return '';
        }

        if ( preg_match( '/^(.{40,360}?[.!?。！？])\s/u', $text . ' ', $matches ) ) {
            return trim( $matches[1] );
        }

        return trim( wp_trim_words( $text, 55, '' ) );
    }

    private function get_search_eligibility_status( $object_type, $object_id, $desc ) {
        $robots_index = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_robots_index' );
        $nosnippet    = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_robots_nosnippet' );

        if ( 'noindex' === $robots_index || ! empty( $nosnippet ) ) {
            return [
                'status'      => 'red',
                'message_key' => 'search_eligible_blocked',
            ];
        }

        if ( '' === trim( (string) $desc ) ) {
            return [
                'status'      => 'orange',
                'message_key' => 'search_eligible_desc_missing',
            ];
        }

        return [
            'status'      => 'green',
            'message_key' => 'search_eligible_ok',
        ];
    }

    private function count_experience_signals( $combined_lower ) {
        $groups = [
            [ 'before and after', 'case study', 'real case', 'patient case', 'customer case', 'trước sau', 'ca thực tế', 'khách hàng thực tế', 'hình ảnh trước sau' ],
            [ 'experience', 'expert', 'specialist', 'doctor', 'practitioner', 'kinh nghiệm', 'chuyên gia', 'bác sĩ', 'chuyên viên', 'đội ngũ chuyên môn' ],
            [ 'protocol', 'process', 'method', 'technology', 'clinical', 'quy trình', 'phác đồ', 'công nghệ', 'tiêu chuẩn', 'chuẩn y khoa' ],
            [ 'results may vary', 'measured', 'assessment', 'consultation', 'kết quả tùy cơ địa', 'đánh giá', 'thăm khám', 'tư vấn', 'theo dõi' ],
            [ 'years', 'certified', 'licensed', 'training', 'năm kinh nghiệm', 'chứng chỉ', 'được đào tạo', 'được cấp phép' ],
        ];

        return $this->count_matching_groups( $combined_lower, $groups );
    }

    private function count_intent_facets( $combined_lower, $headings ) {
        $heading_text = '';
        foreach ( (array) $headings as $heading ) {
            $heading_text .= ' ' . ( $heading['text'] ?? '' );
        }
        $haystack = trim( $combined_lower . ' ' . $this->strtolower( $heading_text ) );

        $groups = [
            [ 'what is', 'what are', 'là gì', 'dịch vụ gì', 'công nghệ gì' ],
            [ 'who is', 'suitable', 'candidate', 'phù hợp', 'đối tượng', 'ai nên', 'ai phù hợp' ],
            [ 'how much', 'price', 'cost', 'pricing', 'giá', 'chi phí', 'bao nhiêu', 'bảng giá' ],
            [ 'safe', 'risk', 'side effect', 'an toàn', 'rủi ro', 'tác dụng phụ', 'biến chứng' ],
            [ 'process', 'procedure', 'steps', 'quy trình', 'các bước', 'thực hiện như thế nào' ],
            [ 'aftercare', 'recovery', 'downtime', 'chăm sóc sau', 'phục hồi', 'kiêng gì', 'tái khám' ],
            [ 'result', 'how long', 'timeline', 'kết quả', 'bao lâu', 'duy trì', 'hiệu quả' ],
            [ 'compare', 'vs', 'difference', 'so sánh', 'khác gì', 'nên chọn' ],
        ];

        return $this->count_matching_groups( $haystack, $groups );
    }

    private function get_media_support_status( $context, $content_clean ) {
        $images = isset( $context['images'] ) && is_array( $context['images'] ) ? $context['images'] : [];
        $media_count = count( $images );
        $described_count = 0;

        foreach ( $images as $image ) {
            $alt     = trim( (string) ( $image['alt'] ?? '' ) );
            $caption = trim( (string) ( $image['caption'] ?? '' ) );
            if ( '' !== $alt || '' !== $caption ) {
                $described_count++;
            }
        }

        $html = isset( $context['html'] ) ? (string) $context['html'] : (string) $content_clean;
        if ( $this->contains_any( $html, [ '<video', '<iframe', 'youtube.com', 'youtu.be', 'vimeo.com' ] ) ) {
            $media_count++;
            $described_count++;
        }

        if ( $media_count <= 0 ) {
            return [
                'status'      => 'red',
                'message_key' => 'media_missing',
                'count'       => 0,
            ];
        }

        if ( $described_count > 0 ) {
            return [
                'status'      => 'green',
                'message_key' => 'media_ok',
                'count'       => $media_count,
            ];
        }

        return [
            'status'      => 'orange',
            'message_key' => 'media_missing_alt',
            'count'       => $media_count,
        ];
    }

    private function get_schema_visibility_status( $object_type, $object_id, $content, $headings ) {
        $faq_items = $this->decode_faq_items( $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_faq' ) );

        if ( empty( $faq_items ) ) {
            return [
                'status'      => 'green',
                'message_key' => 'schema_visible_ok',
            ];
        }

        $visible_faq_count = $this->count_visible_questions( $content, $headings );

        if ( $visible_faq_count >= min( count( $faq_items ), 2 ) ) {
            return [
                'status'      => 'green',
                'message_key' => 'schema_visible_ok',
            ];
        }

        return [
            'status'      => 'red',
            'message_key' => 'schema_visible_missing',
        ];
    }

    private function count_trust_signals( $object_type, $object_id, $settings, $combined_lower, $context ) {
        $count = 0;

        if ( ! empty( $settings['org_name'] ) && false !== $this->strpos( $combined_lower, $this->strtolower( $settings['org_name'] ) ) ) {
            $count++;
        }

        if ( $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_reviewed_by' ) ) {
            $count++;
        }

        if ( $this->contains_any( $combined_lower, [ 'address', 'phone', 'hotline', 'contact', 'địa chỉ', 'số điện thoại', 'liên hệ', 'hotline', 'đặt lịch', 'tư vấn' ] ) ) {
            $count++;
        }

        if ( $this->count_external_links( $context ) > 0 ) {
            $count++;
        }

        if ( ! empty( $settings['social_facebook'] ) || ! empty( $settings['social_instagram'] ) || ! empty( $settings['social_youtube'] ) || ! empty( $settings['social_tiktok'] ) ) {
            $count++;
        }

        return $count;
    }

    private function count_matching_groups( $haystack, $groups ) {
        $count = 0;

        foreach ( $groups as $needles ) {
            if ( $this->contains_any( $haystack, $needles ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function count_visible_questions( $content, $headings ) {
        $count = 0;

        foreach ( (array) $headings as $heading ) {
            if ( false !== strpos( (string) ( $heading['text'] ?? '' ), '?' ) ) {
                $count++;
            }
        }

        if ( preg_match_all( '/\?/', $content, $matches ) ) {
            $count = max( $count, count( $matches[0] ) );
        }

        return $count;
    }

    private function count_external_links( $context ) {
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $count = 0;
        $all_links = [];

        if ( isset( $context['links'] ) && is_array( $context['links'] ) ) {
            foreach ( $context['links'] as $link ) {
                if ( is_array( $link ) && ! empty( $link['href'] ) ) {
                    $all_links[] = $link['href'];
                } elseif ( is_string( $link ) ) {
                    $all_links[] = $link;
                }
            }
        }

        if ( empty( $all_links ) && ! empty( $context['html'] ) && preg_match_all( '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/is', $context['html'], $matches ) ) {
            $all_links = $matches[1];
        }

        foreach ( $all_links as $url ) {
            $url = trim( (string) $url );
            $host = wp_parse_url( $url, PHP_URL_HOST );

            if ( empty( $host ) || $host === $home_host ) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function get_brand_alignment_status( $settings, $combined_lower ) {
        $terms = array_merge(
            [ 'shynh', 'shynh super luxury', 'premium', 'luxury', 'vip', 'personalized', 'consultation', 'safety', 'expert', 'refined' ],
            $this->lines_to_terms( $settings['brand_values'] ?? '' ),
            $this->lines_to_terms( $settings['brand_tone'] ?? '' )
        );

        if ( ! empty( $settings['org_name'] ) ) {
            $terms[] = $settings['org_name'];
        }

        $matches = $this->get_matching_terms( $combined_lower, $terms );
        $count   = count( $matches );

        return [
            'status'      => $count >= 4 ? 'green' : ( $count >= 2 ? 'orange' : 'red' ),
            'message_key' => $count >= 4 ? 'brand_alignment_ok' : 'brand_alignment_weak',
            'count'       => $count,
            'matches'     => array_slice( $matches, 0, 8 ),
        ];
    }

    private function get_brand_tone_status( $settings, $combined_lower ) {
        $positive_terms = array_merge(
            [ 'premium', 'luxury', 'refined', 'calm', 'trustworthy', 'consultation', 'personalized', 'minh bach', 'an toan', 'chuyen mon', 'tinh te', 'cao cap' ],
            $this->lines_to_terms( $settings['brand_tone'] ?? '' )
        );
        $risky_terms = [ 'sale soc', 'gia re', 're nhat', 'dap tan', 'lot xac', 'than toc', 'dep ngay', 'cam ket', '100%', 'so 1', 'khong dau', 'khong rui ro' ];

        $positive_count = count( $this->get_matching_terms( $combined_lower, $positive_terms ) );
        $risk_count     = count( $this->get_matching_terms( $combined_lower, array_merge( $risky_terms, $this->lines_to_terms( $settings['brand_banned_claims'] ?? '' ) ) ) );

        return [
            'status'         => $risk_count > 0 ? 'red' : ( $positive_count >= 2 ? 'green' : 'orange' ),
            'message_key'    => $risk_count > 0 ? 'brand_tone_risky' : 'brand_tone_ok',
            'positive_count' => $positive_count,
            'risk_count'     => $risk_count,
        ];
    }

    private function get_brand_proof_status( $settings, $combined_lower, $context ) {
        $strong_claim_terms = [ 'fda', 'ce', 'chung nhan', 'bac si', 'chuyen gia', 'giai thuong', 'bao chi', 'review', 'danh gia', 'truoc sau', 'case study', 'cong nghe', 'doctor', 'certified', 'award', 'press', 'testimonial' ];
        $proof_terms        = [ 'nguon', 'tham khao', 'chung nhan', 'giay phep', 'duoc duyet', 'reviewed', 'source', 'certificate', 'licensed', 'https://' ];

        $claim_count = count( $this->get_matching_terms( $combined_lower, $strong_claim_terms ) );
        $proof_count = count( $this->get_matching_terms( $combined_lower, $proof_terms ) );

        if ( $this->count_external_links( $context ) > 0 ) {
            $proof_count++;
        }

        $status = 0 === $claim_count || $proof_count >= 1 ? 'green' : 'orange';

        return [
            'status'      => $status,
            'message_key' => 'green' === $status ? 'brand_proof_ok' : 'brand_proof_missing',
            'claim_count' => $claim_count,
            'proof_count' => $proof_count,
        ];
    }

    private function lines_to_terms( $value ) {
        if ( empty( $value ) ) {
            return [];
        }

        return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|,|;/', (string) $value ) ) ) );
    }

    private function get_matching_terms( $haystack, $terms ) {
        $matches = [];
        foreach ( array_unique( array_filter( array_map( 'trim', (array) $terms ) ) ) as $term ) {
            if ( '' !== $term && false !== $this->strpos( $haystack, $this->strtolower( $term ) ) ) {
                $matches[] = $term;
            }
        }

        return array_values( array_unique( $matches ) );
    }

    private function has_heading_match( $headings, $needles ) {
        foreach ( (array) $headings as $heading ) {
            $text = $this->strtolower( $heading['text'] ?? '' );
            foreach ( $needles as $needle ) {
                if ( false !== $this->strpos( $text, $needle ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function count_faq_items( $object_type, $object_id, $content, $headings ) {
        $count = 0;
        $faq_json = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_faq' );
        $faq_items = $this->decode_faq_items( $faq_json );
        $count += count( $faq_items );

        foreach ( (array) $headings as $heading ) {
            if ( false !== strpos( (string) ( $heading['text'] ?? '' ), '?' ) ) {
                $count++;
            }
        }

        if ( preg_match_all( '/\?/', $content, $matches ) ) {
            $count = max( $count, count( $matches[0] ) );
        }

        return $count;
    }

    private function count_entity_signals( $settings, $combined_lower ) {
        $entities = [];
        if ( ! empty( $settings['org_name'] ) ) {
            $entities[] = $settings['org_name'];
        }
        $entities[] = get_bloginfo( 'name' );

        if ( ! empty( $settings['geo_aeo_target_entities'] ) ) {
            $entities = array_merge( $entities, preg_split( '/\r\n|\r|\n/', $settings['geo_aeo_target_entities'] ) );
        }

        $count = 0;
        foreach ( array_unique( array_filter( array_map( 'trim', $entities ) ) ) as $entity ) {
            if ( false !== $this->strpos( $combined_lower, $this->strtolower( $entity ) ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function has_schema_support( $object_type, $object_id, $settings ) {
        if ( empty( $settings['schema_enabled'] ) ) {
            return false;
        }

        if ( ! empty( $settings['schema_auto_detect_type'] ) ) {
            return true;
        }

        return '' !== $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_type' );
    }

    private function get_object_meta( $object_type, $object_id, $key ) {
        if ( ! $object_id ) {
            return '';
        }

        return 'term' === $object_type ? get_term_meta( $object_id, $key, true ) : get_post_meta( $object_id, $key, true );
    }

    private function decode_faq_items( $faq_json ) {
        $faq_json = trim( (string) $faq_json );
        if ( '' === $faq_json ) {
            return [];
        }

        $decoded = json_decode( html_entity_decode( $faq_json ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return array_values( array_filter( $decoded, function( $item ) {
            return is_array( $item ) && ! empty( $item['question'] ) && ! empty( $item['answer'] );
        } ) );
    }

    private function get_faq_items_data( $object_type, $object_id, $content, $headings ) {
        $questions = [];
        $faq_json = $this->get_object_meta( $object_type, $object_id, '_shynh_seo_schema_faq' );
        $faq_items = $this->decode_faq_items( $faq_json );

        foreach ( (array) $faq_items as $item ) {
            if ( ! empty( $item['q'] ) ) {
                $questions[] = trim( $item['q'] );
            }
        }

        foreach ( (array) $headings as $heading ) {
            $text = trim( (string) ( $heading['text'] ?? '' ) );
            if ( false !== strpos( $text, '?' ) && ! in_array( $text, $questions, true ) ) {
                $questions[] = $text;
            }
        }

        return $questions;
    }

    private function get_detected_entities( $settings, $combined_lower ) {
        $found = [];
        $entities = [ 'Shynh Super Luxury', 'Shynh', 'Thermage FLX', 'Ultherapy', 'FDA', 'Bác sĩ chuyên khoa', 'Viện thẩm mỹ' ];
        if ( ! empty( $settings['org_name'] ) ) {
            $entities[] = $settings['org_name'];
        }
        foreach ( [ 'brand_values', 'brand_tone', 'brand_technologies', 'brand_usps', 'brand_positioning', 'geo_aeo_target_entities' ] as $brand_key ) {
            if ( ! empty( $settings[ $brand_key ] ) ) {
                $entities = array_merge( $entities, $this->lines_to_terms( $settings[ $brand_key ] ) );
            }
        }
        foreach ( $entities as $entity ) {
            if ( false !== $this->strpos( $combined_lower, $this->strtolower( $entity ) ) ) {
                $found[] = $entity;
            }
        }
        return array_values( array_unique( $found ) );
    }

    private function extract_medical_disclaimer_snippet( $content_clean ) {
        $sentences = preg_split( '/[.?!]+/', $content_clean, -1, PREG_SPLIT_NO_EMPTY );
        $keywords  = [ 'tư vấn', 'bác sĩ', 'cơ địa', 'không thay thế', 'miễn trừ', 'tham khảo', 'consult', 'doctor', 'results may vary' ];

        foreach ( (array) $sentences as $sentence ) {
            $sentence = trim( $sentence );
            if ( $this->contains_any( $sentence, $keywords ) ) {
                return wp_trim_words( $sentence, 25, '...' );
            }
        }

        return '';
    }

    private function calculate_score( $results ) {
        if ( empty( $results ) ) return 0;

        $total_weight = 0;
        $earned_score = 0;

        foreach ( $results as $res ) {
            $weight = $res['weight'];
            $total_weight += $weight;

            if ( $res['status'] === 'green' ) {
                $earned_score += $weight;
            } elseif ( $res['status'] === 'orange' ) {
                $earned_score += ( $weight * 0.5 );
            }
        }

        return $total_weight > 0 ? round( ( $earned_score / $total_weight ) * 100 ) : 0;
    }

    private function normalize_rules( $rules ) {
        $defaults = Shynh_SEO_Core::get_default_rules();

        if ( is_string( $rules ) ) {
            $maybe_rules = maybe_unserialize( $rules );
            if ( $maybe_rules !== $rules ) {
                $rules = $maybe_rules;
            }
        }

        if ( ! is_array( $rules ) ) {
            return $defaults;
        }

        $normalized = [];

        foreach ( $defaults as $key => $default_rule ) {
            $current_rule = $rules[ $key ] ?? [];

            if ( is_string( $current_rule ) ) {
                $maybe_rule = maybe_unserialize( $current_rule );
                $current_rule = is_array( $maybe_rule ) ? $maybe_rule : [];
            }

            if ( ! is_array( $current_rule ) ) {
                $current_rule = [];
            }

            $normalized[ $key ] = array_merge( $default_rule, $current_rule );
            $normalized[ $key ]['enabled'] = array_key_exists( 'enabled', $current_rule )
                ? (bool) $current_rule['enabled']
                : (bool) ( $default_rule['enabled'] ?? true );
            $normalized[ $key ]['weight'] = isset( $current_rule['weight'] )
                ? (float) $current_rule['weight']
                : (float) ( $default_rule['weight'] ?? 0 );

            foreach ( [ 'green_min', 'green_max', 'orange_min', 'orange_max' ] as $threshold_key ) {
                if ( isset( $default_rule[ $threshold_key ] ) ) {
                    $normalized[ $key ][ $threshold_key ] = isset( $current_rule[ $threshold_key ] )
                        ? (float) $current_rule[ $threshold_key ]
                        : (float) $default_rule[ $threshold_key ];
                }
            }
        }

        return $normalized;
    }

    private function strtolower( $value ) {
        $value = (string) $value;

        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $value, 'UTF-8' );
        }

        return strtolower( $value );
    }

    private function strpos( $haystack, $needle ) {
        if ( '' === $needle ) {
            return false;
        }

        if ( function_exists( 'mb_strpos' ) ) {
            return mb_strpos( $haystack, $needle, 0, 'UTF-8' );
        }

        return strpos( $haystack, $needle );
    }

    private function strlen( $value ) {
        $value = (string) $value;

        if ( function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $value, 'UTF-8' );
        }

        return strlen( $value );
    }

    private function substr_count( $haystack, $needle ) {
        if ( '' === $needle ) {
            return 0;
        }

        if ( function_exists( 'mb_substr_count' ) ) {
            return mb_substr_count( $haystack, $needle, 'UTF-8' );
        }

        return substr_count( $haystack, $needle );
    }

    private function word_count( $text ) {
        $text = (string) $text;

        if ( preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches ) ) {
            return count( $matches[0] );
        }

        return str_word_count( $text );
    }
}
