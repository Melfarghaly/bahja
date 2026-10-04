<?php

namespace App\Services\Wall;

use App\Enums\MomentType;
use App\Models\Moment;

/**
 * One short line describing a moment ("الغداء: أكل نصفه"), in a language.
 */
class MomentSummary
{
    public function for(Moment $moment, ?string $locale = null): string
    {
        $p = $moment->payload ?? [];
        $t = fn (string $group, ?string $key) => $key === null ? '' : __("wall.{$group}.{$key}", [], $locale);

        $params = match ($moment->type) {
            MomentType::Meal => ['meal' => $t('meal', $p['meal'] ?? null), 'amount' => $t('amount', $p['amount'] ?? null)],
            MomentType::Nap => ['from' => $p['from'] ?? '', 'to' => $p['to'] ?? ''],
            MomentType::Diaper => ['kind' => $t('diaper_kind', $p['kind'] ?? null)],
            MomentType::Mood => ['mood' => $t('mood', $p['mood'] ?? null)],
            MomentType::Activity => ['title' => $p['title'] ?? ''],
            default => [],
        };

        return __('wall.summary.'.$moment->type->value, $params, $locale);
    }
}
