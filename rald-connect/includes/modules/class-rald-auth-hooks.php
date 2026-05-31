<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hooks into WordPress authentication lifecycle.
 * Intercepts login/logout, delegates to RALD Identity Provider.
 */
class RALD_Auth_Hooks {

    public function register(): void {
        add_filter( 'authenticate',      [ $this, 'authenticate' ], 30, 3 );
        add_action( 'wp_logout',         [ $this, 'on_logout' ], 10, 1 );
        add_action( 'init',              [ $this, 'maybe_refresh_token' ] );
        add_filter( 'login_url',         [ $this, 'custom_login_url' ], 10, 3 );
        add_filter( 'registration_url',  [ $this, 'custom_register_url' ] );
    }

    /**
     * Replace WP password auth with RALD auth.
     * Hooked at priority 30 (after WP default at 20).
     *
     * @param WP_User|WP_Error|null $user
     * @param string                $username  email or login
     * @param string                $password
     * @return WP_User|WP_Error
     */
    public function authenticate( $user, string $username, string $password ) {
        // If already authenticated (e.g. cookie auth) — don't interfere
        if ( $user instanceof WP_User ) {
            return $user;
        }

        // Skip if not email/password credentials
        if ( ! $username || ! $password ) {
            return $user;
        }

        // Resolve email — WP allows login by username too
        $email = is_email( $username )
            ? $username
            : ( get_user_by( 'login', $username )->user_email ?? $username );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'rald_invalid_email', __( 'Please enter a valid email address.', 'rald-connect' ) );
        }

        $result = RALD_Connect::identity()->login( $email, $password );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $token     = $result['token']   ?? '';
        $rald_user = $result['user']    ?? [];

        if ( ! $token || ! $rald_user ) {
            return new WP_Error( 'rald_bad_response', __( 'Unexpected response from RALD Identity.', 'rald-connect' ) );
        }

        $wp_user = RALD_User_Sync::sync( $rald_user, $token );

        if ( is_wp_error( $wp_user ) ) {
            return $wp_user;
        }

        return $wp_user;
    }

    /**
     * Clear RALD token on WordPress logout.
     *
     * @param int $user_id
     */
    public function on_logout( int $user_id ): void {
        $token = RALD_Token_Store::get_token( $user_id );
        if ( $token ) {
            RALD_Connect::identity()->logout( $token );
        }
        RALD_Token_Store::clear( $user_id );
    }

    /**
     * Refresh/validate token once per request for logged-in users.
     */
    public function maybe_refresh_token(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        // Skip if token is still fresh (> 1 hour remaining)
        $expiry = (int) get_user_meta( $user_id, '_rald_token_expiry', true );
        if ( $expiry && ( $expiry - time() ) > 3600 ) {
            return;
        }

        $token = RALD_Token_Store::get_token( $user_id );
        if ( ! $token ) {
            return;
        }

        // Verify token is still valid
        $result = RALD_Connect::identity()->verify_token( $token );
        if ( is_wp_error( $result ) ) {
            // Token invalid — force logout
            wp_logout();
            return;
        }

        // Update meta with fresh user data
        RALD_User_Sync::update_meta( $user_id, $result, $token );
    }

    /**
     * Redirect login URL to RALD login page (if configured).
     */
    public function custom_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        $custom = get_option( 'rald_connect_login_page_id' );
        if ( ! $custom ) {
            return $login_url;
        }

        $url = get_permalink( (int) $custom );
        if ( $url && $redirect ) {
            $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
        }
        return $url ?: $login_url;
    }

    /**
     * Redirect registration URL to RALD register page (if configured).
     */
    public function custom_register_url( string $register_url ): string {
        $custom = get_option( 'rald_connect_register_page_id' );
        if ( ! $custom ) {
            return $register_url;
        }
        return get_permalink( (int) $custom ) ?: $register_url;
    }
}
