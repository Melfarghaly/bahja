<?php

namespace App\Services\Wall;

use App\Enums\ConsentScope;
use App\Models\Child;
use App\Models\Moment;
use App\Models\MomentChild;
use App\Models\User;
use App\Services\Notifications\QuietHours;
use App\Services\WardService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reading the wall. A family sees moments tagged with their child — and a
 * group photo only while every other child in it still has group-photo
 * consent (a family that withdraws it disappears from others' walls at once).
 * Other children's names are never shown to a family.
 */
class MomentFeedService
{
    public function __construct(private WardService $wards) {}

    /**
     * @param  array{child_id?: ?int, classroom_id?: ?int, date?: ?string}  $filters
     * @return LengthAwarePaginator<int, Moment>
     */
    public function forStaff(array $filters): LengthAwarePaginator
    {
        return Moment::query()
            ->with(['author:id,name', 'classroom:id,name', 'children:id,first_name,last_name', 'media'])
            ->when($filters['child_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('tags', fn ($t) => $t->where('child_id', $id)))
            ->when($filters['classroom_id'] ?? null, fn (Builder $q, $id) => $q->where('classroom_id', $id))
            ->when($filters['date'] ?? null, function (Builder $q, string $date) {
                $start = CarbonImmutable::parse($date, QuietHours::TIMEZONE)->startOfDay()->setTimezone(config('app.timezone'));
                $q->whereBetween('published_at', [$start, $start->addDay()->subSecond()]);
            })
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);
    }

    /**
     * @return LengthAwarePaginator<int, Moment>
     *
     * @throws AuthorizationException when this guardian may not see the child's wall.
     */
    public function forWard(User $guardian, Child $child): LengthAwarePaginator
    {
        $ward = $this->viewableWard($guardian, $child);

        return Moment::query()
            ->whereHas('tags', fn ($q) => $q->where('child_id', $ward->id))
            ->where(fn (Builder $q) => $q
                ->where('media_count', 0)
                ->orWhere('children_count', 1)
                ->orWhereNotExists(fn (QueryBuilder $others) => $this->othersWithoutGroupConsent($others, $ward->id)))
            ->with(['author:id,name', 'media', 'children' => fn ($q) => $q->whereKey($ward->id)])
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);
    }

    /**
     * A guardian confirms they have read an incident report about their child.
     *
     * @throws ValidationException when the moment is not an incident.
     */
    public function acknowledge(User $guardian, Child $child, Moment $moment): Moment
    {
        $ward = $this->viewableWard($guardian, $child);
        $tag = MomentChild::where('moment_id', $moment->id)->where('child_id', $ward->id)->firstOrFail();

        if (! $moment->requires_ack) {
            throw ValidationException::withMessages(['moment' => __('wall.not_an_incident')]);
        }

        if ($tag->acknowledged_at === null) {
            $tag->update(['acknowledged_at' => now(), 'acknowledged_by' => $guardian->id]);
        }

        return $moment->load(['author:id,name', 'media', 'children' => fn ($q) => $q->whereKey($ward->id)]);
    }

    private function viewableWard(User $guardian, Child $child): Child
    {
        $ward = $this->wards->find($guardian, $child);    // 404 if not mine or custody-blocked

        if (! $ward->pivot->can_view_wall) {
            throw new AuthorizationException(__('wall.cannot_view_wall'));
        }

        return $ward;
    }

    /**
     * Other children tagged in the moment who lack wall + group-photo consent.
     */
    private function othersWithoutGroupConsent(QueryBuilder $query, int $childId): void
    {
        $query->select(DB::raw(1))
            ->from('moment_child as others')
            ->whereColumn('others.moment_id', 'moments.id')
            ->where('others.child_id', '!=', $childId)
            ->where(fn (QueryBuilder $missing) => $missing
                ->whereNotExists(fn (QueryBuilder $c) => $this->activeConsent($c, ConsentScope::Wall))
                ->orWhereNotExists(fn (QueryBuilder $c) => $this->activeConsent($c, ConsentScope::GroupPhotos)));
    }

    private function activeConsent(QueryBuilder $query, ConsentScope $scope): void
    {
        $query->select(DB::raw(1))
            ->from('media_consents')
            ->whereColumn('media_consents.child_id', 'others.child_id')
            ->where('media_consents.scope', $scope->value)
            ->whereNull('media_consents.revoked_at');
    }
}
