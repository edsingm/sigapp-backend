<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiToolResponse;
use App\Services\Ai\Tools\CreateTerrenoReportTool;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CreateTerrenoReportToolTest extends TestCase
{
    public function test_requires_authentication(): void
    {
        $tool = app(CreateTerrenoReportTool::class);
        $payload = json_decode((string) $tool->handle(new Request(['terreno_id' => 1])), true);

        $this->assertSame(AiToolResponse::DENIED, $payload['code']);
        $this->assertStringContainsString('autenticação', mb_strtolower($payload['message'] ?? ''));
    }

    public function test_validates_missing_terreno_id_when_authenticated_via_gate_bypass(): void
    {
        $this->be(new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        });

        $tool = app(CreateTerrenoReportTool::class);
        $payload = json_decode((string) $tool->handle(new Request([])), true);

        $this->assertSame(AiToolResponse::VALIDATION, $payload['code']);
    }

    public function test_description_mentions_terreno_report_and_download_url(): void
    {
        $tool = app(CreateTerrenoReportTool::class);
        $description = (string) $tool->description();

        $this->assertStringContainsString('terreno', mb_strtolower($description));
        $this->assertStringContainsString('data.url', $description);
    }
}
