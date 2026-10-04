<?php

namespace App\Services\Wall;

use App\Enums\AuditAction;
use App\Enums\ConsentScope;
use App\Enums\GuardianRole;
use App\Models\Child;
use App\Models\MediaConsent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Photo permissions per child, set by a primary guardian:
 * - wall: the nursery may photograph the child for the family's wall;
 * - group_photos: the child may appear in group photos other families see
 *   (only effective together with wall).
 * Nothing is assumed: without consent, the child cannot be tagged in photos.
 */
class MediaConsentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Effective permissions of one child (uses loaded active consents if any).
     *
     * @return array{wall: bool, group_photos: bool}
     */
    public function status(Child $child): array
    {
        $active = $child->relationLoaded('mediaConsents')
            ? $child->mediaConsents->whereNull('revoked_at')
            : $child->mediaConsents()->active()->get();

        $scopes = $active->map(fn (MediaConsent $c) => $c->scope)->all();
        $wall = in_array(ConsentScope::Wall, $scopes, true);

        return [
            'wall' => $wall,
            'group_photos' => $wall && in_array(ConsentScope::GroupPhotos, $scopes, true),
        ];
    }

    /**
     * @param  array{wall?: bool, group_photos?: bool}  $choices
     * @return array{wall: bool, group_photos: bool}
     *
     * @throws AuthorizationException when the guardian is not a primary guardian of the child.
     */
    public function update(User $guardian, Child $child, array $choices): array
    {
        $role = $child->guardians()->where('guardian_id', $guardian->id)->value('role');
        if ($role !== GuardianRole::Primary->value) {
            throw new AuthorizationException(__('wall.consent_primary_only'));
        }

        DB::transaction(function () use ($guardian, $child, $choices) {
            foreach ($choices as $scope => $granted) {
                $this->set($guardian, $child, ConsentScope::from($scope), (bool) $granted);
            }
        });

        return $this->status($child->unsetRelation('mediaConsents'));
    }

    /**
     * Of these children, those who may appear in a photo with the given
     * number of tagged children (alone → wall; with others → group photos).
     *
     * @param  Collection<int, Child>  $children
     * @return Collection<int, Child> the children WITHOUT the needed consent
     */
    public function missingFor(Collection $children): Collection
    {
        $needed = $children->count() > 1 ? [ConsentScope::Wall, ConsentScope::GroupPhotos] : [ConsentScope::Wall];

        $granted = MediaConsent::active()
            ->whereIn('child_id', $children->modelKeys())
            ->whereIn('scope', array_map(fn (ConsentScope $s) => $s->value, $needed))
            ->get(['child_id', 'scope'])
            ->groupBy('child_id');

        return $children->reject(fn (Child $child) => ($granted[$child->id] ?? collect())->count() === count($needed))->values();
    }

    private function set(User $guardian, Child $child, ConsentScope $scope, bool $granted): void
    {
        $active = MediaConsent::active()->where('child_id', $child->id)->where('scope', $scope->value)->lockForUpdate()->first();

        if ($granted && $active === null) {
            MediaConsent::create([
                'tenant_id' => $child->tenant_id,
                'child_id' => $child->id,
                'scope' => $scope,
                'granted_by' => $guardian->id,
                'granted_at' => now(),
            ]);
            $this->audit->record(AuditAction::MediaConsentGranted, $child, ['scope' => $scope->value], $guardian);
        }

        if (! $granted && $active !== null) {
            $active->update(['revoked_at' => now(), 'revoked_by' => $guardian->id]);
            $this->audit->record(AuditAction::MediaConsentRevoked, $child, ['scope' => $scope->value], $guardian);
        }
    }
}
