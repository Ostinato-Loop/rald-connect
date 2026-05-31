<?php
/**
 * Plugin Name: RALD Connect
 * Plugin URI:  https://rald.cloud/connect
 * Description: Official bridge between WordPress and the RALD ecosystem. Identity, analytics, lead capture, AI SEO, and business profile — powered by RALD Cloud.
 * Version:     1.0.0
 * Author:      RALD (LILCKY STUDIO LIMITED)
 * Author URI:  https://rald.cloud
 * License:     GPL-2.0-or-later
 * Text Domain: rald-connect
 * Requires PHP: 8.0
 * Requires at least: 6.0
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RALD_CONNECT_VERSION', '1.0.0' );
define( 'RALD_CONNECT_DIR',     plugin_dir_path( __FILE__ ) );
define( 'RALD_CONNECT_URL',     plugin_dir_url( __FILE__ ) );
define( 'RALD_CONNECT_SLUG',    'rald-connect' );

final class RaldConnect {

    private static ?RaldConnect $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init(): void {
        load_plugin_textdomain( 'rald-connect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        $this->load_dependencies();
        $this->init_modules();
    }

    private function load_dependencies(): void {
        $includes = RALD_CONNECT_DIR . 'includes/';

        // Core
        require_once $includes . 'class-rald-connect-core.php';

        // Identity layer
        require_once $includes . 'identity/interface-identity-provider.php';
        require_once $includes . 'identity/class-rald-auth-client.php';
        require_once $includes . 'identity/class-rald-token-store.php';
        require_once $includes . 'identity/class-rald-identity-provider.php';

        // Auth hooks & SSO
        require_once $includes . 'modules/class-rald-auth-hooks.php';
        require_once $includes . 'modules/class-rald-sso.php';
        require_once $includes . 'modules/class-rald-user-sync.php';

        // Dashboard
        require_once $includes . 'modules/class-rald-dashboard.php';

        // Analytics (RALDTICS)
        require_once $includes . 'modules/class-rald-analytics.php';

        // Lead Capture
        require_once $includes . 'modules/class-rald-lead-capture.php';

        // AI SEO
        require_once $includes . 'modules/class-rald-ai-seo.php';

        // Business Profile
        require_once $includes . 'modules/class-rald-business-profile.php';

        // REST API
        require_once $includes . 'rest/class-rald-rest-auth.php';
        require_once $includes . 'rest/class-rald-rest-sso.php';

        // Admin & Settings
        require_once $includes . 'class-rald-settings.php';
        require_once $includes . 'admin/class-rald-admin.php';
    }

    private function init_modules(): void {
        // Always-on: settings, admin, dashboard
        Rald_Settings::get_instance();
        Rald_Admin::get_instance();
        Rald_Dashboard::get_instance();

        // Identity (always active)
        Rald_Auth_Hooks::get_instance();
        Rald_Sso::get_instance();
        Rald_User_Sync::get_instance();

        // REST (always active)
        Rald_Rest_Auth::get_instance();
        Rald_Rest_Sso::get_instance();

        // Analytics — conditional on setting
        if ( get_option( 'rald_raldtics_enabled', false ) ) {
            Rald_Analytics::get_instance();
        }

        // Lead Capture (always active — shortcodes only register when used)
        Rald_Lead_Capture::get_instance();

        // AI SEO — conditional on setting
        if ( get_option( 'rald_ai_seo_enabled', false ) ) {
            Rald_Ai_Seo::get_instance();
        }

        // Business Profile (always active for admin)
        Rald_Business_Profile::get_instance();
    }

    public static function activate(): void {
        $defaults = [
            'rald_auth_url'         => 'https://auth.rald.cloud',
            'rald_api_url'          => 'https://api.rald.cloud',
            'rald_sso_enabled'      => true,
            'rald_replace_wp_login' => false,
            'rald_raldtics_enabled' => false,
            'rald_ai_seo_enabled'   => false,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }

        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'rald_retry_lead_dispatch' );
        wp_clear_scheduled_hook( 'rald_sync_sessions' );
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__,   [ 'RaldConnect', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'RaldConnect', 'deactivate' ] );

RaldConnect::get_instance();
