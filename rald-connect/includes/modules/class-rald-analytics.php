<?php
/**
 * RALD Connect — RALDTICS Analytics Module
 *
 * Injects tracking script and proxies beacon events to RALD analytics.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Analytics {

    private static ?Rald_Analytics $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts',    [ $this, 'inject_tracking_script' ] );
        add_action( 'rest_api_init',          [ $this, 'register_routes' ] );
    }

    public function inject_tracking_script(): void {
        $enabled = (bool) get_option( 'rald_raldtics_enabled', false );
        if ( ! $enabled ) {
            return;
        }

        $site_id = sanitize_text_field( get_option( 'rald_raldtics_site_id', '' ) );
        if ( empty( $site_id ) ) {
            return;
        }

        $beacon_url = rest_url( 'rald-connect/v1/analytics/beacon' );

        wp_add_inline_script(
            'jquery-core',
            $this->get_tracking_script( $site_id, $beacon_url ),
            'after'
        );
    }

    private function get_tracking_script( string $site_id, string $beacon_url ): string {
        return sprintf(
            '(function(w,d){
                var rc=w.raldConnect=w.raldConnect||{};
                rc.siteId=%s;
                rc.beaconUrl=%s;
                rc.track=function(event,props){
                    var data=Object.assign({site_id:rc.siteId,event:event,url:w.location.href,ref:d.referrer,ts:Date.now()},props||{});
                    navigator.sendBeacon?navigator.sendBeacon(rc.beaconUrl,JSON.stringify(data)):fetch(rc.beaconUrl,{method:"POST",body:JSON.stringify(data),keepalive:true});
                };
                d.addEventListener("DOMContentLoaded",function(){rc.track("pageview");});
            })(window,document);',
            wp_json_encode( $site_id ),
            wp_json_encode( $beacon_url )
        );
    }

    public function register_routes(): void {
        register_rest_route(
            'rald-connect/v1',
            '/analytics/beacon',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_beacon' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_beacon( WP_REST_Request $request ): WP_REST_Response {
        $body    = $request->get_body();
        $payload = json_decode( $body, true );

        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [ 'ok' => false ], 400 );
        }

        $payload = $this->sanitize_beacon( $payload );
        $this->forward_to_raldtics( $payload );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    private function sanitize_beacon( array $payload ): array {
        return [
            'site_id' => sanitize_text_field( $payload['site_id'] ?? '' ),
            'event'   => sanitize_text_field( $payload['event'] ?? 'pageview' ),
            'url'     => esc_url_raw( $payload['url'] ?? '' ),
            'ref'     => esc_url_raw( $payload['ref'] ?? '' ),
            'ts'      => absint( $payload['ts'] ?? time() * 1000 ),
        ];
    }

    private function forward_to_raldtics( array $payload ): void {
        $api_base = rtrim( get_option( 'rald_api_url', 'https://api.rald.cloud' ), '/' );
        $api_key  = get_option( 'rald_connect_api_key', '' );

        if ( empty( $api_key ) ) {
            return;
        }

        wp_remote_post(
            $api_base . '/analytics/beacon',
            [
                'timeout'     => 2,
                'blocking'    => false,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'        => wp_json_encode( $payload ),
                'data_format' => 'body',
            ]
        );
    }
}
