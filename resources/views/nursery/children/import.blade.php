@extends('nursery.layout')
@section('title', 'استيراد الأطفال من Excel')

@section('content')
    <a href="{{ route('nursery.children.index') }}" class="text-sm text-gray-500 hover:underline">→ رجوع للأطفال</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2 space-y-5">
            <h2 class="font-bold">رفع ملف Excel أو CSV</h2>
            <form method="POST" action="{{ route('nursery.children.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center">
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="text-sm">
                    <p class="text-xs text-gray-400 mt-2">الصيغ المدعومة: xlsx, csv — بحد أقصى 5 ميجابايت.</p>
                </div>
                <button class="brand-bg text-white px-6 py-2.5 rounded-lg text-sm font-medium">استيراد الأطفال</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-3">
            <h3 class="font-bold">تعليمات</h3>
            <ul class="text-sm text-gray-600 space-y-2 list-disc pr-5">
                <li>الصف الأول للعناوين، ثم صف لكل طفل.</li>
                <li>الأعمدة المطلوبة: <strong>الاسم الأول، تاريخ الميلاد، النوع، اسم ولي الأمر، هاتف ولي الأمر</strong>.</li>
                <li>أعمدة اختيارية: اسم العائلة، الفصل، صلة القرابة.</li>
                <li>تاريخ الميلاد بصيغة YYYY-MM-DD.</li>
                <li>يُنشأ حساب وليّ الأمر تلقائياً عبر رقم الهاتف ويُمنح صلاحية الاستلام.</li>
            </ul>
            <a href="{{ route('nursery.children.import.template') }}" class="inline-block mt-2 text-sm font-medium brand-text hover:underline">⬇ تحميل القالب (CSV)</a>
        </div>
    </div>
@endsection
