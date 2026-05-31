<?php
/**
 * RALD Connect — Dashboard Module Tests
 *
 * @package RaldConnect\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Rald_Dashboard extends TestCase {

    public function test_singleton_returns_same_instance(): void {
        $a = Rald_Dashboard::get_instance();
        $b = Rald_Dashboard::get_instance();
        $this->assertSame( $a, $b );
    }

    public function test_status_endpoint_registered(): void {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey( '/rald-connect/v1/status', $routes );
    }
}
