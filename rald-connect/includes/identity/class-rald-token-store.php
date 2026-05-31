<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Encrypted token persistence.
 * Tokens are stored in user_meta, encrypted with WordPress SECURE_AUTH_KEY.
 * Never stored in cookies or JS-accessible locations.
 */
class RALD_Token_Store {

    private const META_TOKEN   = '_rald_token';
    private const META_EXPIRY  = '_rald_token_expiry';
    private const META_USER    = '_rald_user_data';
    private const CACHE_GROUP  = 'rald_connect';

    // ── Store ──────────────────────────────────────────────────────────────

    public static function save( int $wp_user_id, string $token, array $rald_user, int $expires_in = 86400 ): void {
        $expiry = time() + $expires_in;

        update_user_meta( $wp_user_id, self::META_TOKEN,  self::encrypt( $token ) );
        update_user_meta( $wp_user_id, self::META_EXPIRY, $expiry );
        update_user_meta( $wp_user_id, self::META_USER,   wp_json_encode( $rald_user ) );

        wp_cache_set( 'token_' . $wp_user_id, $token, self::CACHE_GROUP, 300 );
    }

    // ── Retrieve ───────────────────────────────────────────────────────────

    public static function get_token( int $wp_user_id ): ?string {
        $cached = wp_cache_get( 'token_' . $wp_user_id, self::CACHE_GROUP );
        if ( $cached ) {
            return $cached;
        }

        if ( self::is_expired( $wp_user_id ) ) {
            return null;
        }

        $encrypted = get_user_meta( $wp_user_id, self::META_TOKEN, true );
        if ( ! $encrypted ) {
            return null;
        }

        $token = self::decrypt( $encrypted );
        if ( $token ) {
            wp_cache_set( 'token_' . $wp_user_id, $token, self::CACHE_GROUP, 300 );
        }
        return $token ?: null;
    }

    public static function get_rald_user( int $wp_user_id ): ?array {
        $raw = get_user_meta( $wp_user_id, self::META_USER, true );
        return $raw ? json_decode( $raw, true ) : null;
    }

    public static function is_expired( int $wp_user_id ): bool {
        $expiry = (int) get_user_meta( $wp_user_id, self::META_EXPIRY, true );
        return ! $expiry || time() >= $expiry;
    }

    // ── Clear ──────────────────────────────────────────────────────────────

    public static function clear( int $wp_user_id ): void {
        delete_user_meta( $wp_user_id, self::META_TOKEN );
        delete_user_meta( $wp_user_id, self::META_EXPIRY );
        delete_user_meta( $wp_user_id, self::META_USER );
        wp_cache_delete( 'token_' . $wp_user_id, self::CACHE_GROUP );
    }

    // ── Encryption (AES-256-CBC via WordPress SECURE_AUTH_KEY) ────────────

    private static function get_key(): string {
        $key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt( 'secure_auth' );
        return substr( hash( 'sha256', $key, true ), 0, 32 );
    }

    private static function encrypt( string $plaintext ): string {
        $key    = self::get_key();
        $iv     = random_bytes( 16 );
        $cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    private static function decrypt( string $ciphertext ): ?string {
        $key    = self::get_key();
        $raw    = base64_decode( $ciphertext, true );
        if ( ! $raw || strlen( $raw ) < 17 ) {
            return null;
        }
        $iv     = substr( $raw, 0, 16 );
        $cipher = substr( $raw, 16 );
        $plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $plain !== false ? $plain : null;
    }
}
