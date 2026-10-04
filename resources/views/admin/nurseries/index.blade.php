@extends('admin.layout')
@section('title', 'الحضانات')

@section('content')
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs text-gray-500 mb-1">بحث</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم الحضانة أو المعرّف"
                   class="w-full rounded-lg border-gray-200 focus:border-[color:var(--brand-primary)] focus:ring-0 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">الحالة</label>
            <select name="status" class="rounded-lg border-gray-200 text-sm">
                <option value="">الكل</option>
                @foreach (['active' => 'نشطة', 'trial' => 'تجريبية', 'suspended' => 'معلّقة', 'cancelled' => 'ملغاة'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">تصفية</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-right">
                <tr>
                    <th class="px-4 py-3 font-medium">الحضانة</th>
                    <th class="px-4 py-3 font-medium">الحالة</th>
                    <th class="px-4 py-3 font-medium">الخطة</th>
                    <th class="px-4 py-3 font-medium">الأطفال</th>
                    <th class="px-4 py-3 font-medium">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($tenants as $tenant)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.nurseries.show', $tenant) }}" class="font-medium hover:underline">{{ $tenant->name }}</a>
                            <div class="text-xs text-gray-400">{{ $tenant->slug }}</div>
                        </td>
                        <td class="px-4 py-3">@include('admin.partials.status-badge', ['status' => $tenant->status->value])</td>
                        <td class="px-4 py-3 text-gray-600">{{ $tenant->activeSubscription->first()?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $tenant->children_count }}</td>
                        <td class="px-4 py-3">
                            @if ($tenant->status->value === 'suspended')
                                <form method="POST" action="{{ route('admin.nurseries.activate', $tenant) }}">
                                    @csrf @method('PATCH')
                                    <button class="text-green-600 hover:underline text-xs font-medium">تفعيل</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.nurseries.suspend', $tenant) }}"
                                      onsubmit="return confirm('تعليق هذه الحضانة؟')">
                                    @csrf @method('PATCH')
                                    <button class="text-amber-600 hover:underline text-xs font-medium">تعليق</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">لا توجد حضانات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $tenants->links() }}</div>
@endsection
