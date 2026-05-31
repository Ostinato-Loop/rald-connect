<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST SSO endpoint.
 * POST /wp-json/rald-connect/v1/sso/exchange
 */
class RALD_REST_SSO extends WP_REST_Controller {

    protected $namespace = 'rald-connect/v1';
    protected $rest_base = 'sso';

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/exchange', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'exchange' ],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'app_id'      => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'redirect_to' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
            ],
        ] );
    }

    public function exchange( WP_REST_Request $request ): WP_REST_Response {
        $user_id     = get_current_user_id();
        $token       = RALD_Token_Store::get_token( $user_id );
        $app_id      = $request->get_param( 'app_id' );
        $redirect_to = $request->get_param( 'redirect_to' ) ?? '';

        if ( ! $token ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => __( 'RALD session expired. Please log in again.', 'rald-connect' ) ],
                401
            );
        }

        $result = RALD_Connect::identity()->sso_exchange( $token, $app_id, $redirect_to );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => $result->get_error_message() ],
                500
            );
        }

        return new WP_REST_Response( [
            'success'      => true,
            'redirect_url' => $result['redirect_url'] ?? '',
            'token'        => $result['token']        ?? '',
            'expires_at'   => $result['expires_at']   ?? '',
        ], 200 );
    }
}
