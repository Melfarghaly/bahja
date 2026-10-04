@extends('nursery.layout')
@section('title', 'الاشتراك والفواتير')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
            <h2 class="font-bold mb-3">الخطة الحالية</h2>
            @if ($plan)
                <div class="text-3xl font-extrabold brand-text">{{ $plan->name }}</div>
                <div class="text-sm text-gray-500 mt-1">{{ number_format($plan->price_egp) }} ج / {{ $plan->billing_cycle->value === 'yearly' ? 'سنة' : 'شهر' }}</div>
                <div class="mt-4 text-sm">
                    <div class="flex justify-between mb-1"><span>الأطفال</span><span>{{ $childrenCount }} / {{ $plan->max_children ?? '∞' }}</span></div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full brand-bg" style="width: {{ $plan->max_children ? min(100, ($childrenCount / max($plan->max_children,1))*100) : 8 }}%"></div>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400">لا يوجد اشتراك نشط.</p>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2">
            <h2 class="font-bold mb-4">تغيير الخطة</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($plans as $p)
                    <form method="POST" action="{{ route('nursery.subscription.change-plan') }}"
                          class="border {{ $plan && $plan->id === $p->id ? 'border-[color:var(--brand-primary)]' : 'border-gray-100' }} rounded-xl p-4 flex flex-col">
                        @csrf
                        <input type="hidden" name="subscription_plan_id" value="{{ $p->id }}">
                        <div class="font-bold">{{ $p->name }}</div>
                        <div class="text-sm text-gray-500">{{ number_format($p->price_egp) }} ج/{{ $p->billing_cycle->value === 'yearly' ? 'سنة' : 'شهر' }}</div>
                        <div class="text-xs text-gray-400 mt-1">حتى {{ $p->max_children ?? '∞' }} طفل</div>
                        @if ($plan && $plan->id === $p->id)
                            <span class="mt-3 text-xs text-center brand-text font-medium">الخطة الحالية</span>
                        @else
                            <button class="mt-3 brand-bg text-white px-4 py-1.5 rounded-lg text-xs font-medium">التحويل لهذه الخطة</button>
                        @endif
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <h2 class="font-bold mb-4">الفواتير</h2>
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">الرقم</th><th class="pb-2 font-medium">المبلغ</th><th class="pb-2 font-medium">الحالة</th><th class="pb-2 font-medium">الاستحقاق</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="py-2">{{ $invoice->number }}</td>
                        <td class="py-2">{{ number_format($invoice->amount_egp) }} ج</td>
                        <td class="py-2 text-gray-500">{{ $invoice->status->value }}</td>
                        <td class="py-2 text-gray-500">{{ $invoice->due_at?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-center text-gray-400">لا توجد فواتير.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
