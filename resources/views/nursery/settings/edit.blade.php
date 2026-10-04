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
        <div class="pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">حفظ الإعدادات</button>
        </div>
    </form>
@endsection
