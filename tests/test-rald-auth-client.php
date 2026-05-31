<?php
/**
 * RALD Connect — Auth Client Unit Tests
 *
 * @package RaldConnect\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Rald_Auth_Client extends TestCase {

    private Rald_Auth_Client $client;

    protected function setUp(): void {
        $this->client = new Rald_Auth_Client( 'https://auth.rald.cloud' );
    }

    public function test_client_instantiates(): void {
        $this->assertInstanceOf( Rald_Auth_Client::class, $this->client );
    }

    public function test_register_endpoint_format(): void {
        $this->expectException( \RuntimeException::class );
        $this->client->register( [ 'email' => 'invalid' ] );
    }

    public function test_login_requires_email_and_password(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->client->login( '', '' );
    }
}
