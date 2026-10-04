<?php

use App\Enums\AuditAction;
use App\Enums\DunningStep;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\DunningNotice;
use App\Models\PaymentIntent;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\GuardianService;
use App\Services\Messaging\SmsGateway;
use App\Services\Tuition\DunningService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Support\FakeSmsGateway;

beforeEach(function () {
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);
    configureGateways();

    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00'));
    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    enableBahgaPay($this->tenant);
    TenantEntitlementOverride::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'auto_collection', 'value' => true]);

    $this->payer = User::factory()->create(['phone' => '01012345678']);
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    app(GuardianService::class)->attach($child, $this->payer, ['relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);
    $this->child = $child;
});

function invoiceDue($test, string $dueOn, array $attributes = []): TuitionInvoice
{
    return TuitionInvoice::factory()->create([
        'tenant_id' => $test->tenant->id, 'payer_id' => $test->payer->id, 'due_on' => $dueOn,
        'period_start' => CarbonImmutable::parse($dueOn)->startOfMonth(), 'total_piasters' => 150_000,
    ] + $attributes);
}

function remind($test, ?string $day = null)
{
    app(TenantContext::class)->set($test->tenant);

    return app(DunningService::class)->run($test->tenant, $day ? CarbonImmutable::parse($day) : null);
}

it('picks the right step of the ladder for each day', function (int $days, ?DunningStep $step) {
    expect(DunningStep::forDaysPastDue($days))->toBe($step);
})->with([
    [-5, null], [-3, DunningStep::BeforeDue], [-1, DunningStep::BeforeDue], [0, DunningStep::DueDay],
    [2, DunningStep::DueDay], [3, DunningStep::Overdue3], [10, DunningStep::Overdue7], [14, DunningStep::Escalated], [90, DunningStep::Escalated],
]);

it('sends one SMS per step with the amount and a signed pay link', function () {
    invoiceDue($this, '2026-10-23');   // due in 3 days

    $result = remind($this);

    expect($result->sent)->toBe(1)
        ->and($this->sms->sent[0]['phone'])->toBe('01012345678')
        ->and($this->sms->sent[0]['message'])->toContain('حضانة البراعم')->toContain('1,500 ج.م')->toContain('تستحق يوم 23/10')
        ->and($this->sms->sent[0]['message'])->toContain('/pay/'.$this->tenant->id.'/')->toContain('signature=');

    remind($this);   // the job runs again the same day
    expect($this->sms->sent)->toHaveCount(1);
});

it('walks the ladder over time without repeating or back-filling steps', function () {
    $invoice = invoiceDue($this, '2026-10-05');   // first run already 15 days late

    remind($this, '2026-10-20');

    expect($invoice->dunningNotices()->pluck('step')->all())->toBe([DunningStep::Escalated])
        ->and($this->sms->sent)->toBe([])
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::TuitionInvoiceEscalated)->count())->toBe(1);

    $fresh = invoiceDue($this, '2026-10-20', ['period_start' => '2026-11-01']);
    foreach (['2026-10-17', '2026-10-20', '2026-10-22', '2026-10-23', '2026-10-27', '2026-11-03'] as $day) {
        remind($this, $day);
    }

    expect($fresh->dunningNotices()->orderBy('id')->pluck('step')->all())->toBe([
        DunningStep::BeforeDue, DunningStep::DueDay, DunningStep::Overdue3, DunningStep::Overdue7, DunningStep::Escalated,
    ]);
});

it('stops reminding once the invoice is paid', function () {
    invoiceDue($this, '2026-10-20', ['status' => 'paid', 'paid_piasters' => 150_000]);

    expect(remind($this)->sent)->toBe(0)->and(DunningNotice::count())->toBe(0);
});

it('respects a guardian who turned SMS off, and records why', function () {
    $this->child->guardians()->updateExistingPivot($this->payer->id, ['notify_preferences' => json_encode(['sms' => false])]);
    invoiceDue($this, '2026-10-20');

    $result = remind($this);

    expect($result->skipped)->toBe(1)
        ->and($this->sms->sent)->toBe([])
        ->and(DunningNotice::sole()->skip_reason)->toBe('opted_out');
});

it('records a provider failure without retrying the same step', function () {
    $this->sms->fail = true;
    invoiceDue($this, '2026-10-20');

    expect(remind($this)->failed)->toBe(1)->and(DunningNotice::sole()->status)->toBe('failed');
});

it('asks to pay at the nursery when online payment is unavailable', function () {
    config(['services.paymob.secret_key' => null, 'services.fawry.secure_key' => null]);
    invoiceDue($this, '2026-10-20');

    remind($this);

    expect($this->sms->sent[0]['message'])->toContain('يرجى السداد لدى الحضانة')->not->toContain('/pay/');
});

it('runs daily only for nurseries with Auto Collection and Bahga Pay', function () {
    invoiceDue($this, '2026-10-20');
    $other = Tenant::factory()->create();
    enableBahgaPay($other);   // no Auto Collection
    TuitionInvoice::factory()->create(['tenant_id' => $other->id, 'due_on' => '2026-10-20']);

    $this->artisan('tuition:remind')->assertSuccessful();

    expect(DunningNotice::withoutGlobalScopes()->pluck('tenant_id')->all())->toBe([$this->tenant->id]);
});

it('surfaces escalations and parked online payments on the dashboard', function () {
    $invoice = invoiceDue($this, '2026-10-01', ['number' => 'INV-LATE-1']);
    remind($this);
    PaymentIntent::factory()->create([
        'tenant_id' => $this->tenant->id, 'tuition_invoice_id' => $invoice->id, 'payer_id' => $this->payer->id,
        'status' => 'needs_review', 'failure_reason' => 'المبلغ أكبر من المتبقي',
    ]);

    $this->actingAs($this->owner)->get(route('nursery.finance.dashboard'))
        ->assertOk()->assertSee('يحتاج متابعة منك')->assertSee('INV-LATE-1')->assertSee('المبلغ أكبر من المتبقي');

    $this->actingAs($this->owner)->get(route('nursery.finance.invoices.show', $invoice))
        ->assertOk()->assertSee('تصعيد للإدارة')->assertSee('يحتاج مراجعة');
});
