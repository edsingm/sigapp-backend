<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class PublicControllerArchitectureTest extends TestCase
{
    public function test_public_auth_controllers_avoid_inline_validation(): void
    {
        $controllers = [
            __DIR__.'/../../app/Http/Controllers/Api/V1/CentralAuthController.php',
            __DIR__.'/../../app/Http/Controllers/Api/V1/TenantAuthController.php',
            __DIR__.'/../../app/Http/Controllers/Api/V1/TenantPasswordResetController.php',
        ];

        foreach ($controllers as $controllerPath) {
            $contents = file_get_contents($controllerPath);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('->validate(', $contents);
            $this->assertStringNotContainsString('$request->validate(', $contents);
        }
    }

    public function test_blog_controller_avoids_direct_post_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/BlogController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('App\\Models\\Central\\Post', $contents);
        $this->assertStringNotContainsString('Post::', $contents);
    }
}
