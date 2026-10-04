<?php

namespace App\Console\Commands;

use App\Enums\Limit;
use App\Enums\TenantStatus;
use App\Models\Moment;
use App\Models\MomentMedia;
use App\Models\Tenant;
use App\Services\EntitlementService;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Daily: erase wall photos older than the plan's retention (the Free plan
 * keeps 30 days). The moments themselves stay, without their photos.
 */
class PruneWallMedia extends Command
{
    protected $signature = 'wall:prune-media';

    protected $description = "Delete Daily Wall photos older than each nursery's plan retention";

    public function handle(EntitlementService $entitlements, TenantContext $context): int
    {
        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value, TenantStatus::Suspended->value])
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($entitlements, $context) {
                $days = $entitlements->for($tenant)->limit(Limit::MediaRetentionDays);
                if ($days === null) {
                    return;
                }

                $context->set($tenant);

                try {
                    $removed = 0;
                    MomentMedia::where('created_at', '<', now()->subDays($days))
                        ->chunkById(200, function ($batch) use (&$removed) {
                            DB::transaction(function () use ($batch) {
                                MomentMedia::whereKey($batch->modelKeys())->delete();
                                foreach ($batch->countBy('moment_id') as $momentId => $count) {
                                    Moment::withTrashed()->whereKey($momentId)->decrement('media_count', $count);
                                }
                            });

                            // Files last: a failed transaction never leaves rows without files.
                            foreach ($batch as $media) {
                                Storage::disk($media->disk)->delete([$media->path, $media->thumb_path]);
                            }

                            $removed += $batch->count();
                        });

                    if ($removed > 0) {
                        $this->line("[{$tenant->id}] {$tenant->name}: {$removed} photo(s) past {$days} days removed");
                    }
                } finally {
                    $context->forget();
                }
            });

        return self::SUCCESS;
    }
}
