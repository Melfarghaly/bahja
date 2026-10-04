@extends('admin.layout')
@section('title', 'لوحة المؤشرات')

@section('header')
    <div>
        <h1 class="text-2xl font-extrabold">لوحة مؤشرات المنصة</h1>
        <p class="text-sm text-gray-500 mt-1">نظرة عامة على كل الحضانات · {{ now()->translatedFormat('l j F') }}</p>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-5">
        {{-- Accent: MRR --}}
        <div class="rounded-3xl p-6 text-white flex flex-col justify-between" style="background:linear-gradient(135deg, var(--coral), color-mix(in srgb, var(--coral) 80%, #000 20%))">
            <div class="text-sm opacity-90">الإيراد الشهري</div>
            <div class="text-3xl font-extrabold mt-2">{{ number_format($kpis['mrr_egp']) }}<span class="text-base font-normal opacity-90"> ج</span></div>
            <div class="text-xs opacity-90 mt-1">MRR تقديري</div>
        </div>

        @foreach ([
            ['إجمالي الحضانات', $kpis['tenants_total'], $kpis['tenants_active'].' نشطة'],
            ['الاشتراكات النشطة', $kpis['subscriptions_active'], 'اشتراك'],
            ['إجمالي الأطفال', number_format($kpis['children_total']), 'طفل'],
            ['إجمالي المستخدمين', number_format($kpis['users_total']), 'مستخدم'],
        ] as $card)
            <div class="card p-6 flex flex-col justify-between">
                <div class="text-sm text-gray-400">{{ $card[0] }}</div>
                <div class="text-3xl font-extrabold brand-text mt-2">{{ $card[1] }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $card[2] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card p-6">
            <h2 class="font-bold mb-4">التوزيع حسب الخطة</h2>
            @php($maxPlan = collect($tenantsByPlan)->max() ?: 1)
            <div class="space-y-3">
                @forelse ($tenantsByPlan as $plan => $count)
                    <div>
                        <div class="flex justify-between text-sm mb-1"><span>{{ $plan }}</span><span class="text-gray-500">{{ $count }}</span></div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ ($count / $maxPlan) * 100 }}%; background:var(--coral)"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">لا توجد اشتراكات بعد.</p>
                @endforelse
            </div>
        </div>

        <div class="card p-6 lg:col-span-2">
            <h2 class="font-bold mb-4">أحدث الحضانات</h2>
            <table class="w-full text-sm">
                <thead class="text-gray-400 text-right">
                    <tr><th class="pb-2 font-medium">الحضانة</th><th class="pb-2 font-medium">الحالة</th><th class="pb-2 font-medium">الأطفال</th><th class="pb-2 font-medium">انضمّت</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($recentTenants as $tenant)
                        <tr>
                            <td class="py-2.5"><a href="{{ route('admin.nurseries.show', $tenant) }}" class="font-medium hover:underline">{{ $tenant->name }}</a></td>
                            <td class="py-2.5">@include('admin.partials.status-badge', ['status' => $tenant->status->value])</td>
                            <td class="py-2.5">{{ $tenant->children_count }}</td>
                            <td class="py-2.5 text-gray-500">{{ $tenant->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">لا توجد بيانات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
