@extends('nursery.layout')
@section('title', 'بهجة باي — التحصيل')

@section('content')
    <form method="GET" action="{{ route('nursery.finance.dashboard') }}" class="flex flex-wrap items-center gap-2 text-sm">
        <label for="month" class="text-gray-500">الشهر</label>
        <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="rounded-lg border-gray-200 text-sm">
        <button class="brand-bg px-4 py-2 rounded-lg font-medium">عرض</button>
        <a href="{{ route('nursery.finance.invoices.index', ['period' => $month->format('Y-m')]) }}" class="ms-auto brand-text hover:underline">فواتير الشهر ←</a>
    </form>

    {{-- Headline numbers: stat tiles, not charts. --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="text-xs text-gray-500">صدر هذا الشهر</div>
            <div class="text-2xl font-extrabold mt-1">{{ $summary['billed']->format() }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="text-xs text-gray-500">حُصّل هذا الشهر</div>
            <div class="text-2xl font-extrabold mt-1">{{ $summary['collected']->format() }}</div>
            <div class="text-xs text-gray-500 mt-1">كل الدفعات المستلمة خلال الشهر</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="text-xs text-gray-500">نسبة تحصيل فواتير الشهر</div>
            <div class="text-2xl font-extrabold mt-1">{{ $summary['collectionRate'] === null ? '—' : $summary['collectionRate'].'%' }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $summary['collectedForPeriod']->format() }} من {{ $summary['billed']->format() }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="text-xs text-gray-500">إجمالي المستحق</div>
            <div class="text-2xl font-extrabold mt-1">{{ $summary['outstanding']->format() }}</div>
            @if ($summary['overdueInvoices'] > 0)
                <div class="text-xs text-red-700 mt-1">⚠ متأخر: {{ $summary['overdue']->format() }} ({{ $summary['overdueInvoices'] }} فاتورة)</div>
            @else
                <div class="text-xs text-gray-500 mt-1">✓ لا توجد متأخرات</div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Aging: one series over ordered buckets → single-hue bars, direct value labels, no legend. --}}
        <section class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm xl:col-span-2" aria-labelledby="aging-title">
            <h2 id="aging-title" class="font-bold">أعمار المستحقات</h2>
            <p class="text-xs text-gray-500 mb-4">المتبقي على الفواتير المفتوحة حسب أيام التأخير</p>
            <div class="space-y-3" role="list">
                @foreach ($aging as $bucket)
                    @php($width = $bucket['amount']->piasters === 0 ? 0 : max(1, round($bucket['amount']->piasters / $agingMax * 100, 1)))
                    <div role="listitem" class="grid grid-cols-[7rem_1fr_auto] items-center gap-3 text-sm"
                         title="{{ $bucket['label'] }}: {{ $bucket['amount']->format() }} — {{ $bucket['invoices'] }} فاتورة">
                        <span class="text-gray-600">{{ $bucket['label'] }}</span>
                        <span class="h-3 bg-gray-100 rounded overflow-hidden" aria-hidden="true">
                            <span class="block h-full" style="width: {{ $width }}%; background: var(--teal); border-radius: 0 4px 4px 0;"></span>
                        </span>
                        <span class="font-medium tabular-nums">{{ $bucket['amount']->format() }}</span>
                    </div>
                @endforeach
            </div>
            <details class="mt-4 text-sm">
                <summary class="text-gray-500 cursor-pointer">عرض كجدول</summary>
                <table class="w-full mt-2">
                    <thead class="text-gray-400 text-right"><tr><th class="pb-1 font-medium">الفئة</th><th class="pb-1 font-medium">المبلغ</th><th class="pb-1 font-medium">الفواتير</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($aging as $bucket)
                            <tr><td class="py-1">{{ $bucket['label'] }}</td><td class="py-1">{{ $bucket['amount']->format() }}</td><td class="py-1">{{ $bucket['invoices'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <section class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm" aria-labelledby="methods-title">
            <h2 id="methods-title" class="font-bold mb-3">تحصيل الشهر حسب طريقة الدفع</h2>
            <ul class="text-sm divide-y divide-gray-50">
                @php($anyMethod = false)
                @foreach ($methods as $method)
                    @isset($byMethod[$method->value])
                        @php($anyMethod = true)
                        <li class="py-2 flex justify-between"><span>{{ $method->label() }}</span><span class="font-medium tabular-nums">{{ $byMethod[$method->value]->format() }}</span></li>
                    @endisset
                @endforeach
                @unless ($anyMethod)
                    <li class="py-2 text-gray-400">لا توجد دفعات هذا الشهر.</li>
                @endunless
            </ul>
        </section>
    </div>

    @if ($followUps['escalated']->isNotEmpty() || $followUps['needsReview']->isNotEmpty())
        <section class="bg-white rounded-2xl border border-amber-200 p-6 shadow-sm space-y-4" aria-labelledby="followups-title">
            <h2 id="followups-title" class="font-bold">⚠ يحتاج متابعة منك</h2>

            @if ($followUps['needsReview']->isNotEmpty())
                <div>
                    <h3 class="text-sm font-medium mb-2">دفعات إلكترونية وصلت ولم تُطبَّق تلقائياً</h3>
                    <ul class="text-sm divide-y divide-gray-50">
                        @foreach ($followUps['needsReview'] as $intent)
                            <li class="py-2 flex flex-wrap justify-between gap-2">
                                <a class="brand-text hover:underline font-mono" href="{{ route('nursery.finance.invoices.show', $intent->tuition_invoice_id) }}">{{ $intent->invoice?->number }}</a>
                                <span>{{ $intent->amount()->format() }} · {{ $intent->gateway->label() }}</span>
                                <span class="text-gray-500 text-xs">{{ $intent->failure_reason }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($followUps['escalated']->isNotEmpty())
                <div>
                    <h3 class="text-sm font-medium mb-2">فواتير متأخرة أكثر من 14 يوماً بعد كل التذكيرات</h3>
                    <ul class="text-sm divide-y divide-gray-50">
                        @foreach ($followUps['escalated'] as $invoice)
                            <li class="py-2 flex flex-wrap justify-between gap-2">
                                <a class="brand-text hover:underline font-mono" href="{{ route('nursery.finance.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                <span>{{ $invoice->payer->name }} <span class="text-gray-400" dir="ltr">{{ $invoice->payer->phone }}</span></span>
                                <span class="font-medium tabular-nums">{{ $invoice->balance()->format() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm overflow-x-auto" aria-labelledby="late-title">
        <h2 id="late-title" class="font-bold mb-3">أعلى الأسر تأخراً</h2>
        <table class="w-full text-sm min-w-[560px]">
            <thead class="text-gray-400 text-right">
                <tr><th class="pb-2 font-medium">وليّ الأمر</th><th class="pb-2 font-medium">الهاتف</th><th class="pb-2 font-medium">المتأخر</th><th class="pb-2 font-medium">الفواتير</th><th class="pb-2 font-medium">أقدم استحقاق</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($lateFamilies as $family)
                    <tr>
                        <td class="py-2">
                            <a class="brand-text hover:underline" href="{{ route('nursery.finance.invoices.index', ['q' => $family->phone ?? $family->name, 'overdue' => 1]) }}">{{ $family->name }}</a>
                        </td>
                        <td class="py-2 text-gray-500" dir="ltr">{{ $family->phone }}</td>
                        <td class="py-2 font-medium tabular-nums">{{ $family->overdue->format() }}</td>
                        <td class="py-2">{{ $family->invoices }}</td>
                        <td class="py-2 text-gray-500">{{ $family->oldest_due_on }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-gray-400">✓ لا توجد أسر متأخرة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
