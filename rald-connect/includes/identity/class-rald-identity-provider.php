<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RALD Identity Provider — implements the identity provider interface.
 * Wraps RALD_Auth_Client with WordPress error handling.
 */
class RALD_Identity_Provider implements RALD_Identity_Provider_Interface {

    private RALD_Auth_Client $client;

    public function __construct( RALD_Auth_Client $client ) {
        $this->client = $client;
    }

    /** {@inheritdoc} */
    public function login( string $email, string $password ) {
        return $this->client->login( $email, $password );
    }

    /** {@inheritdoc} */
    public function register( string $email, string $password, string $name ) {
        return $this->client->register( $email, $password, $name );
    }

    /** {@inheritdoc} */
    public function verify_token( string $token ) {
        return $this->client->me( $token );
    }

    /** {@inheritdoc} */
    public function sso_exchange( string $rald_token, string $app_id, string $redirect_to ) {
        return $this->client->sso_exchange( $rald_token, $app_id, $redirect_to );
    }

    /** {@inheritdoc} */
    public function logout( string $token ): bool {
        return $this->client->logout( $token );
    }
}
