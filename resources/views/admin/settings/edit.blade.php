@extends('admin.layout')
@section('title', 'الهوية البصرية')

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm max-w-2xl space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">اسم العلامة</label>
            <input name="brand_name" value="{{ old('brand_name', $settings->brand_name) }}" class="w-full rounded-lg border-gray-200 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">الشعار (اللوجو)</label>
            <div class="flex items-center gap-4">
                @if ($settings->logo_path)
                    <img src="{{ asset('storage/'.$settings->logo_path) }}" class="h-14 w-14 rounded-lg object-cover border border-gray-200">
                @else
                    <span class="h-14 w-14 rounded-lg brand-bg text-white grid place-items-center font-extrabold">ب</span>
                @endif
                <input type="file" name="logo" accept="image/*" class="text-sm">
            </div>
            <p class="text-xs text-gray-400 mt-1">PNG / JPG / SVG / WEBP — بحد أقصى 2MB.</p>
        </div>

        <div class="grid grid-cols-3 gap-4">
            @foreach (['primary_color' => 'اللون الأساسي', 'secondary_color' => 'اللون الثانوي', 'accent_color' => 'لون التمييز'] as $field => $label)
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $label }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="{{ $field }}" value="{{ old($field, $settings->$field) }}" class="h-10 w-12 rounded border-gray-200 p-0.5">
                        <input type="text" value="{{ old($field, $settings->$field) }}" readonly class="w-full rounded-lg border-gray-200 text-sm bg-gray-50"
                               oninput="this.previousElementSibling.value=this.value">
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">بريد الدعم</label>
                <input name="support_email" value="{{ old('support_email', $settings->support_email) }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">هاتف الدعم</label>
                <input name="support_phone" value="{{ old('support_phone', $settings->support_phone) }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">حفظ الإعدادات</button>
        </div>
    </form>
@endsection
