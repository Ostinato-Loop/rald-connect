<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API endpoints for RALD auth.
 * Namespace: rald-connect/v1
 *
 * POST /wp-json/rald-connect/v1/auth/login
 * POST /wp-json/rald-connect/v1/auth/register
 * POST /wp-json/rald-connect/v1/auth/logout
 * GET  /wp-json/rald-connect/v1/auth/me
 */
class RALD_REST_Auth extends WP_REST_Controller {

    protected $namespace = 'rald-connect/v1';
    protected $rest_base = 'auth';

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/login', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'login' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'    => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'password' => [ 'required' => true,  'type' => 'string' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/register', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'register_user' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'    => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'password' => [ 'required' => true,  'type' => 'string' ],
                'name'     => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/logout', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'logout' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'me' ],
            'permission_callback' => 'is_user_logged_in',
        ] );
    }

    public function login( WP_REST_Request $request ): WP_REST_Response {
        $email    = $request->get_param( 'email' );
        $password = $request->get_param( 'password' );

        $result = RALD_Connect::identity()->login( $email, $password );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result );
        }

        $token     = $result['token'] ?? '';
        $rald_user = $result['user']  ?? [];

        $wp_user = RALD_User_Sync::sync( $rald_user, $token );
        if ( is_wp_error( $wp_user ) ) {
            return $this->error_response( $wp_user );
        }

        // Set WP auth cookie so subsequent requests are authenticated
        wp_set_auth_cookie( $wp_user->ID, true );
        wp_set_current_user( $wp_user->ID );
        do_action( 'wp_login', $wp_user->user_login, $wp_user );

        return new WP_REST_Response( [
            'success' => true,
            'user'    => $this->format_user( $wp_user, $rald_user ),
        ], 200 );
    }

    public function register_user( WP_REST_Request $request ): WP_REST_Response {
        $email    = $request->get_param( 'email' );
        $password = $request->get_param( 'password' );
        $name     = $request->get_param( 'name' ) ?? '';

        $result = RALD_Connect::identity()->register( $email, $password, $name );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result );
        }

        $token     = $result['token'] ?? '';
        $rald_user = $result['user']  ?? [];

        $wp_user = RALD_User_Sync::sync( $rald_user, $token );
        if ( is_wp_error( $wp_user ) ) {
            return $this->error_response( $wp_user );
        }

        wp_set_auth_cookie( $wp_user->ID, true );
        wp_set_current_user( $wp_user->ID );

        return new WP_REST_Response( [
            'success' => true,
            'user'    => $this->format_user( $wp_user, $rald_user ),
        ], 201 );
    }

    public function logout( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        $token   = RALD_Token_Store::get_token( $user_id );

        if ( $token ) {
            RALD_Connect::identity()->logout( $token );
        }

        RALD_Token_Store::clear( $user_id );
        wp_logout();

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function me( WP_REST_Request $request ): WP_REST_Response {
        $user_id   = get_current_user_id();
        $wp_user   = get_user_by( 'ID', $user_id );
        $rald_user = RALD_Token_Store::get_rald_user( $user_id ) ?? [];

        return new WP_REST_Response( $this->format_user( $wp_user, $rald_user ), 200 );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function format_user( WP_User $wp_user, array $rald_user ): array {
        return [
            'wp_id'          => $wp_user->ID,
            'email'          => $wp_user->user_email,
            'name'           => $wp_user->display_name,
            'rald_id'        => $rald_user['rald_id']        ?? get_user_meta( $wp_user->ID, '_rald_id', true ),
            'role'           => $rald_user['role']           ?? 'user',
            'email_verified' => $rald_user['email_verified'] ?? false,
        ];
    }

    private function error_response( WP_Error $error ): WP_REST_Response {
        $data   = $error->get_error_data();
        $status = is_array( $data ) ? ( $data['status'] ?? 400 ) : 400;

        return new WP_REST_Response(
            [ 'success' => false, 'error' => $error->get_error_message() ],
            $status
        );
    }
}
