@extends('nursery.layout')
@section('title', 'فاتورة '.$invoice->number)

@section('content')
    <a href="{{ route('nursery.finance.invoices.index') }}" class="text-sm text-gray-500 hover:underline">→ رجوع للفواتير</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold font-mono">{{ $invoice->number }}</h2>
                    <p class="text-sm text-gray-500">مصروفات شهر {{ $invoice->period_start->format('Y-m') }} · صدرت {{ $invoice->issued_on->toDateString() }}</p>
                </div>
                @include('nursery.finance.invoices.partials.status', ['status' => $invoice->status])
            </div>

            <div class="text-sm">
                <span class="text-gray-400">وليّ الأمر:</span> {{ $invoice->payer->name }} · {{ $invoice->payer->phone }}
            </div>

            <table class="w-full text-sm">
                <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">البند</th><th class="pb-2 font-medium text-left">المبلغ</th></tr></thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($invoice->items as $item)
                        <tr class="{{ $item->amount_piasters < 0 ? 'text-emerald-700' : '' }}">
                            <td class="py-2">{{ $item->description }}</td>
                            <td class="py-2 text-left">{{ $item->amount()->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm">
                    <tr class="border-t border-gray-100"><td class="pt-3 text-gray-500">الإجمالي قبل الخصم</td><td class="pt-3 text-left">{{ \App\Support\Money::of($invoice->subtotal_piasters)->format() }}</td></tr>
                    <tr><td class="text-gray-500">الخصومات</td><td class="text-left text-emerald-700">-{{ \App\Support\Money::of($invoice->discount_piasters)->format() }}</td></tr>
                    <tr class="font-bold"><td class="pt-1">المطلوب</td><td class="pt-1 text-left">{{ $invoice->total()->format() }}</td></tr>
                    <tr><td class="text-gray-500">المدفوع</td><td class="text-left">{{ $invoice->paid()->format() }}</td></tr>
                    <tr class="font-extrabold brand-text text-base"><td class="pt-1">المتبقي</td><td class="pt-1 text-left">{{ $invoice->balance()->format() }}</td></tr>
                </tfoot>
            </table>

            @if ($invoice->status === \App\Enums\TuitionInvoiceStatus::Void)
                <p class="text-sm text-gray-500">أُلغيت في {{ $invoice->voided_at->toDateString() }} — {{ $invoice->void_reason }}</p>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                <h3 class="font-bold mb-2">الاستحقاق</h3>
                <p class="text-sm {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                    {{ $invoice->due_on->toDateString() }}
                    @if ($invoice->isOverdue()) — متأخرة {{ $invoice->daysOverdue() }} يوم @endif
                </p>
            </div>

            @includeIf('nursery.finance.invoices.partials.payments')
        </div>
    </div>
@endsection
