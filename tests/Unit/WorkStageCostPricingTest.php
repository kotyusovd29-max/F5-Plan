<?php

namespace Tests\Unit;

use App\Support\WorkStageCostPricing;
use Tests\TestCase;

class WorkStageCostPricingTest extends TestCase
{
    public function test_keeps_amount_from_request(): void
    {
        $this->assertSame(
            45000.0,
            WorkStageCostPricing::normalizeCost(45000, WorkStageCostPricing::extractExplicitAmounts('Этап за 45 000 руб'))
        );
    }

    public function test_keeps_multiples_of_5100(): void
    {
        $this->assertSame(5100.0, WorkStageCostPricing::normalizeCost(5100, []));
        $this->assertSame(10200.0, WorkStageCostPricing::normalizeCost(10200, []));
        $this->assertSame(15300.0, WorkStageCostPricing::normalizeCost(15300, []));
        $this->assertSame(51000.0, WorkStageCostPricing::normalizeCost(51000, []));
    }

    public function test_snaps_estimated_amounts_to_hourly_multiples(): void
    {
        $this->assertSame(5100.0, WorkStageCostPricing::normalizeCost(5000, []));
        $this->assertSame(51000.0, WorkStageCostPricing::normalizeCost(49000, []));
    }

    public function test_normalize_changes_respects_source_text(): void
    {
        $changes = WorkStageCostPricing::normalizeChanges([
            'work_stages' => [
                'create' => [
                    ['name' => 'A', 'cost' => 5000],
                    ['name' => 'B', 'cost' => 45000],
                ],
                'update' => [],
                'delete' => [],
            ],
        ], 'Сделай этап B за 45000, этап A оцени сам');

        $this->assertSame(5100.0, $changes['work_stages']['create'][0]['cost']);
        $this->assertSame(45000.0, $changes['work_stages']['create'][1]['cost']);
    }
}
