<?php

namespace App\Domain\Users\Services;

use App\Domain\Shared\Services\DateRangeFilterBuilder;
use Illuminate\Database\Eloquent\Builder;

class UserTableFilterBuilder
{
    public function apply(Builder $query, array $data): Builder
    {
        $query->when(
            $data['status'] ?? null,
            fn (Builder $builder, string $value) => $builder->where('status', $value)
        );

        $query->when(
            $data['mfa'] ?? null,
            fn (Builder $builder, string $value) => $value === 'enabled'
                ? $builder->whereNotNull('two_factor_secret')
                : $builder->whereNull('two_factor_secret')
        );

        $query->when(
            $data['access_profiles'] ?? null,
            fn (Builder $builder, array $value) => $builder->whereHas(
                'accessProfiles',
                fn (Builder $subQuery) => $subQuery->whereIn('access_profiles.id', $value)
            )
        );

        $query->when(
            $data['unit_kerjas'] ?? null,
            fn (Builder $builder, array $value) => $builder->whereHas(
                'unitKerjas',
                fn (Builder $subQuery) => $subQuery->whereIn('unit_kerja.id', $value)
            )
        );

        DateRangeFilterBuilder::build(
            $query,
            [
                'from' => $data['login_from'] ?? null,
                'until' => $data['login_until'] ?? null,
            ],
            'last_login_at'
        );

        DateRangeFilterBuilder::build(
            $query,
            [
                'from' => $data['created_from'] ?? null,
                'until' => $data['created_until'] ?? null,
            ],
            'created_at'
        );

        $query->when(
            $data['quick_filter'] ?? null,
            function (Builder $builder, string $value): void {
                match ($value) {
                    'secure_users' => $builder
                        ->where('status', 'active')
                        ->whereNotNull('two_factor_secret')
                        ->whereHas('accessProfiles'),
                    'unused_accounts' => $builder
                        ->where('status', 'active')
                        ->whereNull('last_login_at')
                        ->where('created_at', '<', now()->subDays(30)),
                    default => null,
                };
            }
        );

        return $query;
    }
}