@extends('admin.layout')
@section('title', $plan->exists ? 'تعديل خطة' : 'خطة جديدة')

@section('content')
    @php($action = $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store'))
    <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm max-w-2xl space-y-5">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">اسم الخطة</label>
                <input name="name" value="{{ old('name', $plan->name) }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">المعرّف (slug)</label>
                <input name="slug" value="{{ old('slug', $plan->slug) }}" class="w-full rounded-lg border-gray-200 text-sm" placeholder="pro">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">السعر (ج)</label>
                <input type="number" name="price_egp" value="{{ old('price_egp', $plan->price_egp) }}" min="0" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">دورة الفوترة</label>
                <select name="billing_cycle" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="monthly" @selected(old('billing_cycle', $plan->billing_cycle?->value) === 'monthly')>شهري</option>
                    <option value="yearly" @selected(old('billing_cycle', $plan->billing_cycle?->value) === 'yearly')>سنوي</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">حد الأطفال</label>
                <input type="number" name="max_children" value="{{ old('max_children', $plan->max_children) }}" min="1" placeholder="غير محدود" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">حد المعلمات</label>
                <input type="number" name="max_teachers" value="{{ old('max_teachers', $plan->max_teachers) }}" min="1" placeholder="غير محدود" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رسائل SMS</label>
                <input type="number" name="included_sms" value="{{ old('included_sms', $plan->included_sms ?? 0) }}" min="0" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium mb-2">المزايا المتضمّنة</legend>
            @php($selectedFeatures = old('features', $plan->features ?? []))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach (\App\Enums\Feature::cases() as $feature)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="features[]" value="{{ $feature->value }}" @checked(in_array($feature->value, $selectedFeatures, true)) class="rounded">
                        {{ $feature->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded">
            خطة مفعّلة
        </label>

        <div class="flex gap-3 pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">{{ $plan->exists ? 'حفظ التعديلات' : 'إنشاء الخطة' }}</button>
            <a href="{{ route('admin.plans.index') }}" class="px-6 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">إلغاء</a>
        </div>
    </form>
@endsection
