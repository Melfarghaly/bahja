<?php

namespace App\Http\Resources;

use App\Models\TuitionInvoice;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TuitionInvoice
 */
class TuitionInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'period' => $this->period_start->format('Y-m'),
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'subtotal' => Money::of($this->subtotal_piasters),
            'discount' => Money::of($this->discount_piasters),
            'total' => $this->total(),
            'paid' => $this->paid(),
            'balance' => $this->balance(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'kind' => $item->kind->value,
                'child_id' => $item->child_id,
                'amount' => $item->amount(),
            ])),
            'receipts' => $this->whenLoaded('payments', fn () => $this->payments->whereNull('voided_at')->values()->map(fn ($payment) => [
                'receipt_number' => $payment->receipt_number,
                'method' => $payment->method->value,
                'amount' => $payment->amount(),
                'paid_at' => $payment->paid_at->toIso8601String(),
            ])),
        ];
    }
}
