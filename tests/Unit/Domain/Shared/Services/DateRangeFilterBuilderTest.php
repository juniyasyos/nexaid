<?php

namespace Tests\Unit\Domain\Shared\Services;

use App\Domain\Shared\Services\DateRangeFilterBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class DateRangeFilterBuilderTest extends TestCase
{
    /**
     * Test get indicators for active filters.
     */
    public function test_get_indicators_for_active_filters(): void
    {
        $data = [
            'from' => '2025-01-01',
            'until' => '2025-01-31',
        ];

        $indicators = DateRangeFilterBuilder::getIndicators($data);

        $this->assertCount(2, $indicators);
        $this->assertStringContainsString('Created from', $indicators[0]);
        $this->assertStringContainsString('Created until', $indicators[1]);
    }

    /**
     * Test get indicators with custom label.
     */
    public function test_get_indicators_with_custom_label(): void
    {
        $data = ['from' => '2025-01-01'];

        $indicators = DateRangeFilterBuilder::getIndicators($data, 'Updated');

        $this->assertStringContainsString('Updated from', $indicators[0]);
    }

    /**
     * Test get indicators for empty filters.
     */
    public function test_get_indicators_for_empty_filters(): void
    {
        $indicators = DateRangeFilterBuilder::getIndicators([]);

        $this->assertCount(0, $indicators);
    }

    /**
     * Test is active returns true when filters are set.
     */
    public function test_is_active_returns_true_for_set_filters(): void
    {
        $this->assertTrue(DateRangeFilterBuilder::isActive(['from' => '2025-01-01']));
        $this->assertTrue(DateRangeFilterBuilder::isActive(['until' => '2025-01-31']));
        $this->assertTrue(DateRangeFilterBuilder::isActive(['from' => '2025-01-01', 'until' => '2025-01-31']));
    }

    /**
     * Test is active returns false for empty filters.
     */
    public function test_is_active_returns_false_for_empty_filters(): void
    {
        $this->assertFalse(DateRangeFilterBuilder::isActive([]));
        $this->assertFalse(DateRangeFilterBuilder::isActive(['from' => null, 'until' => null]));
    }

    /**
     * Test format date with string input.
     */
    public function test_format_date_with_string(): void
    {
        $result = DateRangeFilterBuilder::formatDate('2025-01-01');
        $this->assertEquals('2025-01-01', $result);
    }

    /**
     * Test format date with Carbon instance.
     */
    public function test_format_date_with_carbon(): void
    {
        $date = Carbon::create(2025, 1, 15);
        $result = DateRangeFilterBuilder::formatDate($date);
        $this->assertEquals('2025-01-15', $result);
    }
}
