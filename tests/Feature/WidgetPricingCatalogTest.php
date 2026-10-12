<?php

namespace Tests\Feature;

use App\Support\F5WidgetCatalog;
use App\Support\WidgetPricingCalculator;
use Tests\TestCase;

class WidgetPricingCatalogTest extends TestCase
{
    public function test_chatter_has_per_channel_pricing(): void
    {
        $widget = F5WidgetCatalog::findBySlug('chatter');

        $this->assertNotNull($widget);
        $this->assertSame('per_channel_monthly', $widget['pricing']['model']);
        $this->assertSame(3500, $widget['pricing']['unit_monthly']);
    }

    public function test_one_c_has_fixed_period_packages(): void
    {
        $widget = F5WidgetCatalog::findBySlug('1c');

        $this->assertNotNull($widget);
        $this->assertSame('fixed_period', $widget['pricing']['model']);
        $this->assertSame(60000, $widget['pricing']['packages'][6]);
        $this->assertSame(90000, $widget['pricing']['packages'][12]);
    }

    public function test_resolve_sets_default_channel_quantity_for_chatter(): void
    {
        $resolved = F5WidgetCatalog::resolveForStorage([
            'widget_slug' => 'chatter',
            'comment' => 'WhatsApp',
        ]);

        $this->assertSame(1, $resolved['billing_quantity']);
    }

    public function test_resolve_keeps_channel_quantity_for_chatter(): void
    {
        $resolved = F5WidgetCatalog::resolveForStorage([
            'widget_slug' => 'chatter',
            'billing_quantity' => 2,
        ]);

        $this->assertSame(2, $resolved['billing_quantity']);
    }

    public function test_resolve_clears_quantity_for_regular_widget(): void
    {
        $resolved = F5WidgetCatalog::resolveForStorage([
            'widget_slug' => 'tasker',
            'billing_quantity' => 3,
        ]);

        $this->assertNull($resolved['billing_quantity']);
    }

    public function test_amocrm_license_tariffs_match_official_pricing(): void
    {
        $estimate = WidgetPricingCalculator::amocrmLicenseEstimatePeriods(8);

        $this->assertSame(8, $estimate['billingUsers']);
        $this->assertNull($estimate['selectedSlug']);
        $this->assertCount(3, $estimate['tariffs']);
        $this->assertSame('Базовый', $estimate['tariffs'][0]['name']);
        $this->assertSame(599.0, $estimate['tariffs'][0]['price_per_user']);
        // 599 × 8 × 6
        $this->assertSame(28752.0, $estimate['tariffs'][0]['costSixPlusOne']);
        // 599 × 8 × 10
        $this->assertSame(47920.0, $estimate['tariffs'][0]['costTenPlusThree']);

        $this->assertSame('Расширенный', $estimate['tariffs'][1]['name']);
        $this->assertSame(1199.0, $estimate['tariffs'][1]['price_per_user']);
        $this->assertSame('Профессиональный', $estimate['tariffs'][2]['name']);
        $this->assertSame(1699.0, $estimate['tariffs'][2]['price_per_user']);
    }

    public function test_amocrm_license_estimate_filters_selected_tariff(): void
    {
        $estimate = WidgetPricingCalculator::amocrmLicenseEstimatePeriods(8, 'advanced');

        $this->assertSame('advanced', $estimate['selectedSlug']);
        $this->assertCount(1, $estimate['tariffs']);
        $this->assertSame('Расширенный', $estimate['tariffs'][0]['name']);
        $this->assertSame(1199.0, $estimate['tariffs'][0]['price_per_user']);
    }

    public function test_amocrm_license_allows_from_one_user(): void
    {
        $estimate = WidgetPricingCalculator::calculateAmocrmLicenses(1, 1);

        $this->assertSame(1, $estimate['billingUsers']);
        $this->assertSame(599.0, $estimate['tariffs'][0]['linePeriod']); // 599 × 1
    }

    public function test_amocrm_license_defaults_to_one_user_when_empty(): void
    {
        $estimate = WidgetPricingCalculator::calculateAmocrmLicenses(null, 1);

        $this->assertSame(1, $estimate['billingUsers']);
        $this->assertSame(599.0, $estimate['tariffs'][0]['linePeriod']);
    }

    public function test_unlimited_variant_three_uses_thirteen_months(): void
    {
        $rows = WidgetPricingCalculator::unlimitedSubscriptionRows(5);

        $this->assertSame(12, $rows[0]['longMonths']);
        $this->assertSame(13, $rows[1]['longMonths']);
        $this->assertSame('на 13 месяцев', $rows[1]['longPeriodLabel']);
        // 1799 × 5 × 13
        $this->assertSame(116935.0, $rows[1]['tariffs'][0]['costLongPeriod']);
    }

    public function test_custom_subscription_uses_paid_months(): void
    {
        $estimate = WidgetPricingCalculator::calculateEstimatePeriods(
            [
                [
                    'name' => 'Телефония',
                    'widget_slug' => null,
                    'payment_type' => 'subscription',
                    'cost' => 5000,
                ],
            ],
            F5WidgetCatalog::widgets(),
            5
        );

        $this->assertCount(1, $estimate['lines']);
        $this->assertSame('custom_subscription', $estimate['lines'][0]['model']);
        $this->assertSame(30000.0, $estimate['lines'][0]['costSixPlusOne']);
        $this->assertSame(50000.0, $estimate['lines'][0]['costTenPlusThree']);
    }

    public function test_custom_one_time_is_flat_sum(): void
    {
        $estimate = WidgetPricingCalculator::calculateEstimatePeriods(
            [
                [
                    'name' => 'Настройка',
                    'widget_slug' => null,
                    'payment_type' => 'one_time',
                    'cost' => 45000,
                ],
            ],
            F5WidgetCatalog::widgets(),
            5
        );

        $this->assertCount(1, $estimate['lines']);
        $this->assertSame('custom_one_time', $estimate['lines'][0]['model']);
        $this->assertSame(45000.0, $estimate['lines'][0]['costSixPlusOne']);
        $this->assertSame(45000.0, $estimate['lines'][0]['costTenPlusThree']);
    }
}
