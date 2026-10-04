<?php

namespace App\Services\Wall;

use App\Enums\AuditAction;
use App\Enums\ChildStatus;
use App\Enums\Limit;
use App\Enums\MomentType;
use App\Models\Child;
use App\Models\Moment;
use App\Models\MomentChild;
use App\Models\MomentMedia;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use App\Services\Exceptions\PlanLimitException;
use App\Services\Notifications\QuietHours;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Posting to the Daily Wall: one update for one child or a whole class
 * ("breakfast for everyone, except Omar"), with the photo rules enforced:
 * consent for every tagged child, and the plan's daily photo allowance.
 */
class MomentService
{
    /**
     * Payload fields kept per type (anything else is dropped).
     */
    private const PAYLOAD = [
        'meal' => ['meal', 'amount'],
        'nap' => ['from', 'to'],
        'diaper' => ['kind'],
        'mood' => ['mood'],
        'activity' => ['title'],
    ];

    public function __construct(
        private MediaConsentService $consents,
        private PhotoProcessor $photos,
        private VideoStore $videos,
        private EntitlementService $entitlements,
        private WallNotifications $notifications,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{type: string, client_ref?: ?string, body?: ?string, payload?: array<string, mixed>, child_ids?: array<int, int>, classroom_id?: ?int, except_child_ids?: array<int, int>}  $data
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, array{file: UploadedFile, poster: ?UploadedFile, duration_ms: ?int}>  $videos
     *
     * @throws ValidationException when no child is left, or a child has no photo consent.
     * @throws PlanLimitException when a child would exceed the plan's daily media allowance.
     */
    public function post(Tenant $tenant, User $author, array $data, array $photos = [], array $videos = []): Moment
    {
        // A retry of an update that already arrived (the app lost the response).
        if (isset($data['client_ref']) && ($existing = $this->alreadyPosted($data['client_ref'])) !== null) {
            return $existing;
        }

        $type = MomentType::from($data['type']);
        $children = $this->children($data);

        // Photos and videos follow the same consent rules and daily allowance.
        if ($photos !== [] || $videos !== []) {
            $this->assertConsent($children);
            $this->assertDailyPhotos($tenant, $children, count($photos) + count($videos));
        }

        $stored = $this->storeMedia($tenant, $photos, $videos);

        try {
            $moment = DB::transaction(function () use ($tenant, $author, $data, $type, $children, $stored) {
                $moment = Moment::create([
                    'tenant_id' => $tenant->id,
                    'author_id' => $author->id,
                    'client_ref' => $data['client_ref'] ?? null,
                    'classroom_id' => $data['classroom_id'] ?? null,
                    'type' => $type,
                    'body' => $data['body'] ?? null,
                    'payload' => $this->payload($type, $data['payload'] ?? []),
                    'children_count' => $children->count(),
                    'media_count' => count($stored),
                    'requires_ack' => $type->requiresAcknowledgement(),
                    'published_at' => now(),
                ]);

                MomentChild::insert($children->map(fn (Child $child) => [
                    'tenant_id' => $tenant->id,
                    'moment_id' => $moment->id,
                    'child_id' => $child->id,
                ])->all());

                foreach ($stored as $sort => $file) {
                    MomentMedia::create($file + ['tenant_id' => $tenant->id, 'moment_id' => $moment->id, 'sort' => $sort]);
                }

                return $moment;
            });
        } catch (Throwable $e) {
            $this->deleteFiles($stored);

            // Two retries raced: the other one stored it, answer with that one.
            if ($e instanceof UniqueConstraintViolationException && isset($data['client_ref'])) {
                return $this->alreadyPosted($data['client_ref']) ?? throw $e;
            }

            throw $e;
        }

        $moment->load(['author:id,name', 'classroom:id,name', 'children', 'media']);
        $this->notifications->posted($tenant, $moment);

        return $moment;
    }

    private function alreadyPosted(string $clientRef): ?Moment
    {
        return Moment::withTrashed()->where('client_ref', $clientRef)->first()
            ?->load(['author:id,name', 'classroom:id,name', 'children', 'media']);
    }

    /**
     * Soft-deletes the moment (kept for the audit trail) and erases its photos.
     */
    public function delete(Moment $moment, User $by): void
    {
        $media = $moment->media()->get();

        DB::transaction(function () use ($moment, $media, $by) {
            MomentMedia::whereKey($media->modelKeys())->delete();
            $moment->update(['media_count' => 0]);
            $moment->delete();

            $this->audit->record(AuditAction::MomentDeleted, $moment, [
                'type' => $moment->type->value,
                'children' => $moment->children()->pluck('children.id')->all(),
                'photos' => $media->count(),
            ], $by);
        });

        $this->deleteFiles($media->map(fn (MomentMedia $m) => $m->only(['disk', 'path', 'thumb_path']))->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Child>
     */
    private function children(array $data): Collection
    {
        $query = Child::query()->where('status', ChildStatus::Active->value);

        $children = isset($data['classroom_id'])
            ? $query->where('classroom_id', $data['classroom_id'])->whereNotIn('id', $data['except_child_ids'] ?? [])->get()
            : $query->whereKey($data['child_ids'] ?? [])->get();

        if ($children->isEmpty()) {
            throw ValidationException::withMessages([
                isset($data['classroom_id']) ? 'classroom_id' : 'child_ids' => __('validation.required', ['attribute' => __('validation.attributes.child_ids')]),
            ]);
        }

        return $children;
    }

    /**
     * @param  Collection<int, Child>  $children
     */
    private function assertConsent(Collection $children): void
    {
        $missing = $this->consents->missingFor($children);

        if ($missing->isNotEmpty()) {
            $key = $children->count() > 1 ? 'wall.no_group_consent' : 'wall.no_photo_consent';

            throw ValidationException::withMessages([
                'child_ids' => __($key, ['children' => $missing->pluck('first_name')->implode('، ')]),
            ]);
        }
    }

    /**
     * @param  Collection<int, Child>  $children
     */
    private function assertDailyPhotos(Tenant $tenant, Collection $children, int $adding): void
    {
        $limit = $this->entitlements->for($tenant)->limit(Limit::DailyPhotosPerChild);
        if ($limit === null) {
            return;
        }

        $dayStart = CarbonImmutable::now(QuietHours::TIMEZONE)->startOfDay()->setTimezone(config('app.timezone'));
        $usage = MomentChild::query()
            ->join('moments', 'moments.id', '=', 'moment_child.moment_id')
            ->whereIn('moment_child.child_id', $children->modelKeys())
            ->whereNull('moments.deleted_at')
            ->where('moments.published_at', '>=', $dayStart)
            ->groupBy('moment_child.child_id')
            ->selectRaw('moment_child.child_id, sum(moments.media_count) as photos')
            ->pluck('photos', 'moment_child.child_id');

        if ($children->contains(fn (Child $child) => (int) ($usage[$child->id] ?? 0) + $adding > $limit)) {
            throw new PlanLimitException(Limit::DailyPhotosPerChild->value, $limit);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, array{file: UploadedFile, poster: ?UploadedFile, duration_ms: ?int}>  $videos
     * @return array<int, array<string, mixed>>
     */
    private function storeMedia(Tenant $tenant, array $photos, array $videos): array
    {
        $stored = [];

        try {
            foreach (array_values($photos) as $index => $photo) {
                try {
                    $stored[] = $this->photos->store($photo, $tenant->id);
                } catch (RuntimeException) {
                    throw ValidationException::withMessages(["photos.{$index}" => __('validation.image', ['attribute' => __('validation.attributes.photo')])]);
                }
            }
            foreach (array_values($videos) as $index => $video) {
                try {
                    $stored[] = $this->videos->store($video['file'], $video['poster'], $video['duration_ms'], $tenant->id);
                } catch (RuntimeException) {
                    throw ValidationException::withMessages(["video_posters.{$index}" => __('validation.image', ['attribute' => __('validation.attributes.photo')])]);
                }
            }
        } catch (Throwable $e) {
            $this->deleteFiles($stored);

            throw $e;
        }

        return $stored;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete(array_filter([$file['path'], $file['thumb_path'] ?? null]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function payload(MomentType $type, array $payload): ?array
    {
        $keys = self::PAYLOAD[$type->value] ?? [];

        return $keys === [] ? null : array_intersect_key($payload, array_flip($keys));
    }
}
