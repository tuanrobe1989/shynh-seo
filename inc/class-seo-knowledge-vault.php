<?php
/**
 * Shynh SEO — Service Knowledge & Multi-Platform Media Assets Vault Module.
 *
 * Manages flexible nested tree service profiles (Parent Category -> Child Category -> Specific Service),
 * multi-source clinical documents (PDF, DOCX, TXT, AWS S3, Google Drive), and authentic media/video assets
 * (YouTube, Facebook Reels, TikTok, AWS S3, Drive, Local Media) with Knowledge Inheritance Engine.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Knowledge_Vault {

    private static $instance = null;
    const OPTION_KEY = 'shynh_seo_knowledge_vault';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Core initialization.
    }

    /**
     * Get all service resource profiles.
     */
    public static function get_all_profiles() {
        $profiles = get_option( self::OPTION_KEY, [] );
        return is_array( $profiles ) ? $profiles : [];
    }

    /**
     * Save all service resource profiles.
     */
    public static function save_all_profiles( array $profiles ) {
        return update_option( self::OPTION_KEY, $profiles );
    }

    /**
     * Resolve knowledge profile for a post or term with Knowledge Inheritance Engine.
     * Searches specific service node -> child category node -> parent category node.
     */
    public static function resolve_inherited_knowledge( $term_id = 0, $taxonomy = 'category', $post_id = 0 ) {
        $all_profiles = self::get_all_profiles();
        $target_term  = null;

        if ( $post_id > 0 && $term_id <= 0 ) {
            $terms = wp_get_post_terms( $post_id, $taxonomy );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $target_term = $terms[0];
            }
        } elseif ( $term_id > 0 ) {
            $target_term = get_term( $term_id, $taxonomy );
        }

        $resolved = [
            'matchedProfile' => null,
            'inheritedDocs'  => [],
            'inheritedMedia' => [],
            'inheritedVideos' => [],
            'inheritancePath' => [],
        ];

        if ( ! $target_term || is_wp_error( $target_term ) ) {
            return $resolved;
        }

        // Build ancestry chain: Current Term -> Parent Term -> Grandparent Term
        $chain = [];
        $curr = $target_term;
        while ( $curr && ! is_wp_error( $curr ) ) {
            $chain[] = $curr;
            if ( $curr->parent > 0 ) {
                $curr = get_term( $curr->parent, $curr->taxonomy );
            } else {
                $curr = null;
            }
        }

        // Traverse chain from specific to root to collect inherited docs and media
        foreach ( $chain as $t ) {
            $term_key = $t->taxonomy . ':' . $t->term_id;
            if ( isset( $all_profiles[ $term_key ] ) ) {
                $prof = $all_profiles[ $term_key ];
                $resolved['inheritancePath'][] = $t->name;

                if ( null === $resolved['matchedProfile'] ) {
                    $resolved['matchedProfile'] = $prof;
                }

                // Collect documents
                if ( ! empty( $prof['docs'] ) && is_array( $prof['docs'] ) ) {
                    foreach ( $prof['docs'] as $d ) {
                        $resolved['inheritedDocs'][] = $d;
                    }
                }

                // Collect media images
                if ( ! empty( $prof['media'] ) && is_array( $prof['media'] ) ) {
                    foreach ( $prof['media'] as $m ) {
                        $resolved['inheritedMedia'][] = $m;
                    }
                }

                // Collect video links (YouTube, Facebook, TikTok)
                if ( ! empty( $prof['videos'] ) && is_array( $prof['videos'] ) ) {
                    foreach ( $prof['videos'] as $v ) {
                        $resolved['inheritedVideos'][] = $v;
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * Generate Responsive Embed HTML and VideoObject Schema for social videos.
     */
    public static function build_social_video_embeds( array $video_urls ) {
        $embeds = [];
        $schemas = [];

        foreach ( $video_urls as $url ) {
            $url = trim( $url );
            if ( empty( $url ) ) {
                continue;
            }

            // YouTube / Shorts Match
            if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches ) ) {
                $yt_id = $matches[1];
                $embed_html = '<div class="shynh-seo-video-responsive" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:20px 0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.15);"><iframe src="https://www.youtube.com/embed/' . esc_attr( $yt_id ) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe></div>';
                $embeds[] = $embed_html;

                $schemas[] = [
                    '@type'        => 'VideoObject',
                    'name'         => 'Video Liệu Trình Y Khoa Shynh Super Luxury',
                    'description'  => 'Video minh họa liệu trình thẩm mỹ y khoa chuẩn Bác sĩ CKI tại Shynh Super Luxury.',
                    'thumbnailUrl' => 'https://img.youtube.com/vi/' . $yt_id . '/maxresdefault.jpg',
                    'embedUrl'     => 'https://www.youtube.com/embed/' . $yt_id,
                ];
            }
            // TikTok Match
            elseif ( strpos( $url, 'tiktok.com' ) !== false ) {
                $embed_html = '<div class="shynh-seo-tiktok-embed" style="margin:20px 0;"><blockquote class="tiktok-embed" cite="' . esc_url( $url ) . '" data-video-id="" style="max-width: 605px;min-width: 325px;"><section><a target="_blank" href="' . esc_url( $url ) . '">Xem Video Trực Tiếp Trên TikTok Shynh Super Luxury</a></section></blockquote><script async src="https://www.tiktok.com/embed.js"></script></div>';
                $embeds[] = $embed_html;
            }
            // Facebook Match
            elseif ( strpos( $url, 'facebook.com' ) !== false || strpos( $url, 'fb.watch' ) !== false ) {
                $embed_html = '<div class="shynh-seo-facebook-embed" style="margin:20px 0;"><div class="fb-video" data-href="' . esc_url( $url ) . '" data-width="500" data-show-text="false"></div></div>';
                $embeds[] = $embed_html;
            }
            // Direct MP4 / S3 / WebM
            elseif ( preg_match( '/\.(mp4|webm|ogg)$/i', $url ) ) {
                $embed_html = '<div class="shynh-seo-direct-video" style="margin:20px 0;"><video controls style="width:100%;border-radius:12px;" preload="metadata"><source src="' . esc_url( $url ) . '"></video></div>';
                $embeds[] = $embed_html;
            }
        }

        return [
            'html'   => implode( "\n", $embeds ),
            'schema' => $schemas,
        ];
    }
}
