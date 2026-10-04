<?php

namespace App\Models;

use App\Enums\DunningStep;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DunningNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DunningNotice extends Model
{
    /** @use HasFactory<DunningNoticeFactory> */
    use BelongsToTenant, HasFactory;

    public const SENT = 'sent';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'tuition_invoice_id',
        'step',
        'channel',
        'status',
        'recipient_phone',
        'message',
        'provider_reference',
        'skip_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['step' => DunningStep::class];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class, 'tuition_invoice_id');
    }
}
