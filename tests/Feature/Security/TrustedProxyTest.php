<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit SEC-008: X-Forwarded-For hanya dipercaya dari proxy lokal (nginx di VPS yang sama). Pengunjung yang
 * mengirim header itu sendiri tidak bisa memalsukan IP-nya.
 */
class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_test/ip', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->secure()]));
    }

    public function test_visitor_cannot_spoof_ip_with_forwarded_header(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
            ->getJson('/_test/ip')
            ->assertJson(['ip' => '203.0.113.7']);
    }

    public function test_ip_and_https_from_local_nginx_proxy_are_used(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/_test/ip')
            ->assertJson(['ip' => '198.51.100.9', 'secure' => true]);
    }

    public function test_spoofed_header_through_local_proxy_keeps_the_real_client_ip(): void
    {
        // nginx menambahkan IP asli di belakang header kiriman pengunjung ($proxy_add_x_forwarded_for).
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 198.51.100.9'])
            ->getJson('/_test/ip')
            ->assertJson(['ip' => '198.51.100.9']);
    }
}
