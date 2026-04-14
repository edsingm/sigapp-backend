<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User as CentralUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\After;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    #[After]
    public function tearDownMockery(): void
    {
        Mockery::close();
    }

    public function test_blocks_non_admin_user(): void
    {
        $user = Mockery::mock(CentralUser::class)->makePartial();
        $user->is_admin = false;
        $user->shouldReceive('currentAccessToken')->andReturn($this->mockToken(true));

        $request = Request::create('/api/v1/admin/tenants', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureUserIsAdmin)->handle($request, fn () => new Response('ok', 200));

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_blocks_user_without_admin_ability(): void
    {
        $user = Mockery::mock(CentralUser::class)->makePartial();
        $user->is_admin = true;
        $user->shouldReceive('currentAccessToken')->andReturn($this->mockToken(false));

        $request = Request::create('/api/v1/admin/tenants', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureUserIsAdmin)->handle($request, fn () => new Response('ok', 200));

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_allows_admin_with_admin_ability(): void
    {
        $user = Mockery::mock(CentralUser::class)->makePartial();
        $user->is_admin = true;
        $user->shouldReceive('currentAccessToken')->andReturn($this->mockToken(true));

        $request = Request::create('/api/v1/admin/tenants', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureUserIsAdmin)->handle($request, fn () => new Response('ok', 200));

        self::assertSame(200, $response->getStatusCode());
    }

    private function mockToken(bool $canAdmin): PersonalAccessToken
    {
        $token = Mockery::mock(PersonalAccessToken::class);
        $token->shouldReceive('can')->with('admin')->andReturn($canAdmin);

        return $token;
    }
}
