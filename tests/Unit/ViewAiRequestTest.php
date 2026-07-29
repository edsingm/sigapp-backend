<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Tenant\ChatAiRequest;
use App\Http\Requests\Tenant\ViewAiRequest;
use App\Models\Tenant\User;
use App\Services\Acl\PermissionNameResolver;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ViewAiRequestTest extends TestCase
{
    /**
     * @return array<string, array{class-string<ViewAiRequest>}>
     */
    public static function aiRequestClasses(): array
    {
        return [
            'chat' => [ChatAiRequest::class],
            'read endpoints' => [ViewAiRequest::class],
        ];
    }

    /**
     * @param  class-string<ViewAiRequest>  $requestClass
     */
    #[DataProvider('aiRequestClasses')]
    public function test_ai_requests_require_ai_viewer_permission(string $requestClass): void
    {
        $user = new User;
        $request = $requestClass::create('/api/v1/ai/sig-ai', 'POST');
        $request->setUserResolver(static fn (): User => $user);

        $resolver = Mockery::mock(PermissionNameResolver::class);
        $resolver->shouldReceive('userCan')
            ->once()
            ->with($user, 'ai.viewer')
            ->andReturnFalse();
        $this->app->instance(PermissionNameResolver::class, $resolver);

        $this->assertFalse($request->authorize());
    }

    /**
     * @param  class-string<ViewAiRequest>  $requestClass
     */
    #[DataProvider('aiRequestClasses')]
    public function test_ai_requests_allow_ai_viewers(string $requestClass): void
    {
        $user = new User;
        $request = $requestClass::create('/api/v1/ai/sig-ai', 'POST');
        $request->setUserResolver(static fn (): User => $user);

        $resolver = Mockery::mock(PermissionNameResolver::class);
        $resolver->shouldReceive('userCan')
            ->once()
            ->with($user, 'ai.viewer')
            ->andReturnTrue();
        $this->app->instance(PermissionNameResolver::class, $resolver);

        $this->assertTrue($request->authorize());
    }
}
