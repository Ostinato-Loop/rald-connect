<?php
/**
 * RALD Connect — Token Store Unit Tests
 *
 * @package RaldConnect\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Rald_Token_Store extends TestCase {

    private Rald_Token_Store $store;

    protected function setUp(): void {
        $this->store = new Rald_Token_Store();
    }

    public function test_encrypt_decrypt_roundtrip(): void {
        $token     = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.sig';
        $encrypted = $this->store->encrypt( $token );
        $this->assertNotSame( $token, $encrypted );
        $this->assertSame( $token, $this->store->decrypt( $encrypted ) );
    }

    public function test_empty_token_returns_empty(): void {
        $this->assertSame( '', $this->store->encrypt( '' ) );
        $this->assertSame( '', $this->store->decrypt( '' ) );
    }
}
