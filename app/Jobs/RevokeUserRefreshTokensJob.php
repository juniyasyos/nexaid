<?php

namespace App\Jobs;

use App\Services\JWTTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RevokeUserRefreshTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;

    /** @var array<string> $applicationKeys Optional list of app keys to limit revocation */
    public array $applicationKeys = [];

    public function __construct(int $userId, array $applicationKeys = [])
    {
        $this->userId = $userId;
        $this->applicationKeys = $applicationKeys;
    }

    public function handle(): void
    {
        try {
            $jwtService = app(JWTTokenService::class);

            $query = \App\Domain\Iam\Models\Application::query();
            if (! empty($this->applicationKeys)) {
                $query->whereIn('app_key', $this->applicationKeys);
            }

            $query->pluck('app_key')->each(function (string $appKey) use ($jwtService) {
                try {
                    $jwtService->revokeRefreshToken($this->userId, $appKey);
                } catch (\Throwable $e) {
                    Log::warning('revoke_refresh_token_failed', [
                        'user_id' => $this->userId,
                        'app_key' => $appKey,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('revoke_refresh_tokens_job_failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
