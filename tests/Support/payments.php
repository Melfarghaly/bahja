<?php

use App\Models\Child;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\GuardianService;
use App\Services\Tuition\LedgerService;
use Illuminate\Support\Arr;

/**
 * Sandbox-style credentials for both gateways.
 */
function configureGateways(): void
{
    config([
        'services.paymob.base_url' => 'https://accept.paymob.test',
        'services.paymob.secret_key' => 'sk_test',
        'services.paymob.public_key' => 'pk_test',
        'services.paymob.hmac_secret' => 'hmac_test',
        'services.paymob.integration_ids' => '111,222',
        'services.fawry.base_url' => 'https://fawry.test',
        'services.fawry.merchant_code' => 'MERCHANT',
        'services.fawry.secure_key' => 'secure_test',
    ]);
}

/**
 * A Paymob "transaction processed" callback body and its valid HMAC.
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function paymobCallback(string $merchantReference, int $amountCents, bool $success = true, int $id = 9001, bool $pending = false): array
{
    $obj = [
        'id' => $id, 'pending' => $pending, 'amount_cents' => $amountCents, 'success' => $success,
        'is_auth' => false, 'is_capture' => false, 'is_standalone_payment' => true, 'is_voided' => false,
        'is_refunded' => false, 'is_3d_secure' => true, 'integration_id' => 111, 'has_parent_transaction' => false,
        'error_occured' => false, 'currency' => 'EGP', 'owner' => 42, 'created_at' => '2026-10-04T12:00:00',
        'order' => ['id' => 777, 'merchant_order_id' => $merchantReference],
        'source_data' => ['pan' => '2346', 'type' => 'card', 'sub_type' => 'MasterCard'],
    ];

    $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner',
        'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
    $message = implode('', array_map(fn ($f) => is_bool($v = Arr::get($obj, $f)) ? ($v ? 'true' : 'false') : (string) $v, $fields));

    return [['type' => 'TRANSACTION', 'obj' => $obj], hash_hmac('sha512', $message, 'hmac_test')];
}

/**
 * A signed Fawry V2 server notification.
 *
 * @return array<string, mixed>
 */
function fawryNotification(string $merchantReference, string $orderAmount, string $status = 'PAID'): array
{
    $data = [
        'requestId' => 'req-1', 'fawryRefNumber' => '966512345', 'merchantRefNumber' => $merchantReference,
        'paymentAmount' => (float) $orderAmount + 5, 'orderAmount' => $orderAmount, 'fawryFees' => 5,
        'orderStatus' => $status, 'paymentMethod' => 'PAYATFAWRY', 'paymentRefrenceNumber' => '24601',
    ];
    $data['messageSignature'] = hash('sha256', $data['fawryRefNumber'].$merchantReference
        .number_format($data['paymentAmount'], 2, '.', '').number_format((float) $orderAmount, 2, '.', '')
        .$status.'PAYATFAWRY'.'24601'.'secure_test');

    return $data;
}

/**
 * A nursery on a plan with Auto Collection, Bahga Pay released, and one open
 * family invoice for a payer guardian.
 *
 * @return array{0: Tenant, 1: User, 2: TuitionInvoice}
 */
function onlinePayingFamily(int $totalPiasters = 150_000): array
{
    [$tenant] = createNurseryWithOwner();
    enableBahgaPay($tenant);
    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'auto_collection', 'value' => true]);

    $payer = User::factory()->create(['name' => 'منى عبد الله', 'phone' => '01011112222']);
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    app(GuardianService::class)->attach($child, $payer, ['relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);

    $invoice = TuitionInvoice::factory()->create([
        'tenant_id' => $tenant->id, 'payer_id' => $payer->id,
        'subtotal_piasters' => $totalPiasters, 'total_piasters' => $totalPiasters,
    ]);
    // The ledger must hold the receivable that online payments will settle.
    app(LedgerService::class)->postInvoiceIssued($invoice);

    return [$tenant, $payer, $invoice];
}
