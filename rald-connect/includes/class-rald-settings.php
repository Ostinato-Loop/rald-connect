<?php
/**
 * RALD Connect — Settings
 *
 * Manages plugin configuration and admin pages.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Settings {

    private static ?Rald_Settings $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',          [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'rest_api_init',       [ $this, 'register_settings_routes' ] );
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __( 'RALD Connect', 'rald-connect' ),
            __( 'RALD Connect', 'rald-connect' ),
            'manage_options',
            'rald-connect',
            [ $this, 'render_admin_page' ],
            $this->get_menu_icon(),
            30
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( false === strpos( $hook, 'rald-connect' ) ) {
            return;
        }

        $asset_file = RALD_CONNECT_DIR . 'admin/js/dist/rald-admin.js';
        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        wp_enqueue_script(
            'rald-connect-admin',
            RALD_CONNECT_URL . 'admin/js/dist/rald-admin.js',
            [],
            RALD_CONNECT_VERSION,
            true
        );

        if ( file_exists( RALD_CONNECT_DIR . 'admin/js/dist/rald-admin.css' ) ) {
            wp_enqueue_style(
                'rald-connect-admin',
                RALD_CONNECT_URL . 'admin/js/dist/rald-admin.css',
                [],
                RALD_CONNECT_VERSION
            );
        }

        wp_localize_script(
            'rald-connect-admin',
            'raldConnectConfig',
            [
                'nonce'     => wp_create_nonce( 'rald_connect_admin' ),
                'restUrl'   => rest_url( 'rald-connect/v1/' ),
                'authUrl'   => get_option( 'rald_auth_url', 'https://auth.rald.cloud' ),
                'apiUrl'    => get_option( 'rald_api_url',  'https://api.rald.cloud' ),
                'siteUrl'   => home_url(),
                'siteName'  => get_bloginfo( 'name' ),
                'version'   => RALD_CONNECT_VERSION,
                'settings'  => $this->get_public_settings(),
            ]
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'rald-connect' ) );
        }
        echo '<div id="rald-connect-admin"></div>';
    }

    public function register_settings_routes(): void {
        register_rest_route(
            'rald-connect/v1',
            '/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_settings' ],
                    'permission_callback' => [ $this, 'admin_permission' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'save_settings' ],
                    'permission_callback' => [ $this, 'admin_permission' ],
                ],
            ]
        );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( $this->get_all_settings(), 200 );
    }

    public function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $body = $request->get_json_params();

        $allowed = [
            'rald_auth_url'          => 'esc_url_raw',
            'rald_api_url'           => 'esc_url_raw',
            'rald_connect_api_key'   => 'sanitize_text_field',
            'rald_raldtics_enabled'  => 'boolval',
            'rald_raldtics_site_id'  => 'sanitize_text_field',
            'rald_crm_webhook_url'   => 'esc_url_raw',
            'rald_ai_seo_enabled'    => 'boolval',
            'rald_sso_enabled'       => 'boolval',
            'rald_replace_wp_login'  => 'boolval',
        ];

        foreach ( $allowed as $key => $sanitizer ) {
            if ( array_key_exists( $key, $body ) ) {
                $value = $sanitizer === 'boolval'
                    ? (bool) $body[ $key ]
                    : call_user_func( $sanitizer, $body[ $key ] );
                update_option( $key, $value );
            }
        }

        return new WP_REST_Response( [ 'success' => true, 'settings' => $this->get_all_settings() ], 200 );
    }

    private function get_all_settings(): array {
        return [
            'rald_auth_url'         => get_option( 'rald_auth_url',         'https://auth.rald.cloud' ),
            'rald_api_url'          => get_option( 'rald_api_url',          'https://api.rald.cloud' ),
            'rald_raldtics_enabled' => (bool) get_option( 'rald_raldtics_enabled', false ),
            'rald_raldtics_site_id' => get_option( 'rald_raldtics_site_id', '' ),
            'rald_crm_webhook_url'  => get_option( 'rald_crm_webhook_url',  '' ),
            'rald_ai_seo_enabled'   => (bool) get_option( 'rald_ai_seo_enabled', false ),
            'rald_sso_enabled'      => (bool) get_option( 'rald_sso_enabled', true ),
            'rald_replace_wp_login' => (bool) get_option( 'rald_replace_wp_login', false ),
            'api_key_configured'    => ! empty( get_option( 'rald_connect_api_key', '' ) ),
        ];
    }

    private function get_public_settings(): array {
        $all = $this->get_all_settings();
        unset( $all['rald_connect_api_key'] );
        return $all;
    }

    private function get_menu_icon(): string {
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>'
        );
    }
}
