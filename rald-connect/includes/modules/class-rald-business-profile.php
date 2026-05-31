<?php
/**
 * RALD Connect — Business Profile Module
 *
 * Collects business data and syncs to RALD Cloud.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Business_Profile {

    private static ?Rald_Business_Profile $instance = null;

    private const OPTION_KEY = 'rald_business_profile';

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route(
            'rald-connect/v1',
            '/business',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_profile' ],
                    'permission_callback' => [ $this, 'admin_permission' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'save_profile' ],
                    'permission_callback' => [ $this, 'admin_permission' ],
                    'args'                => $this->get_schema(),
                ],
            ]
        );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function get_profile( WP_REST_Request $request ): WP_REST_Response {
        $profile = get_option( self::OPTION_KEY, [] );
        return new WP_REST_Response( $profile, 200 );
    }

    public function save_profile( WP_REST_Request $request ): WP_REST_Response {
        $profile = $this->extract_profile( $request );

        update_option( self::OPTION_KEY, $profile );
        $this->sync_to_rald_cloud( $profile );

        return new WP_REST_Response( [ 'success' => true, 'profile' => $profile ], 200 );
    }

    private function extract_profile( WP_REST_Request $request ): array {
        $fields = [
            'business_name', 'industry', 'website', 'phone',
            'email', 'country', 'state', 'city',
        ];

        $profile = [];
        foreach ( $fields as $field ) {
            $raw = $request->get_param( $field );
            if ( $raw !== null ) {
                $profile[ $field ] = in_array( $field, [ 'website' ], true )
                    ? esc_url_raw( $raw )
                    : sanitize_text_field( $raw );
            }
        }

        $profile['_updated_at'] = gmdate( 'c' );
        return $profile;
    }

    private function sync_to_rald_cloud( array $profile ): void {
        $api_base = rtrim( get_option( 'rald_api_url', 'https://api.rald.cloud' ), '/' );
        $api_key  = get_option( 'rald_connect_api_key', '' );

        if ( empty( $api_key ) ) {
            return;
        }

        wp_remote_post(
            $api_base . '/business/profile',
            [
                'timeout'     => 8,
                'blocking'    => false,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'        => wp_json_encode( array_merge( $profile, [
                    'site_url'  => home_url(),
                    'site_name' => get_bloginfo( 'name' ),
                ] ) ),
                'data_format' => 'body',
            ]
        );
    }

    private function get_schema(): array {
        return [
            'business_name' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'industry'      => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'website'       => [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
            'phone'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'email'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
            'country'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'state'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'city'          => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
        ];
    }
}
