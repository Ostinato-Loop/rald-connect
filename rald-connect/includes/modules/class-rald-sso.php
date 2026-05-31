<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SSO module — handles redirect to RALD apps using session token exchange.
 */
class RALD_SSO {

    public function register(): void {
        add_action( 'init', [ $this, 'handle_sso_redirect' ] );
        add_shortcode( 'rald_sso_button', [ $this, 'sso_button_shortcode' ] );
    }

    /**
     * Handle ?rald_sso_to=<app_id> query parameter.
     * Exchanges current user's token and redirects to the target app.
     */
    public function handle_sso_redirect(): void {
        if ( ! isset( $_GET['rald_sso_to'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            wp_redirect( wp_login_url( $current_url ) );
            exit;
        }

        $app_id      = sanitize_text_field( wp_unslash( $_GET['rald_sso_to'] ) );
        $redirect_to = isset( $_GET['redirect_to'] )
            ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) )
            : '';

        $wp_user_id = get_current_user_id();
        $token      = RALD_Token_Store::get_token( $wp_user_id );

        if ( ! $token ) {
            wp_die(
                esc_html__( 'RALD session expired. Please log in again.', 'rald-connect' ),
                esc_html__( 'Session Expired', 'rald-connect' ),
                [ 'response' => 401, 'link_url' => wp_login_url(), 'link_text' => __( 'Login', 'rald-connect' ) ]
            );
        }

        $result = RALD_Connect::identity()->sso_exchange( $token, $app_id, $redirect_to );

        if ( is_wp_error( $result ) ) {
            wp_die(
                esc_html( $result->get_error_message() ),
                esc_html__( 'SSO Error', 'rald-connect' ),
                [ 'response' => 500 ]
            );
        }

        $redirect_url = $result['redirect_url'] ?? '';
        if ( ! $redirect_url ) {
            wp_die(
                esc_html__( 'RALD SSO did not return a redirect URL.', 'rald-connect' ),
                esc_html__( 'SSO Error', 'rald-connect' )
            );
        }

        wp_redirect( esc_url_raw( $redirect_url ) );
        exit;
    }

    /**
     * [rald_sso_button app_id="rald-app" label="Open RALD App"]
     */
    public function sso_button_shortcode( array $atts ): string {
        $atts = shortcode_atts( [
            'app_id'      => 'rald-app',
            'label'       => __( 'Open in RALD', 'rald-connect' ),
            'redirect_to' => '',
            'class'       => '',
        ], $atts, 'rald_sso_button' );

        if ( ! is_user_logged_in() ) {
            return '';
        }

        $url = add_query_arg( [
            'rald_sso_to' => esc_attr( $atts['app_id'] ),
            'redirect_to' => esc_attr( $atts['redirect_to'] ),
        ], home_url( '/' ) );

        $class = 'rald-sso-button' . ( $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '' );

        ob_start();
        include RALD_CONNECT_PLUGIN_DIR . 'templates/sso-button.php';
        return ob_get_clean();
    }
}
