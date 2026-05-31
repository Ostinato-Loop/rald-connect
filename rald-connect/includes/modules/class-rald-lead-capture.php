<?php
/**
 * RALD Connect — Lead Capture Module
 *
 * Provides shortcodes for contact, quote, and newsletter forms.
 * All submissions sync to RALD CRM via webhook.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Lead_Capture {

    private static ?Rald_Lead_Capture $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'rald_contact_form',    [ $this, 'render_contact_form' ] );
        add_shortcode( 'rald_quote_form',      [ $this, 'render_quote_form' ] );
        add_shortcode( 'rald_newsletter_form', [ $this, 'render_newsletter_form' ] );
        add_shortcode( 'rald_inquiry_form',    [ $this, 'render_inquiry_form' ] );
        add_action( 'rest_api_init',            [ $this, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts',       [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts(): void {
        wp_localize_script(
            'rald-connect-public',
            'raldLeads',
            [
                'nonce'  => wp_create_nonce( 'rald_lead_nonce' ),
                'url'    => rest_url( 'rald-connect/v1/leads' ),
                'labels' => [
                    'sending'  => __( 'Sending…', 'rald-connect' ),
                    'success'  => __( 'Thank you! We\'ll be in touch.', 'rald-connect' ),
                    'error'    => __( 'Something went wrong. Please try again.', 'rald-connect' ),
                ],
            ]
        );
    }

    public function register_routes(): void {
        register_rest_route(
            'rald-connect/v1',
            '/leads',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_lead_submission' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'form_type' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'name'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'email'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
                    'message'   => [ 'sanitize_callback' => 'sanitize_textarea_field' ],
                    'phone'     => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'nonce'     => [ 'required' => true ],
                ],
            ]
        );
    }

    public function handle_lead_submission( WP_REST_Request $request ): WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );
        if ( ! wp_verify_nonce( $nonce, 'rald_lead_nonce' ) ) {
            return new WP_REST_Response( [ 'error' => __( 'Invalid request.', 'rald-connect' ) ], 403 );
        }

        $lead = [
            'form_type'  => $request->get_param( 'form_type' ),
            'name'       => $request->get_param( 'name' ),
            'email'      => $request->get_param( 'email' ),
            'message'    => $request->get_param( 'message' ) ?? '',
            'phone'      => $request->get_param( 'phone' ) ?? '',
            'source_url' => sanitize_url( wp_get_referer() ?: home_url() ),
            'site_name'  => get_bloginfo( 'name' ),
            'timestamp'  => gmdate( 'c' ),
        ];

        if ( empty( $lead['email'] ) || ! is_email( $lead['email'] ) ) {
            return new WP_REST_Response( [ 'error' => __( 'Valid email required.', 'rald-connect' ) ], 422 );
        }

        $dispatched = $this->dispatch_to_crm( $lead );

        if ( ! $dispatched ) {
            $this->queue_for_retry( $lead );
        }

        return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Received. Thank you!', 'rald-connect' ) ], 200 );
    }

    private function dispatch_to_crm( array $lead ): bool {
        $webhook_url = get_option( 'rald_crm_webhook_url', '' );
        $api_key     = get_option( 'rald_connect_api_key', '' );

        if ( empty( $webhook_url ) || empty( $api_key ) ) {
            return false;
        }

        $response = wp_remote_post(
            $webhook_url,
            [
                'timeout'     => 8,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'X-RALD-Source' => 'rald-connect-wp/' . RALD_CONNECT_VERSION,
                ],
                'body'        => wp_json_encode( $lead ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'RALD Connect CRM webhook failed: ' . $response->get_error_message() );
            return false;
        }

        return wp_remote_retrieve_response_code( $response ) < 400;
    }

    private function queue_for_retry( array $lead ): void {
        $queue   = get_option( 'rald_lead_retry_queue', [] );
        $queue[] = array_merge( $lead, [ '_attempts' => 0, '_queued_at' => time() ] );
        update_option( 'rald_lead_retry_queue', $queue, false );
        wp_schedule_single_event( time() + 300, 'rald_retry_lead_dispatch' );
    }

    public function render_contact_form( array $atts ): string {
        $atts = shortcode_atts( [ 'title' => __( 'Contact Us', 'rald-connect' ), 'button' => __( 'Send Message', 'rald-connect' ) ], $atts );
        return $this->render_form( 'contact', $atts );
    }

    public function render_quote_form( array $atts ): string {
        $atts = shortcode_atts( [ 'title' => __( 'Get a Quote', 'rald-connect' ), 'button' => __( 'Request Quote', 'rald-connect' ) ], $atts );
        return $this->render_form( 'quote', $atts );
    }

    public function render_newsletter_form( array $atts ): string {
        $atts = shortcode_atts( [ 'title' => __( 'Stay Updated', 'rald-connect' ), 'button' => __( 'Subscribe', 'rald-connect' ) ], $atts );
        return $this->render_form( 'newsletter', $atts, false );
    }

    public function render_inquiry_form( array $atts ): string {
        $atts = shortcode_atts( [ 'title' => __( 'Business Inquiry', 'rald-connect' ), 'button' => __( 'Submit Inquiry', 'rald-connect' ) ], $atts );
        return $this->render_form( 'inquiry', $atts );
    }

    private function render_form( string $type, array $atts, bool $show_message = true ): string {
        $nonce     = wp_create_nonce( 'rald_lead_nonce' );
        $form_id   = 'rald-form-' . esc_attr( $type ) . '-' . wp_rand( 1000, 9999 );
        $title     = esc_html( $atts['title'] );
        $btn_label = esc_html( $atts['button'] );

        ob_start();
        include RALD_CONNECT_DIR . 'templates/lead-form.php';
        return ob_get_clean();
    }
}
