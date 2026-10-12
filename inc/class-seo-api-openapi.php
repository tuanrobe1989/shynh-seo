<?php
/**
 * Shynh SEO — OpenAPI 3.1 specification builder.
 *
 * Produces a machine + human readable description of the `shynh-seo/v1` REST API
 * that is served at `GET /openapi.json` and rendered by the bundled Swagger UI.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_API_OpenAPI {

    /**
     * Build the full OpenAPI document as a PHP array.
     *
     * @return array
     */
    public static function build() {
        $base = untrailingslashit( rest_url( Shynh_SEO_API::NAMESPACE ) );

        return [
            'openapi' => '3.1.0',
            'info'    => [
                'title'       => 'Shynh SEO AI Access API',
                'version'     => defined( 'SHYNH_SEO_VERSION' ) ? SHYNH_SEO_VERSION : '1.0.0',
                'description' => self::intro_markdown(),
                'contact'     => [
                    'name' => 'Shynh Group',
                    'url'  => 'https://shynhgroup.com',
                ],
            ],
            'servers' => [
                [
                    'url'         => $base,
                    'description' => get_bloginfo( 'name' ),
                ],
            ],
            'security'   => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
            'tags'       => [
                [ 'name' => 'Discovery', 'description' => 'API info & spec.' ],
                [ 'name' => 'Content', 'description' => 'Bài viết & trang (posts/pages).' ],
                [ 'name' => 'Taxonomy', 'description' => 'Danh mục & term.' ],
                [ 'name' => 'SEO Intelligence', 'description' => 'FAQ, từ khóa, bài liên quan, phân tích, audit.' ],
                [ 'name' => 'AI Context', 'description' => 'Luật viết, schema types, blocks, content-brief để AI hiểu ngữ cảnh.' ],
                [ 'name' => 'Write', 'description' => 'Ghi meta SEO + tạo draft (scope write).' ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-Shynh-API-Key',
                        'description' => 'Dán API key (bắt đầu bằng shynh_sk_). Tạo trong wp-admin → Shynh SEO → API & AI Access.',
                    ],
                    'BearerAuth' => [
                        'type'   => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Gửi API key dạng: Authorization: Bearer shynh_sk_...',
                    ],
                ],
                'schemas' => self::schemas(),
            ],
            'paths' => self::paths(),
        ];
    }

    private static function intro_markdown() {
        return "REST API trao quyền cho AI đọc & thao tác dữ liệu SEO/AEO/GEO của website.\n\n"
            . "**Xác thực:** gửi API key qua header `X-Shynh-API-Key: shynh_sk_...` hoặc `Authorization: Bearer shynh_sk_...`.\n\n"
            . "**Scope:** key `read` cho các endpoint đọc; key `write` cho các endpoint ghi meta SEO.\n\n"
            . "Endpoint ghi chỉ cập nhật **meta SEO** (focus keyword, meta title/desc, schema, FAQ) — "
            . "không bao giờ sửa nội dung thân bài hay xuất bản bài viết.";
    }

    private static function schemas() {
        return [
            'Error' => [
                'type'       => 'object',
                'properties' => [
                    'code'    => [ 'type' => 'string' ],
                    'message' => [ 'type' => 'string' ],
                    'data'    => [ 'type' => 'object' ],
                ],
            ],
            'PostListItem' => [
                'type'       => 'object',
                'properties' => [
                    'id'               => [ 'type' => 'integer' ],
                    'type'             => [ 'type' => 'string' ],
                    'title'            => [ 'type' => 'string' ],
                    'slug'             => [ 'type' => 'string' ],
                    'url'              => [ 'type' => 'string', 'format' => 'uri' ],
                    'status'           => [ 'type' => 'string' ],
                    'date'             => [ 'type' => 'string', 'format' => 'date-time' ],
                    'modified'         => [ 'type' => 'string', 'format' => 'date-time' ],
                    'focus_keyword'    => [ 'type' => 'string' ],
                    'meta_title'       => [ 'type' => 'string' ],
                    'meta_desc'        => [ 'type' => 'string' ],
                    'seo_score'        => [ 'type' => [ 'integer', 'null' ] ],
                    'geo_aeo_score'    => [ 'type' => [ 'integer', 'null' ] ],
                    'ai_search_score'  => [ 'type' => [ 'integer', 'null' ] ],
                    'word_count'       => [ 'type' => 'integer' ],
                    'primary_category' => [ 'type' => [ 'string', 'null' ] ],
                ],
            ],
            'PostDetail' => [
                'allOf' => [
                    [ '$ref' => '#/components/schemas/PostListItem' ],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'content_plain' => [ 'type' => 'string' ],
                            'content_markdown' => [ 'type' => 'string' ],
                            'outline'       => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'images'        => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'internal_links'=> [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'external_links'=> [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'faq'           => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqItem' ] ],
                            'schema'        => [ 'type' => 'object' ],
                            'keywords'      => [ 'type' => 'object' ],
                            'related_posts' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'linked_by'     => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                            'knowledge'     => [ 'type' => 'object' ],
                            'analysis'      => [ '$ref' => '#/components/schemas/AnalysisResult' ],
                        ],
                    ],
                ],
            ],
            'Term' => [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'taxonomy'      => [ 'type' => 'string' ],
                    'name'          => [ 'type' => 'string' ],
                    'slug'          => [ 'type' => 'string' ],
                    'url'           => [ 'type' => 'string', 'format' => 'uri' ],
                    'count'         => [ 'type' => 'integer' ],
                    'parent'        => [ 'type' => 'integer' ],
                    'description'   => [ 'type' => 'string' ],
                    'focus_keyword' => [ 'type' => 'string' ],
                    'meta_title'    => [ 'type' => 'string' ],
                    'meta_desc'     => [ 'type' => 'string' ],
                    'faq'           => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqItem' ] ],
                ],
            ],
            'FaqItem' => [
                'type'       => 'object',
                'properties' => [
                    'question' => [ 'type' => 'string' ],
                    'answer'   => [ 'type' => 'string' ],
                ],
            ],
            'Keyword' => [
                'type'       => 'object',
                'properties' => [
                    'keyword'         => [ 'type' => 'string' ],
                    'usage_count'     => [ 'type' => 'integer' ],
                    'cannibalized'    => [ 'type' => 'boolean' ],
                    'targets'         => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                ],
            ],
            'AnalysisResult' => [
                'type'       => 'object',
                'properties' => [
                    'seo_score'       => [ 'type' => 'integer' ],
                    'geo_aeo_score'   => [ 'type' => [ 'integer', 'null' ] ],
                    'ai_search_score' => [ 'type' => [ 'integer', 'null' ] ],
                    'results'         => [ 'type' => 'object' ],
                    'geo_aeo'         => [ 'type' => [ 'object', 'null' ] ],
                ],
            ],
            'SiteInfo' => [ 'type' => 'object' ],
            'AuditReport' => [ 'type' => 'object' ],
        ];
    }

    /**
     * Common reusable parameter definitions.
     */
    private static function param( $name, $in, $desc, $type = 'string', $extra = [] ) {
        return array_merge( [
            'name'        => $name,
            'in'          => $in,
            'description' => $desc,
            'required'    => 'path' === $in,
            'schema'      => [ 'type' => $type ],
        ], $extra );
    }

    private static function ok_json( $ref_or_schema, $desc = 'OK' ) {
        $schema = is_string( $ref_or_schema ) ? [ '$ref' => $ref_or_schema ] : $ref_or_schema;
        return [
            'description' => $desc,
            'content'     => [ 'application/json' => [ 'schema' => $schema ] ],
        ];
    }

    private static function paths() {
        $list = [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/PostListItem' ] ];

        return [
            '/' => [
                'get' => [
                    'tags'        => [ 'Discovery' ],
                    'summary'     => 'API info & danh sách endpoint',
                    'operationId' => 'getIndex',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/openapi.json' => [
                'get' => [
                    'tags'        => [ 'Discovery' ],
                    'summary'     => 'OpenAPI 3.1 spec',
                    'operationId' => 'getOpenApi',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/site' => [
                'get' => [
                    'tags'        => [ 'Content' ],
                    'summary'     => 'Thông tin site: post types, taxonomies, ngôn ngữ, tổ chức',
                    'operationId' => 'getSite',
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/SiteInfo' ) ],
                ],
            ],
            '/posts' => [
                'get' => [
                    'tags'        => [ 'Content' ],
                    'summary'     => 'Danh sách bài viết/trang có dữ liệu SEO',
                    'operationId' => 'listPosts',
                    'parameters'  => [
                        self::param( 'type', 'query', 'Post type (post, page, dich-vu, tin-tuc...)' ),
                        self::param( 'search', 'query', 'Từ khóa tìm kiếm' ),
                        self::param( 'taxonomy', 'query', 'Taxonomy để lọc' ),
                        self::param( 'term', 'query', 'Term ID để lọc', 'integer' ),
                        self::param( 'status', 'query', 'publish (mặc định), draft, any' ),
                        self::param( 'has_focus_keyword', 'query', 'true/false: chỉ bài (thiếu) focus keyword', 'boolean' ),
                        self::param( 'orderby', 'query', 'date | modified | title | seo_score' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số item/trang (tối đa 100)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( $list ) ],
                ],
            ],
            '/posts/{id}' => [
                'get' => [
                    'tags'        => [ 'Content' ],
                    'summary'     => 'Chi tiết SEO đầy đủ của 1 bài',
                    'operationId' => 'getPost',
                    'parameters'  => [ self::param( 'id', 'path', 'Post ID', 'integer' ) ],
                    'responses'   => [
                        '200' => self::ok_json( '#/components/schemas/PostDetail' ),
                        '404' => self::ok_json( '#/components/schemas/Error', 'Không tìm thấy' ),
                    ],
                ],
            ],
            '/posts/{id}/seo' => [
                'patch' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Cập nhật meta SEO (whitelist) + re-score',
                    'operationId' => 'updatePostSeo',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'parameters'  => [ self::param( 'id', 'path', 'Post ID', 'integer' ) ],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'focus_keyword'       => [ 'type' => 'string' ],
                                'meta_title'          => [ 'type' => 'string' ],
                                'meta_desc'           => [ 'type' => 'string' ],
                                'breadcrumb_title'    => [ 'type' => 'string' ],
                                'schema_type'         => [ 'type' => 'string' ],
                                'schema_service_name' => [ 'type' => 'string' ],
                                'geo_aeo_mode'        => [ 'type' => 'string' ],
                            ],
                        ] ] ],
                    ],
                    'responses'   => [
                        '200' => self::ok_json( '#/components/schemas/PostDetail' ),
                        '403' => self::ok_json( '#/components/schemas/Error', 'Thiếu scope write' ),
                    ],
                ],
            ],
            '/posts/{id}/faq' => [
                'put' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Thay toàn bộ FAQ của bài',
                    'operationId' => 'updatePostFaq',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'parameters'  => [ self::param( 'id', 'path', 'Post ID', 'integer' ) ],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'faq' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqItem' ] ],
                            ],
                        ] ] ],
                    ],
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/PostDetail' ) ],
                ],
            ],
            '/taxonomies' => [
                'get' => [
                    'tags'        => [ 'Taxonomy' ],
                    'summary'     => 'Danh sách taxonomy công khai',
                    'operationId' => 'listTaxonomies',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/terms' => [
                'get' => [
                    'tags'        => [ 'Taxonomy' ],
                    'summary'     => 'Danh sách term theo taxonomy',
                    'operationId' => 'listTerms',
                    'parameters'  => [
                        self::param( 'taxonomy', 'query', 'Taxonomy (category, loai-dich-vu...)' ),
                        self::param( 'search', 'query', 'Từ khóa' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số item/trang', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/Term' ] ] ) ],
                ],
            ],
            '/terms/{id}' => [
                'get' => [
                    'tags'        => [ 'Taxonomy' ],
                    'summary'     => 'Chi tiết SEO của 1 term',
                    'operationId' => 'getTerm',
                    'parameters'  => [
                        self::param( 'id', 'path', 'Term ID', 'integer' ),
                        self::param( 'taxonomy', 'query', 'Taxonomy (nếu term ID trùng giữa các taxonomy)' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/Term' ) ],
                ],
                'patch' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Cập nhật meta SEO của term',
                    'operationId' => 'updateTermSeo',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'parameters'  => [
                        self::param( 'id', 'path', 'Term ID', 'integer' ),
                        self::param( 'taxonomy', 'query', 'Taxonomy' ),
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'focus_keyword'    => [ 'type' => 'string' ],
                                'meta_title'       => [ 'type' => 'string' ],
                                'meta_desc'        => [ 'type' => 'string' ],
                                'breadcrumb_title' => [ 'type' => 'string' ],
                                'schema_type'      => [ 'type' => 'string' ],
                                'geo_aeo_mode'     => [ 'type' => 'string' ],
                            ],
                        ] ] ],
                    ],
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/Term' ) ],
                ],
            ],
            '/faqs' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Toàn bộ FAQ trên site (post + term)',
                    'operationId' => 'listFaqs',
                    'parameters'  => [
                        self::param( 'search', 'query', 'Từ khóa trong câu hỏi/trả lời' ),
                        self::param( 'source_type', 'query', 'post | term' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/keywords' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Kho từ khóa + cờ cannibalization',
                    'operationId' => 'listKeywords',
                    'parameters'  => [ self::param( 'search', 'query', 'Lọc theo từ khóa' ) ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/Keyword' ] ] ) ],
                ],
            ],
            '/related' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Bài liên quan + gợi ý internal link',
                    'operationId' => 'getRelated',
                    'parameters'  => [ self::param( 'post_id', 'query', 'Post ID', 'integer', [ 'required' => true ] ) ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/analysis' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Phân tích SEO + GEO/AEO trực tiếp',
                    'operationId' => 'getAnalysis',
                    'parameters'  => [
                        self::param( 'post_id', 'query', 'Post ID', 'integer' ),
                        self::param( 'term_id', 'query', 'Term ID', 'integer' ),
                        self::param( 'taxonomy', 'query', 'Taxonomy khi dùng term_id' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/AnalysisResult' ) ],
                ],
            ],
            '/audit' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Tổng quan sức khỏe SEO toàn site (để lập plan)',
                    'operationId' => 'getAudit',
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/AuditReport' ) ],
                ],
            ],
            '/report/summary' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'KPI tổng hợp cho Report Dashboard (có cache)',
                    'operationId' => 'getReportSummary',
                    'parameters'  => [ self::param( 'refresh', 'query', 'true = bỏ cache, tính lại', 'boolean' ) ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/report/rows' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Bảng báo cáo lọc mạnh + phân trang (đọc meta đã lưu)',
                    'operationId' => 'getReportRows',
                    'parameters'  => [
                        self::param( 'post_type', 'query', 'Lọc post type' ),
                        self::param( 'taxonomy', 'query', 'Taxonomy' ),
                        self::param( 'term', 'query', 'Term ID', 'integer' ),
                        self::param( 'search', 'query', 'Từ khóa' ),
                        self::param( 'score_min', 'query', 'Điểm SEO tối thiểu', 'integer' ),
                        self::param( 'score_max', 'query', 'Điểm SEO tối đa', 'integer' ),
                        self::param( 'geo_min', 'query', 'Điểm GEO tối thiểu', 'integer' ),
                        self::param( 'geo_max', 'query', 'Điểm GEO tối đa', 'integer' ),
                        self::param( 'missing', 'query', 'focus_keyword | meta_desc | meta_title' ),
                        self::param( 'has_faq', 'query', 'Chỉ bài có FAQ', 'boolean' ),
                        self::param( 'has_schema', 'query', 'Chỉ bài có schema type', 'boolean' ),
                        self::param( 'orderby', 'query', 'date|modified|seo_score|geo_score|title' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤50)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/report/keywords' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Báo cáo từ khóa (phân trang) + cannibalization + bài đã gắn',
                    'operationId' => 'getReportKeywords',
                    'parameters'  => [
                        self::param( 'search', 'query', 'Lọc theo từ khóa' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤100)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/Keyword' ] ] ) ],
                ],
            ],
            '/report/geo' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Báo cáo GEO/AEO: điểm + issues rút gọn (phân trang)',
                    'operationId' => 'getReportGeo',
                    'parameters'  => [
                        self::param( 'post_type', 'query', 'Lọc post type' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤50)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/report/rescan' => [
                'post' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Chấm điểm lại theo lô nhỏ (chống treo). Lặp đến done:true',
                    'operationId' => 'postReportRescan',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'parameters'  => [
                        self::param( 'offset', 'query', 'Vị trí bắt đầu', 'integer' ),
                        self::param( 'limit', 'query', 'Số bài mỗi lô (≤10)', 'integer' ),
                        self::param( 'scope', 'query', 'missing | all' ),
                        self::param( 'post_type', 'query', 'Lọc post type' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/guidelines' => [
                'get' => [
                    'tags'        => [ 'AI Context' ],
                    'summary'     => 'Luật viết: SEO rules, GEO/AEO, an toàn y khoa, format, workflow',
                    'operationId' => 'getGuidelines',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/schema-types' => [
                'get' => [
                    'tags'        => [ 'AI Context' ],
                    'summary'     => 'Danh sách schema type (post/term) + khi nào dùng + field',
                    'operationId' => 'getSchemaTypes',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/blocks' => [
                'get' => [
                    'tags'        => [ 'AI Context' ],
                    'summary'     => 'Hợp đồng block Gutenberg của theme (kp/faq, TOC...) để AI xuất đúng',
                    'operationId' => 'getBlocks',
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/content-brief' => [
                'get' => [
                    'tags'        => [ 'AI Context' ],
                    'summary'     => 'Gói ngữ cảnh 1 lần cho 1 chủ đề (related, internal, FAQ, knowledge, schema gợi ý)',
                    'operationId' => 'getContentBrief',
                    'parameters'  => [
                        self::param( 'keyword', 'query', 'Từ khóa mục tiêu' ),
                        self::param( 'post_id', 'query', 'Hoặc lấy theo bài có sẵn', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/drafts' => [
                'post' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'AI tạo bài dạng Draft (không publish) để người duyệt',
                    'operationId' => 'postDraft',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'required'   => [ 'title' ],
                            'properties' => [
                                'title'         => [ 'type' => 'string' ],
                                'content'       => [ 'type' => 'string', 'description' => 'Gutenberg blocks hoặc HTML' ],
                                'post_type'     => [ 'type' => 'string' ],
                                'focus_keyword' => [ 'type' => 'string' ],
                                'meta_title'    => [ 'type' => 'string' ],
                                'meta_desc'     => [ 'type' => 'string' ],
                                'schema_type'   => [ 'type' => 'string' ],
                                'terms'         => [ 'type' => 'object', 'description' => '{ taxonomy: [term_id,...] }' ],
                                'faq'           => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqItem' ] ],
                            ],
                        ] ] ],
                    ],
                    'responses'   => [ '200' => self::ok_json( '#/components/schemas/PostDetail', 'Draft đã tạo (kèm edit_url)' ) ],
                ],
            ],
            '/keywords/suggestions' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Từ khóa tiềm năng do cron sinh (gap/covered), phân trang',
                    'operationId' => 'getKeywordSuggestions',
                    'parameters'  => [
                        self::param( 'search', 'query', 'Lọc theo từ khóa' ),
                        self::param( 'only', 'query', 'gap | covered' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤100)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/keywords/expand' => [
                'post' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Chạy mở rộng từ khóa ngay (thay vì đợi cron)',
                    'operationId' => 'postKeywordExpand',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/keyword-map' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Bản đồ từ khóa: nodes + edges (keyword/post/topic), có cap',
                    'operationId' => 'getKeywordMap',
                    'parameters'  => [
                        self::param( 'taxonomy', 'query', 'Lọc taxonomy' ),
                        self::param( 'term', 'query', 'Lọc term ID', 'integer' ),
                        self::param( 'min_usage', 'query', 'Số bài tối thiểu dùng keyword', 'integer' ),
                        self::param( 'include_suggested', 'query', 'Kèm từ khóa gap/gợi ý', 'boolean' ),
                        self::param( 'top', 'query', 'Giới hạn số keyword (≤500)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/keywords/seeds' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Danh sách seed để mở rộng (focus keyword + term dịch vụ)',
                    'operationId' => 'getKeywordSeeds',
                    'parameters'  => [
                        self::param( 'search', 'query', 'Lọc seed' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤200)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/keywords/expand-seed' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Mở rộng 1 seed thành cây phân cấp L1→L4 (nodes+edges, covered/gap, intent)',
                    'operationId' => 'getKeywordExpandSeed',
                    'parameters'  => [
                        self::param( 'keyword', 'query', 'Seed cần mở rộng', 'string', [ 'required' => true ] ),
                        self::param( 'depth', 'query', 'Độ sâu 2..4 (mặc định 4)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/report/fixes' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Bài cần sửa + gợi ý sửa cụ thể (sắp theo mức tác động)',
                    'operationId' => 'getReportFixes',
                    'parameters'  => [
                        self::param( 'post_type', 'query', 'Lọc post type' ),
                        self::param( 'issue', 'query', 'Lọc theo mã lỗi (vd image_alt, content_length)' ),
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤50)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/report/fixes/summary' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Top lỗi phổ biến toàn site (số bài + gợi ý), có cache',
                    'operationId' => 'getReportFixesSummary',
                    'parameters'  => [ self::param( 'refresh', 'query', 'true = tính lại', 'boolean' ) ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
            '/report/duplicates' => [
                'get' => [
                    'tags'        => [ 'SEO Intelligence' ],
                    'summary'     => 'Cặp bài trùng nội dung + % giống + lý do',
                    'operationId' => 'getReportDuplicates',
                    'parameters'  => [
                        self::param( 'page', 'query', 'Trang', 'integer' ),
                        self::param( 'per_page', 'query', 'Số/trang (≤100)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ] ) ],
                ],
            ],
            '/report/duplicates/scan' => [
                'post' => [
                    'tags'        => [ 'Write' ],
                    'summary'     => 'Quét trùng lặp theo lô (tính SimHash). Lặp đến done:true',
                    'operationId' => 'postReportDuplicatesScan',
                    'security'    => [ [ 'ApiKeyAuth' => [] ], [ 'BearerAuth' => [] ] ],
                    'parameters'  => [
                        self::param( 'offset', 'query', 'Vị trí bắt đầu', 'integer' ),
                        self::param( 'limit', 'query', 'Số bài mỗi lô (≤20)', 'integer' ),
                    ],
                    'responses'   => [ '200' => self::ok_json( [ 'type' => 'object' ] ) ],
                ],
            ],
        ];
    }
}
