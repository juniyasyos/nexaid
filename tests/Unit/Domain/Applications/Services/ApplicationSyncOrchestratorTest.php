<?php

namespace Tests\Unit\Domain\Applications\Services;

use App\Domain\Applications\Services\ApplicationSyncOrchestrator;
use Tests\TestCase;

class ApplicationSyncOrchestratorTest extends TestCase
{
    private ApplicationSyncOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = new ApplicationSyncOrchestrator();
    }

    /**
     * Test format sync result for successful sync.
     */
    public function test_format_sync_result_for_success(): void
    {
        $result = [
            'success' => true,
            'message' => 'Sinkronisasi berhasil',
            'comparison' => [
                'in_sync' => 5,
                'missing_in_client' => 0,
                'extra_in_client' => 0,
            ],
        ];

        $formatted = $this->orchestrator->formatSyncResult($result);

        $this->assertStringContainsString('Sinkronisasi berhasil', $formatted);
        $this->assertStringNotContainsString('❌', $formatted);
    }

    /**
     * Test format sync result for failed sync.
     */
    public function test_format_sync_result_for_failure(): void
    {
        $result = [
            'success' => false,
            'message' => 'Sinkronisasi gagal',
            'error' => 'Connection timeout',
        ];

        $formatted = $this->orchestrator->formatSyncResult($result);

        $this->assertStringContainsString('Sinkronisasi gagal', $formatted);
        $this->assertStringContainsString('Connection timeout', $formatted);
        $this->assertStringContainsString('❌', $formatted);
    }
}
