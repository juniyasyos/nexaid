<?php

namespace App\Domain\Shared\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builds date range filters for Eloquent queries.
 * 
 * Provides a reusable, consistent interface for date range filtering
 * across all Filament tables. Used in place of duplicated filter logic.
 * 
 * Example usage:
 * ```php
 * Filter::make('date_range')
 *     ->schema([...])
 *     ->query(fn($query, $data) => DateRangeFilterBuilder::build($query, $data, 'created_at'))
 *     ->indicateUsing(fn($data) => DateRangeFilterBuilder::getIndicators($data))
 * ```
 */
class DateRangeFilterBuilder
{
    /**
     * Build date range query constraints.
     * 
     * Adds >= and <= date comparisons to the query based on 'from' and 'until' keys
     * in the data array.
     * 
     * @param Builder $query
     * @param array $data Filter data with 'from' and 'until' keys (optional)
     * @param string $column Column to filter on (default: 'created_at')
     * @return Builder Modified query builder
     */
    public static function build(Builder $query, array $data, string $column = 'created_at'): Builder
    {
        return $query
            ->when(
                $data['from'] ?? null,
                fn(Builder $q, $date) => $q->whereDate($column, '>=', $date)
            )
            ->when(
                $data['until'] ?? null,
                fn(Builder $q, $date) => $q->whereDate($column, '<=', $date)
            );
    }

    /**
     * Get filter indicator labels.
     * 
     * Returns human-readable text indicating which filters are active.
     * 
     * Example output:
     * - ['Created from 2025-01-01', 'Created until 2025-01-31']
     * 
     * @param array $data Filter data with 'from' and 'until' keys
     * @param string $label Label prefix (default: 'Created')
     * @return array Indicator strings
     */
    public static function getIndicators(array $data, string $label = 'Created'): array
    {
        $indicators = [];

        if (!empty($data['from'])) {
            $indicators[] = $label . ' from ' . $data['from'];
        }

        if (!empty($data['until'])) {
            $indicators[] = $label . ' until ' . $data['until'];
        }

        return $indicators;
    }

    /**
     * Build with custom column and label.
     * 
     * @param Builder $query
     * @param array $data
     * @param string $column Column name
     * @param string $label Label for indicators
     * @return array{query: Builder, indicators: array}
     */
    public static function buildWithLabel(Builder $query, array $data, string $column, string $label = 'Created'): array
    {
        return [
            'query' => self::build($query, $data, $column),
            'indicators' => self::getIndicators($data, $label),
        ];
    }

    /**
     * Format a date value for display.
     * 
     * @param mixed $date
     * @return string Formatted date string
     */
    public static function formatDate($date): string
    {
        if (is_string($date)) {
            return $date;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Check if any date filter is active.
     * 
     * @param array $data
     * @return bool
     */
    public static function isActive(array $data): bool
    {
        return !empty($data['from']) || !empty($data['until']);
    }
}
