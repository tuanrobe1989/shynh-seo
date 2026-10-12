<?php
/**
 * JSON-LD Schema Generator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_Schema {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 3 );
    }

    /**
     * Single source of truth for available Schema types.
     *
     * Used by the post metabox, term editor and the REST `/schema-types`
     * endpoint so AI and humans share one contract.
     *
     * @param string $context 'post' or 'term'.
     * @return array<string,array{label:string,desc:string,fields:array}>
     */
    public static function get_schema_type_options( $context = 'post' ) {
        if ( 'term' === $context ) {
            return [
                ''               => [ 'label' => 'Auto', 'desc' => 'Tự động theo taxonomy.', 'fields' => [] ],
                'CollectionPage' => [ 'label' => 'CollectionPage', 'desc' => 'Trang danh mục gom nhiều mục.', 'fields' => [] ],
                'ItemList'       => [ 'label' => 'ItemList', 'desc' => 'Danh sách có thứ tự các dịch vụ/bài.', 'fields' => [] ],
                'FAQPage'        => [ 'label' => 'FAQPage', 'desc' => 'Trang có bộ câu hỏi thường gặp.', 'fields' => [] ],
                'Service'        => [ 'label' => 'Service', 'desc' => 'Nhóm dịch vụ.', 'fields' => [] ],
                'MedicalWebPage' => [ 'label' => 'MedicalWebPage', 'desc' => 'Trang y khoa (thẩm mỹ, điều trị).', 'fields' => [] ],
                'WebPage'        => [ 'label' => 'WebPage', 'desc' => 'Trang thông thường.', 'fields' => [] ],
            ];
        }

        return [
            ''                 => [ 'label' => 'Auto', 'desc' => 'Tự động: post→Article, khác→WebPage.', 'fields' => [] ],
            'Article'          => [ 'label' => 'Article', 'desc' => 'Bài viết tin tức/blog chuẩn.', 'fields' => [] ],
            'BlogPosting'      => [ 'label' => 'BlogPosting', 'desc' => 'Bài blog.', 'fields' => [] ],
            'WebPage'          => [ 'label' => 'WebPage', 'desc' => 'Trang thông thường.', 'fields' => [] ],
            'MedicalWebPage'   => [ 'label' => 'MedicalWebPage', 'desc' => 'Trang nội dung y khoa.', 'fields' => [] ],
            'Service'          => [ 'label' => 'Service', 'desc' => 'Trang dịch vụ.', 'fields' => [ 'schema_service_name' ] ],
            'MedicalProcedure' => [ 'label' => 'MedicalProcedure', 'desc' => 'Liệu trình/thủ thuật thẩm mỹ y khoa.', 'fields' => [ 'schema_procedure_body_location', 'schema_procedure_how_performed', 'schema_procedure_preparation' ] ],
            'HowTo'            => [ 'label' => 'HowTo', 'desc' => 'Hướng dẫn theo bước (quy trình).', 'fields' => [] ],
            'Physician'        => [ 'label' => 'Physician', 'desc' => 'Trang bác sĩ/chuyên gia.', 'fields' => [ 'schema_reviewed_by' ] ],
            'Review'           => [ 'label' => 'Review', 'desc' => 'Đánh giá — chỉ dùng khi có dữ liệu thật.', 'fields' => [ 'schema_review_rating', 'schema_review_author' ] ],
            'Product'          => [ 'label' => 'Product / Offer', 'desc' => 'Sản phẩm/gói dịch vụ có giá.', 'fields' => [ 'schema_offer_price', 'schema_offer_currency' ] ],
            'VideoObject'      => [ 'label' => 'VideoObject', 'desc' => 'Bài có video minh họa.', 'fields' => [] ],
            'ImageGallery'     => [ 'label' => 'ImageGallery', 'desc' => 'Bộ sưu tập hình ảnh trước/sau.', 'fields' => [] ],
        ];
    }

    public function output_schema() {
        $settings = Shynh_SEO_Core::get_settings();
        if ( empty( $settings['schema_enabled'] ) ) {
            return;
        }

        $schema   = [];

        // 1. Organization Schema (Every page)
        $org_schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => home_url( '/#organization' ),
            'name'     => $settings['org_name'] ?: get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
        ];

        if ( ! empty( $settings['org_logo'] ) ) {
            $org_schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $settings['org_logo'],
            ];
            $org_schema['image'] = $org_schema['logo'];
        }

        $same_as = [];
        $social_keys = [ 'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_twitter' ];
        foreach ( $social_keys as $key ) {
            if ( ! empty( $settings[ $key ] ) ) {
                $same_as[] = $settings[ $key ];
            }
        }
        if ( ! empty( $same_as ) ) {
            $org_schema['sameAs'] = $same_as;
        }

        $schema[] = $org_schema;

        // 2. WebSite Schema (Front page)
        if ( is_front_page() || is_home() ) {
            $schema[] = [
                '@context'      => 'https://schema.org',
                '@type'         => 'WebSite',
                '@id'           => home_url( '/#website' ),
                'url'           => home_url( '/' ),
                'name'          => get_bloginfo( 'name' ),
                'publisher'     => [ '@id' => home_url( '/#organization' ) ],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => home_url( '/?s={search_term_string}' ),
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        // 3. Article Schema (Singular posts/pages)
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $post    = get_post( $post_id );
            
            if ( is_attachment() ) {
                $attachment_url = wp_get_attachment_url( $post_id );
                $caption = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 30, '...' );
                $desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
                
                $image_schema = [
                    '@context'      => 'https://schema.org',
                    '@type'         => 'ImageObject',
                    '@id'           => get_attachment_link( $post_id ) . '#image',
                    'url'           => $attachment_url,
                    'contentUrl'    => $attachment_url,
                    'name'          => get_the_title( $post_id ),
                    'caption'       => $caption ?: get_the_title( $post_id ),
                    'description'   => $desc,
                    'datePublished' => get_the_date( 'c', $post_id ),
                    'dateModified'  => get_the_modified_date( 'c', $post_id ),
                    'isPartOf'      => [ '@id' => get_attachment_link( $post_id ) . '#webpage' ],
                ];

                if ( wp_attachment_is_image( $post_id ) ) {
                    $meta_data = wp_get_attachment_metadata( $post_id );
                    if ( ! empty( $meta_data['width'] ) ) {
                        $image_schema['width'] = $meta_data['width'];
                    }
                    if ( ! empty( $meta_data['height'] ) ) {
                        $image_schema['height'] = $meta_data['height'];
                    }
                }

                $webpage_schema = [
                    '@context'      => 'https://schema.org',
                    '@type'         => 'WebPage',
                    '@id'           => get_attachment_link( $post_id ) . '#webpage',
                    'url'           => get_attachment_link( $post_id ),
                    'name'          => get_the_title( $post_id ),
                    'isPartOf'      => [ '@id' => home_url( '/#website' ) ],
                    'mainEntity'    => [ '@id' => get_attachment_link( $post_id ) . '#image' ],
                ];

                $schema[] = $image_schema;
                $schema[] = $webpage_schema;
            } elseif ( 'shynh_author' === get_post_type( $post_id ) ) {
                $author_profile_schemas = $this->build_author_profile_schemas( $post_id );
                foreach ( $author_profile_schemas as $author_profile_schema ) {
                    $schema[] = $author_profile_schema;
                }
            } else {
                $article_author = $this->build_article_author_schema( $post );
                $article_schema = [
                    '@context'      => 'https://schema.org',
                    '@type'         => 'Article',
                    '@id'           => get_permalink( $post_id ) . '#article',
                    'isPartOf'      => [ '@id' => get_permalink( $post_id ) . '#webpage' ],
                    'author'        => $article_author,
                    'headline'      => get_the_title( $post_id ),
                    'datePublished' => get_the_date( 'c', $post_id ),
                    'dateModified'  => get_the_modified_date( 'c', $post_id ),
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id'   => get_permalink( $post_id ),
                    ],
                    'publisher'     => [ '@id' => home_url( '/#organization' ) ],
                ];

                if ( has_post_thumbnail( $post_id ) ) {
                    $article_schema['image'] = [
                        '@type' => 'ImageObject',
                        'url'   => get_the_post_thumbnail_url( $post_id, 'full' ),
                    ];
                } elseif ( ! empty( $settings['default_og_image'] ) ) {
                    $article_schema['image'] = [
                        '@type' => 'ImageObject',
                        'url'   => $settings['default_og_image'],
                    ];
                }

                $schema[] = $article_schema;

                $schema_type = get_post_meta( $post_id, '_shynh_seo_schema_type', true );
                if ( ! $schema_type && ! empty( $settings['schema_auto_detect_type'] ) ) {
                    $schema_type = 'post' === get_post_type( $post_id ) ? 'Article' : 'WebPage';
                }

                if ( ! empty( $settings['schema_enable_medical_web_page'] ) && 'MedicalWebPage' === $schema_type ) {
                    $schema[] = $this->build_webpage_schema( $post_id, 'MedicalWebPage' );
                } elseif ( 'WebPage' === $schema_type ) {
                    $schema[] = $this->build_webpage_schema( $post_id, 'WebPage' );
                }

                if ( ! empty( $settings['schema_enable_service'] ) && in_array( $schema_type, [ 'Service', 'MedicalWebPage' ], true ) ) {
                    $schema[] = $this->build_service_schema( $post_id );
                }

                // Extended schema types (active when explicitly selected).
                switch ( $schema_type ) {
                    case 'MedicalProcedure':
                        $schema[] = $this->build_medical_procedure_schema( $post_id );
                        break;
                    case 'HowTo':
                        $howto = $this->build_howto_schema( $post_id );
                        if ( $howto ) { $schema[] = $howto; }
                        break;
                    case 'Physician':
                        $schema[] = $this->build_physician_schema( $post_id );
                        break;
                    case 'Review':
                        $review = $this->build_review_schema( $post_id );
                        if ( $review ) { $schema[] = $review; }
                        break;
                    case 'Product':
                        $schema[] = $this->build_product_schema( $post_id );
                        break;
                    case 'VideoObject':
                        $video = $this->build_video_schema( $post_id );
                        if ( $video ) { $schema[] = $video; }
                        break;
                    case 'ImageGallery':
                        $gallery = $this->build_image_gallery_schema( $post_id );
                        if ( $gallery ) { $schema[] = $gallery; }
                        break;
                }

                if ( ! empty( $settings['schema_enable_faq'] ) ) {
                    $faq_schema = $this->build_faq_schema( 'post', $post_id, get_permalink( $post_id ) );
                    if ( $faq_schema ) {
                        $schema[] = $faq_schema;
                    }
                }
            }
        }

        // 4. CollectionPage / FAQ schema for taxonomy archives.
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $term_link = get_term_link( $term );
                $term_type = get_term_meta( $term->term_id, '_shynh_seo_schema_type', true );
                if ( ! is_wp_error( $term_link ) && ! empty( $settings['schema_enable_collection_page'] ) ) {
                    $schema[] = $this->build_collection_schema( $term, $term_link, $term_type );
                }

                if ( ! is_wp_error( $term_link ) && ! empty( $settings['schema_enable_faq'] ) ) {
                    $faq_schema = $this->build_faq_schema( 'term', $term->term_id, $term_link );
                    if ( $faq_schema ) {
                        $schema[] = $faq_schema;
                    }
                }
            }
        }

        // 5. BreadcrumbList Schema
        if ( ! is_front_page() && ! is_home() ) {
            $breadcrumbs = $this->generate_breadcrumb_items();
            if ( ! empty( $breadcrumbs ) ) {
                $schema[] = [
                    '@context'        => 'https://schema.org',
                    '@type'           => 'BreadcrumbList',
                    '@id'             => get_permalink() . '#breadcrumb',
                    'itemListElement' => $breadcrumbs,
                ];
            }
        }

        // Output JSON
        if ( ! empty( $schema ) ) {
            foreach ( $schema as $s ) {
                echo '<script type="application/ld+json" class="shynh-seo-schema">' . wp_json_encode( $s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
            }
        }
    }

    private function build_webpage_schema( $post_id, $type = 'WebPage' ) {
        return [
            '@context'     => 'https://schema.org',
            '@type'        => $type,
            '@id'          => get_permalink( $post_id ) . '#webpage',
            'url'          => get_permalink( $post_id ),
            'name'         => get_the_title( $post_id ),
            'description'  => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 35, '...' ),
            'isPartOf'     => [ '@id' => home_url( '/#website' ) ],
            'publisher'    => [ '@id' => home_url( '/#organization' ) ],
            'dateModified' => get_the_modified_date( 'c', $post_id ),
        ];
    }

    private function build_article_author_schema( $post ) {
        $profile_id = absint( get_post_meta( $post->ID, '_shynh_author_profile_id', true ) );
        $profile    = $profile_id ? get_post( $profile_id ) : null;

        if ( $profile && 'shynh_author' === $profile->post_type && 'publish' === $profile->post_status ) {
            $profile_url = get_permalink( $profile );
            $author      = [
                '@type'    => 'Person',
                '@id'      => $profile_url . '#person',
                'name'     => get_the_title( $profile ),
                'url'      => $profile_url,
                'jobTitle' => get_post_meta( $profile->ID, '_shynh_author_job_title', true ),
            ];

            if ( has_post_thumbnail( $profile ) ) {
                $author['image'] = get_the_post_thumbnail_url( $profile, 'full' );
            }

            return array_filter( $author );
        }

        $author_name = get_the_author_meta( 'display_name', $post->post_author );
        if ( $author_name ) {
            return [
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => get_author_posts_url( $post->post_author ),
            ];
        }

        return [ '@id' => home_url( '/#organization' ) ];
    }

    private function build_author_profile_schemas( $post_id ) {
        $profile_url = get_permalink( $post_id );
        $description = get_post_meta( $post_id, '_shynh_seo_meta_desc', true );
        if ( empty( $description ) ) {
            $profile = get_post( $post_id );
            $description = wp_trim_words( wp_strip_all_tags( $profile->post_excerpt ?: $profile->post_content ), 35, '...' );
        }

        $person = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            '@id'      => $profile_url . '#person',
            'name'     => get_the_title( $post_id ),
            'url'      => $profile_url,
            'jobTitle' => get_post_meta( $post_id, '_shynh_author_job_title', true ),
            'description' => wp_strip_all_tags( $description ),
            'worksFor' => [ '@id' => home_url( '/#organization' ) ],
            'mainEntityOfPage' => [ '@id' => $profile_url . '#profile' ],
        ];

        $profile_page = [
            '@context'    => 'https://schema.org',
            '@type'       => 'ProfilePage',
            '@id'         => $profile_url . '#profile',
            'url'         => $profile_url,
            'name'        => get_the_title( $post_id ),
            'description' => wp_strip_all_tags( $description ),
            'mainEntity'  => [ '@id' => $profile_url . '#person' ],
            'isPartOf'    => [ '@id' => home_url( '/#website' ) ],
            'dateModified' => get_the_modified_date( 'c', $post_id ),
        ];

        if ( has_post_thumbnail( $post_id ) ) {
            $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
            $person['image'] = $image_url;
            $profile_page['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url'   => $image_url,
            ];
        }

        return [ array_filter( $profile_page ), array_filter( $person ) ];
    }

    private function build_service_schema( $post_id ) {
        $service_name = get_post_meta( $post_id, '_shynh_seo_schema_service_name', true ) ?: get_the_title( $post_id );
        $description  = get_post_meta( $post_id, '_shynh_seo_meta_desc', true );
        if ( empty( $description ) ) {
            $description = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 35, '...' );
        }

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            '@id'         => get_permalink( $post_id ) . '#service',
            'name'        => $service_name,
            'description' => wp_strip_all_tags( $description ),
            'url'         => get_permalink( $post_id ),
            'provider'    => [ '@id' => home_url( '/#organization' ) ],
            'areaServed'  => get_bloginfo( 'name' ),
        ];
    }

    private function schema_description( $post_id ) {
        $desc = get_post_meta( $post_id, '_shynh_seo_meta_desc', true );
        if ( empty( $desc ) ) {
            $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 35, '...' );
        }
        return wp_strip_all_tags( $desc );
    }

    private function build_medical_procedure_schema( $post_id ) {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'MedicalProcedure',
            '@id'         => get_permalink( $post_id ) . '#procedure',
            'name'        => get_post_meta( $post_id, '_shynh_seo_schema_service_name', true ) ?: get_the_title( $post_id ),
            'description' => $this->schema_description( $post_id ),
            'url'         => get_permalink( $post_id ),
            'provider'    => [ '@id' => home_url( '/#organization' ) ],
        ];

        $body = get_post_meta( $post_id, '_shynh_seo_schema_procedure_body_location', true );
        if ( $body ) {
            $schema['bodyLocation'] = $body;
        }
        $how = get_post_meta( $post_id, '_shynh_seo_schema_procedure_how_performed', true );
        if ( $how ) {
            $schema['howPerformed'] = wp_strip_all_tags( $how );
        }
        $prep = get_post_meta( $post_id, '_shynh_seo_schema_procedure_preparation', true );
        if ( $prep ) {
            $schema['preparation'] = wp_strip_all_tags( $prep );
        }

        return $schema;
    }

    private function build_howto_schema( $post_id ) {
        // Derive steps from H2/H3 headings via the content extractor.
        if ( ! class_exists( 'Shynh_SEO_Content_Extractor' ) ) {
            return null;
        }
        $context  = Shynh_SEO_Content_Extractor::extract( $post_id, get_post_field( 'post_content', $post_id ) );
        $headings = isset( $context['headings'] ) ? $context['headings'] : [];
        $steps    = [];
        $position = 1;
        foreach ( $headings as $h ) {
            $level = isset( $h['level'] ) ? (int) $h['level'] : 0;
            $text  = isset( $h['text'] ) ? trim( wp_strip_all_tags( $h['text'] ) ) : '';
            if ( ( 2 === $level || 3 === $level ) && '' !== $text ) {
                $steps[] = [
                    '@type'    => 'HowToStep',
                    'position' => $position++,
                    'name'     => $text,
                    'url'      => get_permalink( $post_id ) . '#step-' . ( $position - 1 ),
                ];
            }
            if ( count( $steps ) >= 15 ) {
                break;
            }
        }
        if ( count( $steps ) < 2 ) {
            return null; // Not enough structure to be a valid HowTo.
        }

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            '@id'         => get_permalink( $post_id ) . '#howto',
            'name'        => get_the_title( $post_id ),
            'description' => $this->schema_description( $post_id ),
            'step'        => $steps,
        ];
    }

    private function build_physician_schema( $post_id ) {
        $name = get_post_meta( $post_id, '_shynh_seo_schema_reviewed_by', true ) ?: get_the_title( $post_id );
        return [
            '@context'          => 'https://schema.org',
            '@type'             => 'Physician',
            '@id'               => get_permalink( $post_id ) . '#physician',
            'name'              => $name,
            'url'               => get_permalink( $post_id ),
            'description'       => $this->schema_description( $post_id ),
            'medicalSpecialty'  => 'PlasticSurgery',
            'worksFor'          => [ '@id' => home_url( '/#organization' ) ],
        ];
    }

    private function build_review_schema( $post_id ) {
        // Review schema only when a real rating value is provided (no fabricated ratings).
        $rating = get_post_meta( $post_id, '_shynh_seo_schema_review_rating', true );
        if ( '' === $rating || null === $rating ) {
            return null;
        }
        $rating = (float) $rating;
        if ( $rating <= 0 || $rating > 5 ) {
            return null;
        }
        $author = get_post_meta( $post_id, '_shynh_seo_schema_review_author', true ) ?: get_bloginfo( 'name' );

        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'Review',
            '@id'          => get_permalink( $post_id ) . '#review',
            'itemReviewed' => [
                '@type' => 'MedicalBusiness',
                'name'  => get_bloginfo( 'name' ),
            ],
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => $rating,
                'bestRating'  => 5,
                'worstRating' => 1,
            ],
            'author'       => [ '@type' => 'Person', 'name' => $author ],
        ];
    }

    private function build_product_schema( $post_id ) {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            '@id'         => get_permalink( $post_id ) . '#product',
            'name'        => get_post_meta( $post_id, '_shynh_seo_schema_service_name', true ) ?: get_the_title( $post_id ),
            'description' => $this->schema_description( $post_id ),
            'url'         => get_permalink( $post_id ),
        ];

        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
        }

        $price = get_post_meta( $post_id, '_shynh_seo_schema_offer_price', true );
        if ( '' !== $price && null !== $price ) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) (float) $price,
                'priceCurrency' => get_post_meta( $post_id, '_shynh_seo_schema_offer_currency', true ) ?: 'VND',
                'availability'  => 'https://schema.org/InStock',
                'url'           => get_permalink( $post_id ),
                'seller'        => [ '@id' => home_url( '/#organization' ) ],
            ];
        }

        return $schema;
    }

    private function build_video_schema( $post_id ) {
        // Prefer Knowledge Vault videos; build VideoObject via the vault helper.
        if ( ! class_exists( 'Shynh_SEO_Knowledge_Vault' ) ) {
            return null;
        }
        $resolved = Shynh_SEO_Knowledge_Vault::resolve_inherited_knowledge( 0, 'category', $post_id );
        $videos   = isset( $resolved['inheritedVideos'] ) ? (array) $resolved['inheritedVideos'] : [];
        if ( empty( $videos ) ) {
            return null;
        }
        $urls = array_map( function ( $v ) {
            return is_array( $v ) ? ( $v['url'] ?? '' ) : $v;
        }, $videos );
        $built = Shynh_SEO_Knowledge_Vault::build_social_video_embeds( array_filter( $urls ) );
        if ( empty( $built['schema'] ) ) {
            return null;
        }
        $first = $built['schema'][0];
        $first['@context'] = 'https://schema.org';
        $first['@id']      = get_permalink( $post_id ) . '#video';
        $first['uploadDate'] = get_the_date( 'c', $post_id );
        return $first;
    }

    private function build_image_gallery_schema( $post_id ) {
        if ( ! class_exists( 'Shynh_SEO_Content_Extractor' ) ) {
            return null;
        }
        $context = Shynh_SEO_Content_Extractor::extract( $post_id, get_post_field( 'post_content', $post_id ) );
        $images  = isset( $context['images'] ) ? (array) $context['images'] : [];
        if ( count( $images ) < 2 ) {
            return null;
        }
        $elements = [];
        $position = 1;
        foreach ( $images as $img ) {
            $src = isset( $img['src'] ) ? $img['src'] : '';
            if ( '' === $src ) {
                continue;
            }
            $elements[] = [
                '@type'    => 'ImageObject',
                'position' => $position++,
                'contentUrl' => $src,
                'caption'  => isset( $img['alt'] ) ? $img['alt'] : '',
            ];
            if ( count( $elements ) >= 20 ) {
                break;
            }
        }
        if ( count( $elements ) < 2 ) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'ImageGallery',
            '@id'        => get_permalink( $post_id ) . '#gallery',
            'name'       => get_the_title( $post_id ),
            'url'        => get_permalink( $post_id ),
            'associatedMedia' => $elements,
        ];
    }

    private function build_collection_schema( $term, $term_link, $type = '' ) {
        $description = get_term_meta( $term->term_id, '_shynh_seo_meta_desc', true );
        if ( empty( $description ) ) {
            $description = wp_trim_words( wp_strip_all_tags( $term->description ), 35, '...' );
        }

        // Respect an explicitly chosen term schema type for the top-level @type.
        $allowed_top = [ 'CollectionPage', 'WebPage', 'MedicalWebPage', 'Service', 'ItemList' ];
        $top_type    = in_array( $type, $allowed_top, true ) ? $type : 'CollectionPage';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $top_type,
            '@id'         => $term_link . '#collection',
            'url'         => $term_link,
            'name'        => $term->name,
            'description' => wp_strip_all_tags( $description ),
            'isPartOf'    => [ '@id' => home_url( '/#website' ) ],
            'publisher'   => [ '@id' => home_url( '/#organization' ) ],
        ];

        $items = $this->get_term_item_list( $term );
        if ( ! empty( $items ) ) {
            $schema['mainEntity'] = [
                '@type'           => 'ItemList',
                'itemListElement' => $items,
            ];
        }

        return $schema;
    }

    private function get_term_item_list( $term ) {
        $posts = get_posts( [
            'post_type'      => Shynh_SEO_Core::get_public_content_post_types(),
            'posts_per_page' => 10,
            'tax_query'      => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
        ] );

        $items = [];
        $position = 1;
        foreach ( $posts as $post ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => get_permalink( $post ),
                'name'     => get_the_title( $post ),
            ];
        }

        return $items;
    }

    private function build_faq_schema( $object_type, $object_id, $url ) {
        $raw = 'term' === $object_type
            ? get_term_meta( $object_id, '_shynh_seo_schema_faq', true )
            : get_post_meta( $object_id, '_shynh_seo_schema_faq', true );

        $items = $this->decode_faq_items( $raw );
        if ( empty( $items ) ) {
            return null;
        }

        $main_entity = [];
        foreach ( $items as $item ) {
            $main_entity[] = [
                '@type' => 'Question',
                'name'  => wp_strip_all_tags( $item['question'] ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_kses_post( $item['answer'] ),
                ],
            ];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            '@id'        => $url . '#faq',
            'mainEntity' => $main_entity,
        ];
    }

    private function decode_faq_items( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return [];
        }

        $decoded = json_decode( html_entity_decode( $raw ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return array_values( array_filter( $decoded, function( $item ) {
            return is_array( $item ) && ! empty( $item['question'] ) && ! empty( $item['answer'] );
        } ) );
    }

    private function generate_breadcrumb_items() {
        $items = [];
        $position = 1;
        $current_lang = function_exists( 'kp_current_language_code' ) ? kp_current_language_code() : ( function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '' );
        $is_en = 'en' === $current_lang;

        // Home
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'item'     => [
                '@id'  => $is_en ? home_url( '/en/home/' ) : home_url( '/' ),
                'name' => $is_en ? 'Home' : __( 'Trang chủ', 'shynh-seo' ),
            ]
        ];

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $post    = get_post( $post_id );

            $primary_term = class_exists( 'Shynh_SEO_Core' ) ? Shynh_SEO_Core::get_primary_term( $post_id ) : null;
            if ( $primary_term ) {
                $term_chain = [];
                $current    = $primary_term;

                while ( $current && ! is_wp_error( $current ) ) {
                    $term_chain[] = $current;
                    $current = $current->parent ? get_term( $current->parent, $primary_term->taxonomy ) : null;
                }

                foreach ( array_reverse( $term_chain ) as $term ) {
                    $term_link = get_term_link( $term );
                    if ( is_wp_error( $term_link ) ) {
                        continue;
                    }

                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $position++,
                        'item'     => [
                            '@id'  => $term_link,
                            'name' => $term->name,
                        ]
                    ];
                }
            }

            // The post itself
            $title = get_post_meta( $post_id, '_shynh_seo_breadcrumb_title', true ) ?: get_the_title( $post_id );
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => [
                    '@id'  => get_permalink( $post_id ),
                    'name' => $title,
                ]
            ];
        } elseif ( is_category() || is_tax() ) {
            $term = get_queried_object();
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => [
                    '@id'  => get_term_link( $term ),
                    'name' => $term->name,
                ]
            ];
        }

        return $items;
    }
}
