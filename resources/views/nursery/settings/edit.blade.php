@extends('nursery.layout')
@section('title', 'إعدادات الحضانة')

@section('content')
    <form method="POST" action="{{ route('nursery.settings.update') }}" class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm max-w-2xl space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium mb-1">اسم الحضانة</label>
            <input name="name" value="{{ old('name', $tenant->name) }}" class="w-full rounded-lg border-gray-200 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">الهاتف</label>
            <input name="phone" value="{{ old('phone', $tenant->phone) }}" class="w-full rounded-lg border-gray-200 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">العنوان</label>
            <textarea name="address" rows="2" class="w-full rounded-lg border-gray-200 text-sm">{{ old('address', $tenant->address) }}</textarea>
        </div>
        @rolledout('bahga-pay')
            <div class="pt-4 border-t border-gray-50">
                <label for="tuition_due_day" class="block text-sm font-medium mb-1">يوم استحقاق فواتير المصروفات</label>
                <select id="tuition_due_day" name="tuition_due_day" class="rounded-lg border-gray-200 text-sm">
                    @php($dueDay = (int) old('tuition_due_day', $tenant->settings['tuition_due_day'] ?? \App\Services\Tuition\TuitionBillingService::DEFAULT_DUE_DAY))
                    @foreach (range(1, 28) as $day)
                        <option value="{{ $day }}" @selected($dueDay === $day)>يوم {{ $day }} من الشهر</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">يسري على الفواتير التي تصدر بعد الحفظ.</p>
                @error('tuition_due_day')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
        @endrolledout
        <div class="pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">حفظ الإعدادات</button>
        </div>
    </form>
@endsection
