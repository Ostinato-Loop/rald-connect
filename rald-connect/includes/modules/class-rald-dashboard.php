<?php
/**
 * RALD Connect — Dashboard Module
 *
 * Provides real-time health status for all RALD services.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Dashboard {

    private static ?Rald_Dashboard $instance = null;

    private string $cache_key = 'rald_connect_status_cache';
    private int $cache_ttl    = 30; // seconds

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
            '/status',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_status' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function get_status( WP_REST_Request $request ): WP_REST_Response {
        $cached = wp_cache_get( $this->cache_key, 'rald_connect' );
        if ( false !== $cached ) {
            return new WP_REST_Response( $cached, 200 );
        }

        $status = $this->check_all_services();
        wp_cache_set( $this->cache_key, $status, 'rald_connect', $this->cache_ttl );

        return new WP_REST_Response( $status, 200 );
    }

    private function check_all_services(): array {
        $auth_url = rtrim( get_option( 'rald_auth_url', 'https://auth.rald.cloud' ), '/' );

        $auth_status  = $this->ping_service( $auth_url . '/health' );
        $settings_ok  = ! empty( get_option( 'rald_connect_api_key', '' ) );
        $wp_version   = get_bloginfo( 'version' );
        $plugin_ver   = RALD_CONNECT_VERSION;

        return [
            'identity'  => $auth_status,
            'raldtics'  => $this->check_raldtics(),
            'crm'       => $this->check_crm(),
            'ai'        => $this->check_ai(),
            'api'       => $auth_status,
            'settings'  => [
                'status'         => $settings_ok ? 'green' : 'amber',
                'message'        => $settings_ok ? 'Configured' : 'API key not set',
            ],
            'wordpress' => [
                'status'  => 'green',
                'version' => $wp_version,
            ],
            'plugin'    => [
                'status'  => 'green',
                'version' => $plugin_ver,
            ],
            'timestamp' => gmdate( 'c' ),
        ];
    }

    private function ping_service( string $url ): array {
        $response = wp_remote_get(
            $url,
            [
                'timeout'   => 4,
                'sslverify' => true,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'status' => 'red', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code ) {
            return [
                'status'  => 'green',
                'message' => $body['status'] ?? 'OK',
                'version' => $body['version'] ?? null,
            ];
        }

        return [ 'status' => 'amber', 'message' => 'HTTP ' . $code ];
    }

    private function check_raldtics(): array {
        $site_id = get_option( 'rald_raldtics_site_id', '' );
        if ( empty( $site_id ) ) {
            return [ 'status' => 'amber', 'message' => 'Site ID not configured' ];
        }
        return [ 'status' => 'green', 'message' => 'Configured', 'site_id' => $site_id ];
    }

    private function check_crm(): array {
        $webhook = get_option( 'rald_crm_webhook_url', '' );
        if ( empty( $webhook ) ) {
            return [ 'status' => 'amber', 'message' => 'CRM webhook not configured' ];
        }
        return [ 'status' => 'green', 'message' => 'Configured' ];
    }

    private function check_ai(): array {
        $api_key = get_option( 'rald_connect_api_key', '' );
        if ( empty( $api_key ) ) {
            return [ 'status' => 'amber', 'message' => 'API key required' ];
        }
        return [ 'status' => 'green', 'message' => 'Ready' ];
    }
}
