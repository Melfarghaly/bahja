@extends('nursery.layout')
@section('title', 'فواتير المصروفات')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="GET" action="{{ route('nursery.finance.invoices.index') }}" class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm lg:col-span-2 grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="رقم الفاتورة أو وليّ الأمر" class="rounded-lg border-gray-200 text-sm col-span-2">
            <input type="month" name="period" value="{{ $filters['period'] ?? '' }}" class="rounded-lg border-gray-200 text-sm">
            <select name="status" class="rounded-lg border-gray-200 text-sm">
                <option value="">كل الحالات</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false) class="rounded"> المتأخرة فقط
            </label>
            <button class="brand-bg text-white px-4 py-2 rounded-lg font-medium col-span-2 md:col-span-5">بحث</button>
        </form>

        <form method="POST" action="{{ route('nursery.finance.invoices.generate') }}" class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm space-y-2 text-sm"
              onsubmit="return confirm('إصدار فواتير الشهر المحدد لكل الأسر؟ الأسر التي صدرت فاتورتها لن تتكرر.')">
            @csrf
            <h2 class="font-bold">إصدار فواتير شهر</h2>
            <p class="text-xs text-gray-500">فاتورة واحدة لكل وليّ أمر دافع تشمل كل أبنائه. آمن للتكرار.</p>
            <div class="flex gap-2">
                <input type="month" name="period" value="{{ now()->format('Y-m') }}" class="flex-1 rounded-lg border-gray-200 text-sm" required>
                <button class="brand-bg text-white px-4 rounded-lg font-medium">إصدار</button>
            </div>
            @error('period')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="text-gray-400 text-right">
                <tr>
                    <th class="pb-2 font-medium">الفاتورة</th><th class="pb-2 font-medium">وليّ الأمر</th><th class="pb-2 font-medium">الشهر</th>
                    <th class="pb-2 font-medium">الإجمالي</th><th class="pb-2 font-medium">المتبقي</th><th class="pb-2 font-medium">الاستحقاق</th><th class="pb-2 font-medium">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="py-2"><a href="{{ route('nursery.finance.invoices.show', $invoice) }}" class="font-mono brand-text hover:underline">{{ $invoice->number }}</a></td>
                        <td class="py-2">{{ $invoice->payer->name }} <span class="text-xs text-gray-400">{{ $invoice->payer->phone }}</span></td>
                        <td class="py-2">{{ $invoice->period_start->format('Y-m') }}</td>
                        <td class="py-2">{{ $invoice->total()->format() }}</td>
                        <td class="py-2 font-medium">{{ $invoice->balance()->format() }}</td>
                        <td class="py-2 {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                            {{ $invoice->due_on->toDateString() }}
                            @if ($invoice->isOverdue())<span class="text-xs">(متأخرة {{ $invoice->daysOverdue() }} يوم)</span>@endif
                        </td>
                        <td class="py-2">@include('nursery.finance.invoices.partials.status', ['status' => $invoice->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-gray-400">لا توجد فواتير. سجّل الأطفال في بنود الرسوم ثم أصدر فواتير الشهر.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>
@endsection
