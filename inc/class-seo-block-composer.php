<?php

class Shynh_SEO_Block_Composer {
    private $manifest;
    private $validator;
    
    public const ALLOWED_BLOCKS_BY_CONTEXT = [
        'article' => ['kp/section-heading', 'kp/premium-split', 'kp/faq', 'kp/related-posts', 'core/paragraph', 'core/heading', 'core/list', 'core/image'],
        'service' => ['kp/section-heading', 'kp/premium-split', 'kp/service-grid', 'kp/pricing-plan', 'kp/faq', 'kp/gallery-lightbox']
    ];

    public function __construct( array $manifest, Shynh_SEO_Block_Validator $validator ) {
        $this->manifest = $manifest;
        $this->validator = $validator;
    }

    /**
     * Helper to build JSON schema for the AI prompt based on allowed context.
     * @param string $context 
     * @return array
     */
    public function build_schema_for_context( string $context ): array {
        $allowed = self::ALLOWED_BLOCKS_BY_CONTEXT[$context] ?? [];
        $block_schemas = [];

        foreach ( $allowed as $block_name ) {
            if ( isset( $this->manifest['blocks'][$block_name] ) ) {
                $block_schemas[] = $this->build_block_schema( $block_name, $this->manifest['blocks'][$block_name] );
            } elseif ( strpos( $block_name, 'core/' ) === 0 ) {
                // Core blocks schema
                $block_schemas[] = [
                    'type' => 'object',
                    'properties' => [
                        'name' => [ 'type' => 'string', 'enum' => [$block_name] ],
                        'innerHTML' => [ 'type' => 'string', 'description' => 'Raw HTML content for the block' ]
                    ],
                    'required' => ['name', 'innerHTML']
                ];
            }
        }

        return [
            'type' => 'object',
            'properties' => [
                'blocks' => [
                    'type' => 'array',
                    'items' => [
                        'anyOf' => $block_schemas
                    ]
                ]
            ],
            'required' => ['blocks'],
            'additionalProperties' => false
        ];
    }

    private function build_block_schema( string $name, array $def ): array {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [ 'type' => 'string', 'enum' => [$name] ]
            ],
            'required' => ['name']
        ];

        if ( !empty( $def['attributes'] ) ) {
            $attrs_schema = [ 'type' => 'object', 'properties' => [] ];
            foreach ( $def['attributes'] as $attr_key => $attr_def ) {
                $prop = [ 'type' => $attr_def['type'] ?? 'string' ];
                if ( isset( $attr_def['enum'] ) ) {
                    $prop['enum'] = $attr_def['enum'];
                }
                if ( isset( $attr_def['itemShape'] ) && $prop['type'] === 'array' ) {
                    $prop['items'] = [
                        'type' => 'object',
                        'properties' => []
                    ];
                    $required_items = [];
                    foreach ( $attr_def['itemShape'] as $s_key => $s_def ) {
                        $prop['items']['properties'][$s_key] = [ 'type' => $s_def['type'] ?? 'string' ];
                        if ( !empty( $s_def['required'] ) ) {
                            $required_items[] = $s_key;
                        }
                    }
                    if ( !empty( $required_items ) ) {
                        $prop['items']['required'] = $required_items;
                    }
                }
                $attrs_schema['properties'][$attr_key] = $prop;
            }
            $schema['properties']['attributes'] = $attrs_schema;
        }
        
        if ( isset( $def['serialization'] ) && $def['serialization'] === 'inner-blocks' ) {
            $schema['properties']['innerBlocks'] = [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'description' => 'Recursive block structures goes here'
                ]
            ];
        }

        return $schema;
    }

    /**
     * Converts AI generated JSON array into WP Block Markup, then validates it.
     * @param array $ai_json The decoded JSON from AI
     * @param string $context The context to enforce allow-lists
     * @return array Result from validate_and_repair()
     */
    public function compose_markup( array $ai_json, string $context ): array {
        $allowed = self::ALLOWED_BLOCKS_BY_CONTEXT[$context] ?? [];
        $blocks_tree = $this->build_wp_block_tree( $ai_json['blocks'] ?? [], $allowed );
        
        $raw_markup = serialize_blocks( $blocks_tree );
        return $this->validator->validate_and_repair( $raw_markup );
    }

    private function build_wp_block_tree( array $blocks, array $allowed ): array {
        $tree = [];
        foreach ( $blocks as $block ) {
            if ( empty( $block['name'] ) || ! in_array( $block['name'], $allowed, true ) ) {
                continue; // Drop early if not allowed in this context
            }

            $wp_block = [
                'blockName' => $block['name'],
                'attrs' => $block['attributes'] ?? [],
                'innerBlocks' => [],
                'innerHTML' => '',
                'innerContent' => []
            ];

            // If it's a core block, usually it relies on innerHTML
            if ( strpos( $block['name'], 'core/' ) === 0 && isset( $block['innerHTML'] ) ) {
                $wp_block['innerHTML'] = $block['innerHTML'];
                $wp_block['innerContent'] = [ $block['innerHTML'] ];
            } else {
                // For custom blocks
                if ( !empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                    $wp_block['innerBlocks'] = $this->build_wp_block_tree( $block['innerBlocks'], $allowed );
                }
                // Custom dynamic blocks usually have empty innerHTML but require [null] for innerContent matching innerBlocks count + 1 for wrappers if needed.
                // serialize_blocks expects innerContent to contain strings for HTML parts, and null for innerBlock placeholders.
                // For a dynamic block with inner blocks, standard is: array(null, null...)
                if ( !empty( $wp_block['innerBlocks'] ) ) {
                    $inner_content = [];
                    // Very simplistic representation. If the block has a save template, it might require wrapping HTML.
                    // But since we rely on dynamic render and validator, a sequence of nulls is standard for "just inner blocks".
                    for ( $i = 0; $i < count( $wp_block['innerBlocks'] ); $i++ ) {
                        $inner_content[] = null;
                    }
                    $wp_block['innerContent'] = $inner_content;
                }
            }

            $tree[] = $wp_block;
        }

        return $tree;
    }
}
