@extends('nursery.layout')
@section('title', $child->first_name.' '.$child->last_name)

@section('content')
    <a href="{{ route('nursery.children.index') }}" class="text-sm text-gray-500 hover:underline">→ رجوع للأطفال</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-1 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold">{{ $child->first_name }} {{ $child->last_name }}</h2>
                @include('nursery.partials.child-status', ['status' => $child->status->value])
            </div>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-400">تاريخ الميلاد</dt><dd>{{ $child->birth_date?->toDateString() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">النوع</dt><dd>{{ $child->gender->value === 'male' ? 'ذكر' : 'أنثى' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">الفصل</dt><dd>{{ $child->classroom?->name ?? '—' }}</dd></div>
            </dl>
            <a href="{{ route('nursery.children.edit', $child) }}" class="inline-block mt-2 text-sm brand-text hover:underline">تعديل البيانات</a>
        </div>

        {{-- Guardians (the M:N relationship surfaced) --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2 space-y-4">
            <h3 class="font-bold">الأوصياء</h3>
            <table class="w-full text-sm">
                <thead class="text-gray-400 text-right"><tr>
                    <th class="pb-2 font-medium">الاسم</th><th class="pb-2 font-medium">الصلة</th>
                    <th class="pb-2 font-medium">استلام</th><th class="pb-2 font-medium">الحائط</th><th class="pb-2 font-medium">يدفع</th><th class="pb-2 font-medium"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($child->guardians as $guardian)
                        <tr>
                            <td class="py-2.5"><span class="font-medium">{{ $guardian->name }}</span><div class="text-xs text-gray-400">{{ $guardian->phone }}</div></td>
                            <td class="py-2.5 text-gray-500">{{ $guardian->pivot->relationship }}</td>
                            <td class="py-2.5">{!! $guardian->pivot->can_pickup ? '<span class="text-green-600">✔</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                            <td class="py-2.5">{!! $guardian->pivot->can_view_wall ? '<span class="text-green-600">✔</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                            <td class="py-2.5">
                                @if ($guardian->pivot->is_payer)
                                    <span class="text-green-600">✔</span>
                                    @if ($guardian->pivot->billing_share_bp)<span class="text-xs text-gray-500">{{ rtrim(rtrim(number_format($guardian->pivot->billing_share_bp / 100, 2), '0'), '.') }}%</span>@endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="py-2.5">
                                <form method="POST" action="{{ route('nursery.children.guardians.destroy', [$child, $guardian]) }}" onsubmit="return confirm('فصل وليّ الأمر؟')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:underline">فصل</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-center text-gray-400">لا يوجد أوصياء.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <details class="pt-3 border-t border-gray-100">
                <summary class="cursor-pointer text-sm font-medium brand-text">+ إضافة وليّ أمر</summary>
                <form method="POST" action="{{ route('nursery.children.guardians.store', $child) }}" class="mt-3 grid grid-cols-2 gap-3">
                    @csrf
                    <input name="name" placeholder="الاسم" class="rounded-lg border-gray-200 text-sm">
                    <input name="phone" placeholder="الهاتف" class="rounded-lg border-gray-200 text-sm">
                    <select name="relationship" class="rounded-lg border-gray-200 text-sm">
                        @foreach (['mother' => 'الأم', 'father' => 'الأب', 'grandparent' => 'جد/جدة', 'nanny' => 'مربية', 'driver' => 'سائق', 'other' => 'أخرى'] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <select name="role" class="rounded-lg border-gray-200 text-sm">
                        <option value="pickup_authorized">مخوّل بالاستلام</option>
                        <option value="viewer">مشاهد فقط</option>
                        <option value="emergency_contact">جهة طوارئ</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="can_pickup" value="0"><input type="checkbox" name="can_pickup" value="1" checked class="rounded"> مخوّل بالاستلام</label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="can_view_wall" value="0"><input type="checkbox" name="can_view_wall" value="1" checked class="rounded"> يرى الحائط</label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_payer" value="0"><input type="checkbox" name="is_payer" value="1" class="rounded"> مسؤول عن الدفع</label>
                    <input name="billing_share_percent" inputmode="decimal" placeholder="نسبة المساهمة % (عند تعدد الدافعين)" class="rounded-lg border-gray-200 text-sm">
                    @error('billing_share_percent')<p class="col-span-2 text-xs text-red-500">{{ $message }}</p>@enderror
                    <div class="col-span-2"><button class="brand-bg text-white px-5 py-2 rounded-lg text-sm">إضافة</button></div>
                </form>
            </details>
        </div>
    </div>

    @if ($managesFees)
        @include('nursery.children.partials.fees')
    @endif

    {{-- Recent attendance --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <h3 class="font-bold mb-3">آخر سجلات الحضور</h3>
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">التاريخ</th><th class="pb-2 font-medium">حضور</th><th class="pb-2 font-medium">انصراف</th><th class="pb-2 font-medium">تحقق الاستلام</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($child->attendances as $a)
                    <tr>
                        <td class="py-2">{{ $a->date?->toDateString() }}</td>
                        <td class="py-2 text-gray-500">{{ $a->checked_in_at?->format('H:i') ?? '—' }}</td>
                        <td class="py-2 text-gray-500">{{ $a->checked_out_at?->format('H:i') ?? '—' }}</td>
                        <td class="py-2">{!! $a->pickup_verified ? '<span class="text-green-600">موثّق</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-center text-gray-400">لا توجد سجلات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
