<?php
/**
 * Shynh SEO — API Key store.
 *
 * Generates, verifies and revokes API keys used to authorize external AI/machine
 * access to the Shynh SEO REST API (`shynh-seo/v1`).
 *
 * Keys are shown to the admin exactly once at creation time. Only a keyed HMAC of
 * the full key is stored at rest, never the raw value.
 *
 * @package Shynh_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shynh_SEO_API_Keys {

    /** Option holding the array of key records (keyed by record id). */
    const OPTION_KEY = 'shynh_seo_api_keys';

    /** Option holding API-wide settings (enabled toggle, etc.). */
    const SETTINGS_KEY = 'shynh_seo_api_settings';

    /** Human/machine visible prefix on every key. */
    const KEY_PREFIX = 'shynh_sk_';

    /** Valid scopes. */
    const SCOPES = [ 'read', 'write' ];

    /**
     * Whether the REST API is globally enabled by the administrator.
     */
    public static function is_api_enabled() {
        $settings = get_option( self::SETTINGS_KEY, [] );
        return ! empty( $settings['enabled'] );
    }

    /**
     * Set the global enabled flag.
     */
    public static function set_api_enabled( $enabled ) {
        $settings            = get_option( self::SETTINGS_KEY, [] );
        $settings            = is_array( $settings ) ? $settings : [];
        $settings['enabled'] = (bool) $enabled;
        update_option( self::SETTINGS_KEY, $settings );
    }

    /**
     * All stored key records, without the secret hash.
     *
     * @return array<int,array>
     */
    public static function get_all() {
        $keys = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $keys ) ) {
            return [];
        }

        $out = [];
        foreach ( $keys as $id => $record ) {
            $out[] = [
                'id'        => $id,
                'label'     => isset( $record['label'] ) ? $record['label'] : '',
                'prefix'    => isset( $record['prefix'] ) ? $record['prefix'] : '',
                'scopes'    => isset( $record['scopes'] ) ? (array) $record['scopes'] : [],
                'created'   => isset( $record['created'] ) ? (int) $record['created'] : 0,
                'last_used' => isset( $record['last_used'] ) ? (int) $record['last_used'] : 0,
                'active'    => ! empty( $record['active'] ),
            ];
        }

        // Newest first.
        usort( $out, function ( $a, $b ) {
            return $b['created'] <=> $a['created'];
        } );

        return $out;
    }

    /**
     * Generate a new API key.
     *
     * @param string   $label  Human label.
     * @param string[] $scopes Requested scopes (subset of self::SCOPES).
     * @return array{id:string,key:string,scopes:array} The full raw key is returned ONCE.
     */
    public static function generate( $label, array $scopes ) {
        $scopes = array_values( array_intersect( self::SCOPES, array_map( 'sanitize_key', $scopes ) ) );
        if ( empty( $scopes ) ) {
            $scopes = [ 'read' ];
        }

        // 40 hex chars of cryptographically-secure randomness.
        $secret   = bin2hex( random_bytes( 20 ) );
        $full_key = self::KEY_PREFIX . $secret;
        $id       = 'k_' . bin2hex( random_bytes( 8 ) );

        $keys = get_option( self::OPTION_KEY, [] );
        $keys = is_array( $keys ) ? $keys : [];

        $keys[ $id ] = [
            'label'     => sanitize_text_field( $label ),
            'prefix'    => substr( $full_key, 0, 16 ), // e.g. shynh_sk_ab12cd
            'hash'      => self::hash_key( $full_key ),
            'scopes'    => $scopes,
            'created'   => time(),
            'last_used' => 0,
            'active'    => true,
        ];

        update_option( self::OPTION_KEY, $keys );

        return [
            'id'     => $id,
            'key'    => $full_key,
            'scopes' => $scopes,
        ];
    }

    /**
     * Revoke (delete) a key by record id.
     */
    public static function revoke( $id ) {
        $keys = get_option( self::OPTION_KEY, [] );
        if ( is_array( $keys ) && isset( $keys[ $id ] ) ) {
            unset( $keys[ $id ] );
            update_option( self::OPTION_KEY, $keys );
            return true;
        }
        return false;
    }

    /**
     * Verify a raw key. On success, updates last_used and returns the record
     * (including id + scopes). Returns null on failure.
     *
     * @param string $raw_key
     * @return array|null
     */
    public static function verify( $raw_key ) {
        $raw_key = is_string( $raw_key ) ? trim( $raw_key ) : '';
        if ( '' === $raw_key || 0 !== strpos( $raw_key, self::KEY_PREFIX ) ) {
            return null;
        }

        $candidate_hash = self::hash_key( $raw_key );

        $keys = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $keys ) ) {
            return null;
        }

        foreach ( $keys as $id => $record ) {
            if ( empty( $record['active'] ) || empty( $record['hash'] ) ) {
                continue;
            }
            if ( hash_equals( (string) $record['hash'], $candidate_hash ) ) {
                // Update last_used (throttled to once per minute to limit writes).
                if ( time() - (int) ( $record['last_used'] ?? 0 ) > 60 ) {
                    $keys[ $id ]['last_used'] = time();
                    update_option( self::OPTION_KEY, $keys );
                }

                return [
                    'id'     => $id,
                    'label'  => isset( $record['label'] ) ? $record['label'] : '',
                    'scopes' => isset( $record['scopes'] ) ? (array) $record['scopes'] : [],
                ];
            }
        }

        return null;
    }

    /**
     * Deterministic keyed hash used for storage + comparison.
     */
    private static function hash_key( $raw_key ) {
        return hash_hmac( 'sha256', $raw_key, wp_salt( 'auth' ) );
    }
}
