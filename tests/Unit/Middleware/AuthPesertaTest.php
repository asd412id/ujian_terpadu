<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AuthPeserta;
use App\Models\Peserta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthPesertaTest extends TestCase
{
    use RefreshDatabase;

    private AuthPeserta $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AuthPeserta();
    }

    public function test_unauthenticated_peserta_is_redirected(): void
    {
        $request = Request::create('/ujian/lobby', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_authenticated_peserta_passes(): void
    {
        $peserta = Peserta::factory()->create();
        Auth::guard('peserta')->login($peserta);

        $request = Request::create('/ujian/lobby', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
