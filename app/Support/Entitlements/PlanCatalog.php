<?php

namespace App\Support\Entitlements;

use App\Enums\Feature;
use App\Enums\Limit;

/**
 * Single source of truth for the standard Bahga tiers (V2 pricing, EGP/month).
 * Seeds the subscription_plans table, and the "free" tier doubles as the
 * fallback for a nursery without an active subscription.
 */
class PlanCatalog
{
    public const FREE = 'free';

    /**
     * @return array<string, array{name: string, price_egp: int, max_children: ?int, max_teachers: ?int, included_sms: int, features: array<int, Feature>, limits: array<string, ?int>}>
     */
    public static function tiers(): array
    {
        $free = [
            Feature::Attendance, Feature::LostChild, Feature::Wall,
            Feature::ThankYouStars, Feature::FinanceLedger,
        ];
        $basic = [...$free, Feature::PickupPasses, Feature::Messaging, Feature::HealthRecords];
        $pro = [
            ...$basic, Feature::AutoCollection, Feature::AdmissionsCrm,
            Feature::PulseReport, Feature::ReportsExport, Feature::RewardsCatalog,
        ];
        $advanced = [...$pro, Feature::WhiteLabel, Feature::PublicApi];

        return [
            self::FREE => [
                'name' => 'Free',
                'price_egp' => 0,
                'max_children' => 20,
                'max_teachers' => 3,
                'included_sms' => 0,
                'features' => $free,
                'limits' => [
                    Limit::DailyPhotosPerChild->value => 3,
                    Limit::MediaRetentionDays->value => 30,
                ],
            ],
            'basic' => [
                'name' => 'Basic',
                'price_egp' => 750,
                'max_children' => 50,
                'max_teachers' => 8,
                'included_sms' => 100,
                'features' => $basic,
                'limits' => [],
            ],
            'pro' => [
                'name' => 'Pro',
                'price_egp' => 1900,
                'max_children' => 120,
                'max_teachers' => null,
                'included_sms' => 500,
                'features' => $pro,
                'limits' => [],
            ],
            'advanced' => [
                'name' => 'Advanced',
                'price_egp' => 3900,
                'max_children' => 250,
                'max_teachers' => null,
                'included_sms' => 2000,
                'features' => $advanced,
                'limits' => [],
            ],
        ];
    }

    /**
     * @return array{name: string, price_egp: int, max_children: ?int, max_teachers: ?int, included_sms: int, features: array<int, Feature>, limits: array<string, ?int>}
     */
    public static function free(): array
    {
        return self::tiers()[self::FREE];
    }
}
