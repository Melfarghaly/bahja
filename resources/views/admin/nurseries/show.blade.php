@extends('admin.layout')
@section('title', $tenant->name)

@section('content')
    <a href="{{ route('admin.nurseries.index') }}" class="text-sm text-gray-500 hover:underline">→ رجوع للحضانات</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold">{{ $tenant->name }}</h2>
                @include('admin.partials.status-badge', ['status' => $tenant->status->value])
            </div>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-400">المعرّف</dt><dd>{{ $tenant->slug }}</dd></div>
                <div><dt class="text-gray-400">الهاتف</dt><dd>{{ $tenant->phone ?? '—' }}</dd></div>
                <div><dt class="text-gray-400">عدد الأطفال</dt><dd>{{ $tenant->children_count }}</dd></div>
                <div><dt class="text-gray-400">عدد الفصول</dt><dd>{{ $tenant->classrooms_count }}</dd></div>
                <div class="col-span-2"><dt class="text-gray-400">العنوان</dt><dd>{{ $tenant->address ?? '—' }}</dd></div>
            </dl>

            <div class="pt-4 border-t border-gray-100">
                @if ($tenant->status->value === 'suspended')
                    <form method="POST" action="{{ route('admin.nurseries.activate', $tenant) }}">
                        @csrf @method('PATCH')
                        <button class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">تفعيل الحضانة</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.nurseries.suspend', $tenant) }}" onsubmit="return confirm('تعليق هذه الحضانة؟')">
                        @csrf @method('PATCH')
                        <button class="bg-amber-500 text-white px-5 py-2 rounded-lg text-sm font-medium">تعليق الحضانة</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-3">
            <h3 class="font-bold">الاشتراك الحالي</h3>
            @php($sub = $tenant->activeSubscription->first())
            @if ($sub)
                <div class="text-sm space-y-2">
                    <div class="flex justify-between"><span class="text-gray-400">الخطة</span><span class="font-medium">{{ $sub->plan?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">السعر</span><span>{{ number_format($sub->plan?->price_egp ?? 0) }} ج/شهر</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">الحالة</span><span>{{ $sub->status->value }}</span></div>
                </div>
            @else
                <p class="text-sm text-gray-400">لا يوجد اشتراك نشط.</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <h3 class="font-bold mb-4">الأعضاء ({{ $tenant->members->count() }})</h3>
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">الاسم</th><th class="pb-2 font-medium">الدور</th><th class="pb-2 font-medium">الهاتف</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($tenant->members as $member)
                    <tr><td class="py-2">{{ $member->name }}</td><td class="py-2 text-gray-500">{{ $member->pivot->member_type }}</td><td class="py-2 text-gray-500">{{ $member->phone }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-3 text-center text-gray-400">لا يوجد أعضاء.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
