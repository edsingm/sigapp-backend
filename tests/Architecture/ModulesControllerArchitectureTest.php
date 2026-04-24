<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class ModulesControllerArchitectureTest extends TestCase
{
    public function test_modules_controller_avoids_direct_model_usage(): void
    {
        $file = __DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/Common/ModulesController.php';
        $contents = file_get_contents($file);

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('App\\Models\\', $contents);
        $this->assertStringNotContainsString('::query(', $contents);
        $this->assertStringNotContainsString('::find(', $contents);
    }
}
