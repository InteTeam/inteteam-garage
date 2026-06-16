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
        yield 'dashboard' => ['GET', '/dashboard'];
        yield 'jobs index' => ['GET', '/jobs'];
        yield 'jobs create' => ['GET', '/jobs/create'];
        yield 'vehicles index' => ['GET', '/vehicles'];
        yield 'vehicles create' => ['GET', '/vehicles/create'];
        yield 'vehicles show' => ['GET', '/vehicles/01HZ0000000000000000000001'];
        yield 'vehicles edit' => ['GET', '/vehicles/01HZ0000000000000000000001/edit'];
        // No 'jobs edit' row — Route::resource('/', JobController) is constrained
        // to ->only(['index','create','store','show']); /jobs/{job}/edit is unregistered,
        // returns 404, never reaches the auth middleware.
        yield 'mechanics index' => ['GET', '/mechanics'];
        yield 'mechanics create' => ['GET', '/mechanics/create'];
        yield 'mechanics edit' => ['GET', '/mechanics/01HZ0000000000000000000001/edit'];
        yield 'settings index' => ['GET', '/settings'];
        yield 'estimates confirm-translation' => ['POST', '/jobs/01HZ0000000000000000000000/estimates/01HZ0000000000000000000001/confirm-translation'];
        yield 'estimates preview-translation' => ['POST', '/jobs/01HZ0000000000000000000000/estimates/01HZ0000000000000000000001/preview-translation'];
        yield 'line-items preview-response' => ['POST', '/jobs/01HZ0000000000000000000000/line-items/01HZ0000000000000000000001/preview-response'];
        yield 'stages notes update' => ['PATCH', '/jobs/01HZ0000000000000000000000/stages/01HZ0000000000000000000001/notes'];
    }
}
