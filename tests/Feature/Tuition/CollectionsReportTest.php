<?php

use App\Enums\TuitionInvoiceStatus;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use App\Models\User;
use App\Services\Tuition\CollectionsReportService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00'));
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    enableBahgaPay($this->tenant);
});

function invoiceFor($test, array $attributes): TuitionInvoice
{
    return TuitionInvoice::factory()->create(['tenant_id' => $test->tenant->id] + $attributes);
}

it('summarises the month and buckets outstanding balances by days late', function () {
    $mona = User::factory()->create(['name' => 'منى']);
    $sara = User::factory()->create(['name' => 'سارة']);

    // October: two invoices billed (1,500 + 1,000), one half paid.
    $oct = invoiceFor($this, ['payer_id' => $mona->id, 'period_start' => '2026-10-01', 'due_on' => '2026-10-05', 'total_piasters' => 150_000, 'paid_piasters' => 75_000, 'status' => TuitionInvoiceStatus::PartiallyPaid]);
    invoiceFor($this, ['payer_id' => $sara->id, 'period_start' => '2026-10-01', 'due_on' => '2026-10-25', 'total_piasters' => 100_000]);
    TuitionPayment::factory()->create(['tenant_id' => $this->tenant->id, 'tuition_invoice_id' => $oct->id, 'amount_piasters' => 75_000, 'paid_at' => '2026-10-03', 'method' => 'cash']);
    // Older debts: 45 and 120 days late. A void invoice never counts.
    invoiceFor($this, ['payer_id' => $mona->id, 'period_start' => '2026-09-01', 'due_on' => '2026-09-05', 'total_piasters' => 20_000]);
    invoiceFor($this, ['payer_id' => $sara->id, 'period_start' => '2026-06-01', 'due_on' => '2026-06-22', 'total_piasters' => 30_000]);
    invoiceFor($this, ['payer_id' => $sara->id, 'period_start' => '2026-05-01', 'due_on' => '2026-05-05', 'total_piasters' => 99_000, 'status' => TuitionInvoiceStatus::Void]);

    $reports = app(CollectionsReportService::class);
    $summary = $reports->summary($this->tenant, CarbonImmutable::parse('2026-10-01'));

    expect($summary['billed']->piasters)->toBe(250_000)
        ->and($summary['collected']->piasters)->toBe(75_000)
        ->and($summary['collectionRate'])->toBe(30)
        ->and($summary['outstanding']->piasters)->toBe(75_000 + 100_000 + 20_000 + 30_000)
        ->and($summary['overdue']->piasters)->toBe(75_000 + 20_000 + 30_000)
        ->and($summary['overdueInvoices'])->toBe(3);

    expect(array_map(fn ($b) => $b['amount']->piasters, $reports->aging($this->tenant)))
        ->toBe([100_000, 75_000, 20_000, 0, 30_000]);   // not due, 15d, 45d, —, 120d

    $late = $reports->topLateFamilies($this->tenant);
    expect($late->pluck('name')->all())->toBe(['منى', 'سارة'])
        ->and($late->first()->overdue->piasters)->toBe(95_000)
        ->and($late->first()->oldest_due_on)->toBe('2026-09-05');
});

it('ignores voided payments in collections', function () {
    $invoice = invoiceFor($this, ['payer_id' => User::factory()->create()->id, 'period_start' => '2026-10-01']);
    TuitionPayment::factory()->create(['tenant_id' => $this->tenant->id, 'tuition_invoice_id' => $invoice->id, 'amount_piasters' => 10_000, 'paid_at' => '2026-10-10', 'voided_at' => '2026-10-11']);

    expect(app(CollectionsReportService::class)->collectedByMethod($this->tenant, CarbonImmutable::parse('2026-10-01')))->toBe([]);
});

it('renders the collections dashboard', function () {
    invoiceFor($this, ['payer_id' => User::factory()->create(['name' => 'منى'])->id, 'due_on' => '2026-09-01', 'period_start' => '2026-09-01', 'total_piasters' => 50_000]);

    $this->actingAs($this->owner)
        ->get(route('nursery.finance.dashboard'))
        ->assertOk()
        ->assertSee('أعمار المستحقات')
        ->assertSee('منى')
        ->assertSee('500 ج.م');
});

it('never mixes another nursery\'s money into the report', function () {
    TuitionInvoice::factory()->create(['total_piasters' => 999_900, 'due_on' => '2026-01-01']);

    $summary = app(CollectionsReportService::class)->summary($this->tenant, CarbonImmutable::parse('2026-10-01'));

    expect($summary['outstanding']->piasters)->toBe(0);
});
