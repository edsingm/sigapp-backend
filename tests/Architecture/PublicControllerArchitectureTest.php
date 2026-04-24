<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class PublicControllerArchitectureTest extends TestCase
{
    public function test_auth_controller_avoids_inline_validation(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/AuthController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('$request->validate(', $contents);
    }

    public function test_blog_controller_avoids_direct_post_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/BlogController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('App\\Models\\Central\\Post', $contents);
        $this->assertStringNotContainsString('Post::', $contents);
    }
}
