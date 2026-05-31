<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin settings page.
 * Renders a React app for the settings UI.
 * Stores settings in wp_options with prefix rald_connect_.
 */
class RALD_Admin {

    private const OPTION_GROUP = 'rald_connect_settings';
    private const PAGE_SLUG    = 'rald-connect';

    public function register(): void {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'rest_api_init', [ $this, 'register_settings_api' ] );
    }

    public function add_menu(): void {
        add_options_page(
            __( 'RALD Connect', 'rald-connect' ),
            __( 'RALD Connect', 'rald-connect' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        echo '<div id="rald-connect-admin"></div>';
    }

    public function register_settings(): void {
        $options = [
            'rald_connect_auth_url',
            'rald_connect_api_url',
            'rald_connect_login_page_id',
            'rald_connect_register_page_id',
            'rald_connect_analytics_enabled',
            'rald_connect_app_id',
        ];

        foreach ( $options as $option ) {
            register_setting( self::OPTION_GROUP, $option, [ 'sanitize_callback' => 'sanitize_text_field' ] );
        }
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        $asset_dir = RALD_CONNECT_PLUGIN_URL . 'admin/js/dist/';

        wp_enqueue_script(
            'rald-connect-admin',
            $asset_dir . 'admin.js',
            [],
            RALD_CONNECT_VERSION,
            true
        );

        wp_enqueue_style(
            'rald-connect-admin',
            RALD_CONNECT_PLUGIN_URL . 'admin/css/rald-admin.css',
            [],
            RALD_CONNECT_VERSION
        );

        // Pass settings to React app
        wp_localize_script( 'rald-connect-admin', 'raldConnectAdmin', [
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'apiUrl'   => rest_url( 'rald-connect/v1' ),
            'settings' => $this->get_all_settings(),
            'siteUrl'  => get_site_url(),
        ] );
    }

    /**
     * REST endpoint for admin settings CRUD.
     * GET/POST /wp-json/rald-connect/v1/admin/settings
     */
    public function register_settings_api(): void {
        register_rest_route( 'rald-connect/v1', '/admin/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_settings' ],
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            ],
        ] );
    }

    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( $this->get_all_settings(), 200 );
    }

    public function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $body     = $request->get_json_params();
        $allowed  = [
            'rald_connect_auth_url', 'rald_connect_api_url',
            'rald_connect_login_page_id', 'rald_connect_register_page_id',
            'rald_connect_analytics_enabled', 'rald_connect_app_id',
        ];

        foreach ( $allowed as $key ) {
            if ( isset( $body[ $key ] ) ) {
                update_option( $key, sanitize_text_field( $body[ $key ] ) );
            }
        }

        return new WP_REST_Response( [ 'success' => true, 'settings' => $this->get_all_settings() ], 200 );
    }

    private function get_all_settings(): array {
        return [
            'rald_connect_auth_url'              => get_option( 'rald_connect_auth_url', RALD_CONNECT_AUTH_URL ),
            'rald_connect_api_url'               => get_option( 'rald_connect_api_url',  RALD_CONNECT_API_URL ),
            'rald_connect_login_page_id'         => get_option( 'rald_connect_login_page_id', '' ),
            'rald_connect_register_page_id'      => get_option( 'rald_connect_register_page_id', '' ),
            'rald_connect_analytics_enabled'     => get_option( 'rald_connect_analytics_enabled', '1' ),
            'rald_connect_app_id'                => get_option( 'rald_connect_app_id', 'wordpress' ),
        ];
    }
}
