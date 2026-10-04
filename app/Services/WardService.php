<?php

namespace App\Services;

use App\Enums\CustodyFlag;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A guardian's own view of their children. A custody-blocked link never
 * reveals the child; everything else is scoped to the guardian's own link.
 */
class WardService
{
    /**
     * @return Collection<int, Child>
     */
    public function list(User $guardian): Collection
    {
        return $this->query($guardian)
            ->with([
                'classroom:id,name',
                'attendances' => fn ($q) => $q->whereDate('date', today())->with('pickedUpBy:id,name'),
            ])
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @throws ModelNotFoundException when the child isn't this guardian's (or the link is blocked).
     */
    public function find(User $guardian, Child $child): Child
    {
        return $this->query($guardian)
            ->whereKey($child->id)
            ->with([
                'classroom:id,name',
                'attendances' => fn ($q) => $q->whereDate('date', today())->with('pickedUpBy:id,name'),
            ])
            ->firstOrFail();
    }

    public function attendance(User $guardian, Child $child, ?CarbonImmutable $from, ?CarbonImmutable $to): LengthAwarePaginator
    {
        $ward = $this->find($guardian, $child);

        return $ward->attendances()
            ->when($from, fn (Builder $q, $d) => $q->whereDate('date', '>=', $d->toDateString()))
            ->when($to, fn (Builder $q, $d) => $q->whereDate('date', '<=', $d->toDateString()))
            ->with('pickedUpBy:id,name')
            ->latest('date')
            ->paginate(30);
    }

    /**
     * Merge the guardian's notification choices for this child.
     *
     * @param  array<string, bool>  $preferences
     */
    public function updateNotifications(User $guardian, Child $child, array $preferences): Child
    {
        $ward = $this->find($guardian, $child);
        $current = json_decode((string) $ward->pivot->notify_preferences, true) ?: [];

        $guardian->wards()->updateExistingPivot($child->id, [
            'notify_preferences' => json_encode(array_merge($current, $preferences)),
        ]);

        return $this->find($guardian, $child);
    }

    private function query(User $guardian): BelongsToMany
    {
        return $guardian->wards()->wherePivot('custody_flag', '!=', CustodyFlag::Blocked->value);
    }
}
