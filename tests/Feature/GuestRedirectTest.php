<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GuestRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('protectedRoutes')]
    public function test_guest_is_redirected_to_login(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $response->assertRedirect('/login');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function protectedRoutes(): iterable
    {
        yield 'dashboard' => ['GET', '/'];
        yield 'jobs index' => ['GET', '/jobs'];
        yield 'jobs create' => ['GET', '/jobs/create'];
        yield 'vehicles index' => ['GET', '/vehicles'];
        yield 'vehicles create' => ['GET', '/vehicles/create'];
        yield 'mechanics index' => ['GET', '/mechanics'];
        yield 'mechanics create' => ['GET', '/mechanics/create'];
        yield 'settings index' => ['GET', '/settings'];
    }
}
