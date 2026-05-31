<?php
/**
 * RALD Connect — AI SEO Module
 *
 * Generates SEO titles, meta descriptions, schema, FAQs, and content suggestions
 * via RALD Cloud AI services. All AI processing is remote — no local models.
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rald_Ai_Seo {

    private static ?Rald_Ai_Seo $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes',     [ $this, 'add_meta_box' ] );
        add_action( 'save_post',          [ $this, 'save_meta_box_data' ] );
        add_action( 'rest_api_init',      [ $this, 'register_routes' ] );
        add_action( 'wp_head',            [ $this, 'output_seo_meta' ], 1 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function add_meta_box(): void {
        $enabled = (bool) get_option( 'rald_ai_seo_enabled', false );
        if ( ! $enabled ) {
            return;
        }

        add_meta_box(
            'rald-ai-seo',
            __( 'RALD AI SEO', 'rald-connect' ),
            [ $this, 'render_meta_box' ],
            [ 'post', 'page' ],
            'normal',
            'high'
        );
    }

    public function enqueue_admin_scripts( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_localize_script(
            'rald-connect-admin',
            'raldAiSeo',
            [
                'nonce'    => wp_create_nonce( 'rald_ai_seo_nonce' ),
                'url'      => rest_url( 'rald-connect/v1/ai/generate' ),
                'postId'   => get_the_ID(),
            ]
        );
    }

    public function render_meta_box( WP_Post $post ): void {
        $meta    = get_post_meta( $post->ID, '_rald_seo', true ) ?: [];
        $title   = esc_attr( $meta['title']       ?? '' );
        $desc    = esc_attr( $meta['description'] ?? '' );
        $schema  = esc_textarea( $meta['schema']  ?? '' );
        $nonce   = wp_create_nonce( 'rald_seo_save_' . $post->ID );

        include RALD_CONNECT_DIR . 'templates/ai-seo-meta-box.php';
    }

    public function save_meta_box_data( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['rald_seo_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rald_seo_nonce'] ) ), 'rald_seo_save_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $seo_data = [
            'title'       => sanitize_text_field( wp_unslash( $_POST['rald_seo_title']       ?? '' ) ),
            'description' => sanitize_text_field( wp_unslash( $_POST['rald_seo_description'] ?? '' ) ),
            'schema'      => sanitize_textarea_field( wp_unslash( $_POST['rald_seo_schema']   ?? '' ) ),
        ];

        update_post_meta( $post_id, '_rald_seo', $seo_data );
    }

    public function output_seo_meta(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        $meta    = get_post_meta( $post_id, '_rald_seo', true ) ?: [];

        if ( ! empty( $meta['title'] ) ) {
            printf( '<meta name="title" content="%s" />' . "\n", esc_attr( $meta['title'] ) );
        }
        if ( ! empty( $meta['description'] ) ) {
            printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $meta['description'] ) );
        }
        if ( ! empty( $meta['schema'] ) ) {
            printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( json_decode( $meta['schema'] ) ) );
        }
    }

    public function register_routes(): void {
        register_rest_route(
            'rald-connect/v1',
            '/ai/generate',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_generate' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'type'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'content' => [ 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ],
                    'nonce'   => [ 'required' => true ],
                ],
            ]
        );
    }

    public function check_permission( WP_REST_Request $request ): bool {
        return wp_verify_nonce( $request->get_param( 'nonce' ), 'rald_ai_seo_nonce' )
            && current_user_can( 'edit_posts' );
    }

    public function handle_generate( WP_REST_Request $request ): WP_REST_Response {
        $type    = $request->get_param( 'type' );
        $content = $request->get_param( 'content' );

        $valid_types = [ 'title', 'description', 'schema', 'faqs', 'suggestions' ];
        if ( ! in_array( $type, $valid_types, true ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid generation type.' ], 400 );
        }

        $result = $this->call_rald_ai( $type, $content );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 502 );
        }

        return new WP_REST_Response( $result, 200 );
    }

    private function call_rald_ai( string $type, string $content ): array|WP_Error {
        $api_base = rtrim( get_option( 'rald_api_url', 'https://api.rald.cloud' ), '/' );
        $api_key  = get_option( 'rald_connect_api_key', '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'RALD API key not configured.', 'rald-connect' ) );
        }

        $response = wp_remote_post(
            $api_base . '/ai/seo',
            [
                'timeout'     => 20,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'        => wp_json_encode( [ 'type' => $type, 'content' => $content ] ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error( 'ai_error', $body['error'] ?? 'AI service error.' );
        }

        return $body;
    }
}
