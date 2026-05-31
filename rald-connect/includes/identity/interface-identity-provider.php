<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Identity provider interface.
 * Implementations: RALD_Identity_Provider
 * Future: could wrap other providers for multi-tenancy.
 */
interface RALD_Identity_Provider_Interface {

    /**
     * Authenticate a user with email + password.
     *
     * @return array{ token: string, user: array }|WP_Error
     */
    public function login( string $email, string $password );

    /**
     * Register a new user.
     *
     * @return array{ token: string, user: array }|WP_Error
     */
    public function register( string $email, string $password, string $name );

    /**
     * Verify a token and return the user payload.
     *
     * @return array|WP_Error
     */
    public function verify_token( string $token );

    /**
     * Issue an SSO exchange token for a target app.
     *
     * @return array{ token: string, redirect_url: string }|WP_Error
     */
    public function sso_exchange( string $rald_token, string $app_id, string $redirect_to );

    /**
     * Revoke a session token.
     */
    public function logout( string $token ): bool;
}
