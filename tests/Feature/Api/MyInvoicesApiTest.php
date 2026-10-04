<?php

use App\Models\Child;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\GuardianService;

beforeEach(function () {
    [$this->tenant] = createNurseryWithOwner();
    enableBahgaPay($this->tenant);
    $this->child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->payer = User::factory()->create();
    $this->coGuardian = User::factory()->create();

    $guardians = app(GuardianService::class);
    $guardians->attach($this->child, $this->payer, ['relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);
    $guardians->attach($this->child, $this->coGuardian, ['relationship' => 'father', 'role' => 'viewer', 'is_payer' => false]);
});

it('makes a linked guardian a member of the nursery so they can sign in to it', function () {
    $this->actingAs($this->payer)->getJson('/api/v1/me/wards')->assertOk()->assertJsonCount(1, 'data');
});

it('lists only the invoices addressed to the signed-in payer', function () {
    $mine = TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => $this->payer->id, 'total_piasters' => 185_050]);
    TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => User::factory()->create()->id]);
    TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => $this->payer->id, 'status' => 'void', 'period_start' => now()->subMonth()->startOfMonth()]);

    $this->actingAs($this->payer)
        ->getJson('/api/v1/me/invoices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', $mine->number)
        ->assertJsonPath('data.0.total.piasters', 185_050)
        ->assertJsonPath('data.0.balance.formatted', '1,850.50 ج.م');

    $this->actingAs($this->coGuardian)->getJson('/api/v1/me/invoices')->assertOk()->assertJsonCount(0, 'data');
});

it('shows an invoice\'s lines only to its payer', function () {
    $invoice = TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => $this->payer->id]);
    $invoice->items()->create(['tenant_id' => $this->tenant->id, 'kind' => 'fee', 'description' => 'المصروفات', 'amount_piasters' => 150_000]);

    $this->actingAs($this->payer)->getJson("/api/v1/me/invoices/{$invoice->id}")
        ->assertOk()->assertJsonPath('data.items.0.description', 'المصروفات');

    $this->actingAs($this->coGuardian)->getJson("/api/v1/me/invoices/{$invoice->id}")->assertNotFound();
});

it('keeps another nursery\'s invoices out of reach', function () {
    $foreign = TuitionInvoice::factory()->create(['payer_id' => $this->payer->id]);

    $this->actingAs($this->payer)->getJson("/api/v1/me/invoices/{$foreign->id}")->assertNotFound();
});

it('stays hidden until Bahga Pay is released to the nursery', function () {
    [$other] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $other->id]);
    $parent = User::factory()->create();
    app(GuardianService::class)->attach($child, $parent, ['relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);

    $this->actingAs($parent)->getJson('/api/v1/me/invoices')->assertNotFound();
});
