@extends('admin.layout')
@section('title', 'كود خصم جديد')

@section('content')
    <form method="POST" action="{{ route('admin.coupons.store') }}" class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4 max-w-2xl">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">الكود</label>
                <input name="code" value="{{ old('code') }}" class="w-full rounded-lg border-gray-200 text-sm font-mono uppercase" required>
                @error('code')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">الاسم</label>
                <input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-200 text-sm" required>
                @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نسبة الخصم %</label>
                <input type="number" name="percent_off" value="{{ old('percent_off', 35) }}" min="1" max="100" class="w-full rounded-lg border-gray-200 text-sm" required>
                @error('percent_off')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">المدة</label>
                <select name="duration" class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($durations as $duration)
                        <option value="{{ $duration->value }}" @selected(old('duration', 'forever') === $duration->value)>{{ $duration->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">عدد الأشهر (للمدة المتكررة)</label>
                <input type="number" name="duration_months" value="{{ old('duration_months') }}" min="1" max="36" class="w-full rounded-lg border-gray-200 text-sm">
                @error('duration_months')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">الحد الأقصى للاستخدام</label>
                <input type="number" name="max_redemptions" value="{{ old('max_redemptions') }}" min="1" placeholder="غير محدود" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">صالح حتى</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="w-full rounded-lg border-gray-200 text-sm">
                @error('valid_until')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium mb-2">الخطط المشمولة (بدون اختيار = كل الخطط المدفوعة)</legend>
            <div class="flex flex-wrap gap-4">
                @foreach ($plans as $plan)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="plan_slugs[]" value="{{ $plan->slug }}" @checked(in_array($plan->slug, old('plan_slugs', []), true)) class="rounded">
                        {{ $plan->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex gap-3 pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">إنشاء الكود</button>
            <a href="{{ route('admin.coupons.index') }}" class="px-6 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">إلغاء</a>
        </div>
    </form>
@endsection
