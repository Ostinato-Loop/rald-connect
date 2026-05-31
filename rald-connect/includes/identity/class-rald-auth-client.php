<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HTTP client for auth.rald.cloud API.
 * All outbound calls go through this class.
 */
class RALD_Auth_Client {

    private string $base_url;
    private int    $timeout = 10;

    public function __construct( string $base_url ) {
        $this->base_url = rtrim( $base_url, '/' );
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * POST /auth/login
     *
     * @return array|WP_Error
     */
    public function login( string $email, string $password ) {
        return $this->post( '/auth/login', compact( 'email', 'password' ) );
    }

    /**
     * POST /auth/register
     *
     * @return array|WP_Error
     */
    public function register( string $email, string $password, string $name ) {
        return $this->post( '/auth/register', compact( 'email', 'password', 'name' ) );
    }

    /**
     * GET /auth/me  (token verification)
     *
     * @return array|WP_Error
     */
    public function me( string $token ) {
        return $this->get( '/auth/me', $token );
    }

    /**
     * POST /sso/exchange
     *
     * @return array|WP_Error
     */
    public function sso_exchange( string $token, string $app_id, string $redirect_to = '' ) {
        return $this->post(
            '/sso/exchange',
            [ 'app_id' => $app_id, 'redirect_to' => $redirect_to ],
            $token
        );
    }

    /**
     * POST /auth/logout
     *
     * @return bool
     */
    public function logout( string $token ): bool {
        $result = $this->post( '/auth/logout', [], $token );
        return ! is_wp_error( $result );
    }

    // ── HTTP helpers ────────────────────────────────────────────────────────

    /**
     * @return array|WP_Error
     */
    private function post( string $path, array $body, string $token = '' ) {
        $args = [
            'method'  => 'POST',
            'timeout' => $this->timeout,
            'headers' => $this->headers( $token ),
            'body'    => wp_json_encode( $body ),
        ];

        $response = wp_remote_post( $this->base_url . $path, $args );
        return $this->parse( $response, $path );
    }

    /**
     * @return array|WP_Error
     */
    private function get( string $path, string $token = '' ) {
        $args = [
            'timeout' => $this->timeout,
            'headers' => $this->headers( $token ),
        ];

        $response = wp_remote_get( $this->base_url . $path, $args );
        return $this->parse( $response, $path );
    }

    private function headers( string $token = '' ): array {
        $h = [ 'Content-Type' => 'application/json' ];
        if ( $token ) {
            $h['Authorization'] = 'Bearer ' . $token;
        }
        return $h;
    }

    /**
     * @return array|WP_Error
     */
    private function parse( $response, string $path ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'rald_connect_network',
                sprintf(
                    /* translators: %s: API path */
                    __( 'RALD Connect: Network error calling %s', 'rald-connect' ),
                    $path
                )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return $body ?? [];
        }

        $message = isset( $body['error'] ) ? $body['error'] : __( 'Unknown RALD error', 'rald-connect' );
        return new WP_Error( 'rald_connect_api_' . $code, $message, [ 'status' => $code ] );
    }
}
