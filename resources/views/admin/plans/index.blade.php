@extends('admin.layout')
@section('title', 'الخطط والاشتراكات')

@section('content')
    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">إدارة خطط الاشتراك (كتالوج التسعير العام للمنصة).</p>
        <a href="{{ route('admin.plans.create') }}" class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">+ خطة جديدة</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @forelse ($plans as $plan)
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-lg">{{ $plan->name }}</h3>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $plan->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $plan->is_active ? 'مفعّلة' : 'متوقفة' }}
                    </span>
                </div>
                <div class="mt-3 text-3xl font-extrabold brand-text">{{ number_format($plan->price_egp) }} <span class="text-sm font-normal text-gray-400">ج/{{ $plan->billing_cycle->value === 'yearly' ? 'سنة' : 'شهر' }}</span></div>
                <ul class="mt-4 space-y-1.5 text-sm text-gray-600 flex-1">
                    <li>الأطفال: {{ $plan->max_children ?? 'غير محدود' }}</li>
                    <li>المعلمات: {{ $plan->max_teachers ?? 'غير محدود' }}</li>
                    <li>رسائل SMS: {{ number_format($plan->included_sms) }}</li>
                    <li class="text-gray-400">اشتراكات قائمة: {{ $plan->subscriptions_count }}</li>
                </ul>
                <div class="mt-4 flex gap-2 pt-4 border-t border-gray-50">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="text-xs font-medium brand-text hover:underline">تعديل</a>
                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('حذف هذه الخطة؟')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-red-500 hover:underline">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400">لا توجد خطط. أنشئ أول خطة.</p>
        @endforelse
    </div>
@endsection
