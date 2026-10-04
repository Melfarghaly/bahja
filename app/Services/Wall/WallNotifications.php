<?php

namespace App\Services\Wall;

use App\Enums\CustodyFlag;
use App\Enums\NotificationType;
use App\Models\Child;
use App\Models\Moment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationRouter;

/**
 * Tells each family about a new moment: one notification per guardian, even
 * when several of their children are in it. Only guardians who may see the
 * wall are told, never a custody-blocked one. Incidents are urgent; their
 * details stay in the app, not on the lock screen.
 */
class WallNotifications
{
    public function __construct(
        private NotificationRouter $router,
        private MomentSummary $summary,
    ) {}

    public function posted(Tenant $tenant, Moment $moment): void
    {
        $moment->loadMissing(['children.guardians', 'author:id,name']);

        /** @var array<int, array{guardian: User, children: array<int, Child>}> $families */
        $families = [];
        foreach ($moment->children as $child) {
            foreach ($child->guardians as $guardian) {
                if ($guardian->pivot->can_view_wall && $guardian->pivot->custody_flag !== CustodyFlag::Blocked->value) {
                    $families[$guardian->id]['guardian'] = $guardian;
                    $families[$guardian->id]['children'][] = $child;
                }
            }
        }

        $type = $moment->requires_ack ? NotificationType::IncidentReported : NotificationType::MomentPosted;
        $summary = $this->summary->for($moment, 'ar');

        foreach ($families as ['guardian' => $guardian, 'children' => $children]) {
            $this->router->notify($tenant, $guardian, $type, [
                'child' => collect($children)->pluck('first_name')->implode(' و'),
                'teacher' => $moment->author?->name ?? $tenant->name,
                'summary' => $summary,
            ], $children[0], ['screen' => 'wall', 'moment_id' => $moment->id]);
        }
    }
}
