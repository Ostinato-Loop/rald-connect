<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core plugin class — bootstraps all modules.
 */
final class RALD_Connect {

    private static ?RALD_Connect $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void {
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/identity/interface-identity-provider.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/identity/class-rald-auth-client.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/identity/class-rald-token-store.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/identity/class-rald-identity-provider.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/modules/class-rald-user-sync.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/modules/class-rald-auth-hooks.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/modules/class-rald-sso.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/rest/class-rald-rest-auth.php';
        require_once RALD_CONNECT_PLUGIN_DIR . 'includes/rest/class-rald-rest-sso.php';

        if ( is_admin() ) {
            require_once RALD_CONNECT_PLUGIN_DIR . 'admin/class-rald-admin.php';
        }
    }

    private function init_hooks(): void {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'rest_api_init',  [ $this, 'register_rest_routes' ] );

        // Core modules
        ( new RALD_Auth_Hooks() )->register();
        ( new RALD_SSO() )->register();

        if ( is_admin() ) {
            ( new RALD_Admin() )->register();
        }
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'rald-connect',
            false,
            dirname( plugin_basename( RALD_CONNECT_PLUGIN_DIR . 'rald-connect.php' ) ) . '/languages'
        );
    }

    public function register_rest_routes(): void {
        ( new RALD_REST_Auth() )->register_routes();
        ( new RALD_REST_SSO() )->register_routes();
    }

    /**
     * Get the configured RALD Auth client.
     */
    public static function auth_client(): RALD_Auth_Client {
        static $client = null;
        if ( null === $client ) {
            $client = new RALD_Auth_Client(
                get_option( 'rald_connect_auth_url', RALD_CONNECT_AUTH_URL )
            );
        }
        return $client;
    }

    /**
     * Get the identity provider.
     */
    public static function identity(): RALD_Identity_Provider {
        static $provider = null;
        if ( null === $provider ) {
            $provider = new RALD_Identity_Provider( self::auth_client() );
        }
        return $provider;
    }
}
