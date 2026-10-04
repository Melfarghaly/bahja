@extends('admin.layout')
@section('title', 'أكواد الخصم')

@section('content')
    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">خصومات على اشتراك الحضانة في المنصة — ومنها برنامج «الخمسون المؤسِّسون».</p>
        <a href="{{ route('admin.coupons.create') }}" class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">+ كود جديد</a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right">
                <tr>
                    <th class="pb-2 font-medium">الكود</th><th class="pb-2 font-medium">الاسم</th><th class="pb-2 font-medium">الخصم</th>
                    <th class="pb-2 font-medium">المدة</th><th class="pb-2 font-medium">الاستخدام</th><th class="pb-2 font-medium">الحالة</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($coupons as $coupon)
                    <tr>
                        <td class="py-2 font-mono">{{ $coupon->code }}</td>
                        <td class="py-2">{{ $coupon->name }}</td>
                        <td class="py-2">{{ $coupon->percent_off }}%</td>
                        <td class="py-2 text-gray-500">{{ $coupon->duration->label() }}@if ($coupon->duration_months) ({{ $coupon->duration_months }} شهر)@endif</td>
                        <td class="py-2">{{ $coupon->redemptions_count }} / {{ $coupon->max_redemptions ?? '∞' }}</td>
                        <td class="py-2">
                            @if (! $coupon->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">موقوف</span>
                            @elseif ($coupon->isExpired() || $coupon->isExhausted())
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">منتهٍ</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">فعّال</span>
                            @endif
                        </td>
                        <td class="py-2 text-left">
                            <form method="POST" action="{{ route('admin.coupons.status', $coupon) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $coupon->is_active ? 0 : 1 }}">
                                <button class="text-xs hover:underline {{ $coupon->is_active ? 'text-red-500' : 'brand-text' }}">{{ $coupon->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-3 text-center text-gray-400">لا توجد أكواد.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $coupons->links() }}</div>
    </div>
@endsection
