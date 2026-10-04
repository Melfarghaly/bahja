<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single entry point for writing to the append-only audit trail. Captures the
 * acting user and request fingerprint automatically when available.
 */
class AuditLogger
{
    public function __construct(private ?Request $request = null) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditAction $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
        ?int $tenantId = null,
    ): AuditLog {
        $actor ??= $this->request?->user();

        return AuditLog::create([
            'tenant_id' => $tenantId ?? $subject?->getAttribute('tenant_id'),
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent() !== null
                ? mb_substr($this->request->userAgent(), 0, 255)
                : null,
        ]);
    }
}
