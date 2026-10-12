<?php

class Shynh_SEO_Block_Validator {
    private $manifest;
    private $core_allow_list = [
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/image',
        'core/group',
        'core/buttons',
        'core/quote',
        'core/html'
    ];
    private $warnings = [];
    private $dropped = [];

    public function __construct( array $manifest ) {
        $this->manifest = $manifest;
    }

    /**
     * @param string $markup  Block markup
     * @return array
     */
    public function validate_and_repair( string $markup ): array {
        $this->warnings = [];
        $this->dropped = [];

        $blocks = parse_blocks( $markup );
        $cleaned_blocks = $this->clean_blocks( $blocks );
        
        $repaired_markup = serialize_blocks( $cleaned_blocks );

        // Content safety check
        $this->check_content_safety( $repaired_markup );

        return [
            'markup'   => $repaired_markup,
            'valid'    => empty($this->warnings) && empty($this->dropped),
            'warnings' => $this->warnings,
            'dropped'  => $this->dropped
        ];
    }

    private function clean_blocks( array $blocks ): array {
        $cleaned = [];
        foreach ( $blocks as $block ) {
            $cleaned_block = $this->clean_block( $block );
            if ( $cleaned_block !== null ) {
                $cleaned[] = $cleaned_block;
            }
        }
        return $cleaned;
    }

    private function clean_block( array $block ): ?array {
        $name = $block['blockName'];

        if ( $name === null ) {
            // Raw HTML block
            if ( trim( $block['innerHTML'] ) === '' ) {
                return null; // drop empty
            }
            return $block;
        }

        $is_core = in_array( $name, $this->core_allow_list, true );
        $in_manifest = isset( $this->manifest['blocks'][$name] );

        if ( ! $is_core && ! $in_manifest ) {
            $this->dropped[] = [
                'block'  => $name,
                'reason' => 'Block not in allow-list or manifest.'
            ];
            return null;
        }

        if ( $in_manifest ) {
            $manifest_def = $this->manifest['blocks'][$name];
            
            // Clean attributes
            if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
                $block['attrs'] = $this->clean_attributes( $name, $block['attrs'], $manifest_def['attributes'] ?? [] );
            }

            // Check serialization and inner blocks
            $serialization = $manifest_def['serialization'] ?? 'unknown';
            $allowed_inner = $manifest_def['allowedInnerBlocks'] ?? null;

            if ( $serialization === 'self-closing' && !empty( $block['innerBlocks'] ) ) {
                $this->warnings[] = "Block {$name} is self-closing but contains inner blocks. Inner blocks dropped.";
                $block['innerBlocks'] = [];
            } elseif ( $serialization === 'inner-blocks' && !empty( $block['innerBlocks'] ) ) {
                $valid_inner = [];
                foreach ( $block['innerBlocks'] as $inner ) {
                    $inner_name = $inner['blockName'];
                    if ( $allowed_inner !== null && $inner_name !== null && !in_array( $inner_name, $allowed_inner, true ) ) {
                        $this->warnings[] = "Block {$inner_name} is not allowed inside {$name}. Dropped.";
                        $this->dropped[] = [
                            'block'  => $name,
                            'child'  => $inner_name,
                            'reason' => 'Not in allowedInnerBlocks.'
                        ];
                        continue;
                    }
                    $cleaned_inner = $this->clean_block( $inner );
                    if ( $cleaned_inner !== null ) {
                        $valid_inner[] = $cleaned_inner;
                    }
                }
                $block['innerBlocks'] = $valid_inner;
            } else {
                $block['innerBlocks'] = $this->clean_blocks( $block['innerBlocks'] ?? [] );
            }
        } else {
            // Core block, just recurse
            $block['innerBlocks'] = $this->clean_blocks( $block['innerBlocks'] ?? [] );
        }

        return $block;
    }

    private function clean_attributes( string $block_name, array $attrs, array $manifest_attrs ): array {
        $cleaned = [];
        foreach ( $attrs as $key => $value ) {
            if ( ! isset( $manifest_attrs[$key] ) ) {
                $this->warnings[] = "Dropped unknown attribute {$key} in block {$block_name}.";
                $this->dropped[] = [
                    'block' => $block_name,
                    'attr'  => $key,
                    'reason'=> 'Unknown attribute.'
                ];
                continue;
            }

            $def = $manifest_attrs[$key];
            $type = $def['type'] ?? 'string';
            $default = $def['default'] ?? null;

            // Enforce basic types
            if ( $type === 'boolean' ) {
                $value = (bool) $value;
            } elseif ( $type === 'number' || $type === 'integer' ) {
                $value = is_numeric( $value ) ? (float) $value : $default;
            } elseif ( $type === 'string' ) {
                $value = (string) $value;
            }

            // Enum check
            if ( isset( $def['enum'] ) && is_array( $def['enum'] ) ) {
                if ( ! in_array( $value, $def['enum'], true ) ) {
                    $this->warnings[] = "Invalid enum value '{$value}' for attribute {$key} in {$block_name}. Reverting to default.";
                    $value = $default;
                }
            }

            // Array / Item shape check
            if ( $type === 'array' && is_array( $value ) && isset( $def['itemShape'] ) ) {
                $valid_items = [];
                foreach ( $value as $item ) {
                    if ( ! is_array( $item ) ) continue;
                    
                    $valid_item = [];
                    $is_valid = true;
                    
                    foreach ( $def['itemShape'] as $shape_key => $shape_def ) {
                        if ( ! isset( $item[$shape_key] ) ) {
                            if ( !empty( $shape_def['required'] ) ) {
                                $is_valid = false;
                                break;
                            }
                            $valid_item[$shape_key] = $shape_def['default'] ?? null;
                        } else {
                            $valid_item[$shape_key] = $item[$shape_key];
                            // Basic type casting could go here for shape
                            if (isset($shape_def['type'])) {
                                if ($shape_def['type'] === 'boolean') $valid_item[$shape_key] = (bool)$valid_item[$shape_key];
                                if ($shape_def['type'] === 'string') $valid_item[$shape_key] = (string)$valid_item[$shape_key];
                            }
                        }
                    }
                    if ( $is_valid ) {
                        $valid_items[] = $valid_item;
                    } else {
                        $this->warnings[] = "Dropped array item in attribute {$key} of block {$block_name} due to missing required fields.";
                    }
                }
                $value = $valid_items;
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    private function check_content_safety( string $markup ): void {
        $patterns = [
            '/100%\s*an\s*toàn/iu' => 'Claim y tế: "100% an toàn"',
            '/cam\s*kết.*(khỏi|hết)/iu' => 'Claim y tế: "cam kết khỏi/hết"',
            '/vĩnh\s*viễn.*không/iu' => 'Claim y tế: "vĩnh viễn không"'
        ];

        foreach ( $patterns as $regex => $reason ) {
            if ( preg_match( $regex, $markup ) ) {
                $this->warnings[] = "Vi phạm safety: {$reason}. Vui lòng tự review và sửa đổi nội dung.";
            }
        }
    }
}
