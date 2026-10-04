@extends('nursery.layout')
@section('title', $child->exists ? 'تعديل بيانات طفل' : 'تسجيل طفل جديد')

@section('content')
    @php($action = $child->exists ? route('nursery.children.update', $child) : route('nursery.children.store'))
    <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm max-w-3xl space-y-6">
        @csrf
        @if ($child->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">الاسم الأول</label>
                <input name="first_name" value="{{ old('first_name', $child->first_name) }}" class="w-full rounded-lg border-gray-200 text-sm"></div>
            <div><label class="block text-sm font-medium mb-1">اسم العائلة</label>
                <input name="last_name" value="{{ old('last_name', $child->last_name) }}" class="w-full rounded-lg border-gray-200 text-sm"></div>
            <div><label class="block text-sm font-medium mb-1">تاريخ الميلاد</label>
                <input type="date" name="birth_date" value="{{ old('birth_date', $child->birth_date?->toDateString()) }}" class="w-full rounded-lg border-gray-200 text-sm"></div>
            <div><label class="block text-sm font-medium mb-1">النوع</label>
                <select name="gender" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="male" @selected(old('gender', $child->gender?->value) === 'male')>ذكر</option>
                    <option value="female" @selected(old('gender', $child->gender?->value) === 'female')>أنثى</option>
                </select></div>
            <div><label class="block text-sm font-medium mb-1">الفصل</label>
                <select name="classroom_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— بدون —</option>
                    @foreach ($classrooms as $c)
                        <option value="{{ $c->id }}" @selected(old('classroom_id', $child->classroom_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select></div>
            @if ($child->exists)
                <div><label class="block text-sm font-medium mb-1">الحالة</label>
                    <select name="status" class="w-full rounded-lg border-gray-200 text-sm">
                        @foreach (['active' => 'نشط', 'graduated' => 'متخرّج', 'withdrawn' => 'منسحب'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $child->status?->value) === $v)>{{ $l }}</option>
                        @endforeach
                    </select></div>
            @endif
        </div>

        @unless ($child->exists)
            <div class="pt-4 border-t border-gray-100">
                <h3 class="font-bold mb-1">وليّ الأمر الأساسي</h3>
                <p class="text-xs text-gray-400 mb-3">يُنشأ له حساب تلقائياً عبر رقم الهاتف. يمكن إضافة أوصياء آخرين لاحقاً.</p>
                <div class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="guardians[0][role]" value="primary">
                    <input type="hidden" name="guardians[0][can_view_wall]" value="1">
                    <input type="hidden" name="guardians[0][can_pickup]" value="1">
                    <input type="hidden" name="guardians[0][is_payer]" value="1">
                    <div><label class="block text-sm font-medium mb-1">الاسم</label>
                        <input name="guardians[0][name]" value="{{ old('guardians.0.name') }}" class="w-full rounded-lg border-gray-200 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">الهاتف</label>
                        <input name="guardians[0][phone]" value="{{ old('guardians.0.phone') }}" class="w-full rounded-lg border-gray-200 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">صلة القرابة</label>
                        <select name="guardians[0][relationship]" class="w-full rounded-lg border-gray-200 text-sm">
                            @foreach (['mother' => 'الأم', 'father' => 'الأب', 'grandparent' => 'جد/جدة', 'other' => 'أخرى'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select></div>
                </div>
            </div>
        @endunless

        <div class="flex gap-3 pt-4 border-t border-gray-50">
            <button class="brand-bg text-white px-6 py-2 rounded-lg text-sm font-medium">{{ $child->exists ? 'حفظ' : 'تسجيل الطفل' }}</button>
            <a href="{{ route('nursery.children.index') }}" class="px-6 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">إلغاء</a>
        </div>
    </form>
@endsection
