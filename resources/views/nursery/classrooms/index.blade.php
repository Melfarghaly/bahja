@extends('nursery.layout')
@section('title', 'الفصول')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2">
            <h2 class="font-bold mb-4">فصول الحضانة</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @forelse ($classrooms as $classroom)
                    <div class="border border-gray-100 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold">{{ $classroom->name }}</h3>
                            <form method="POST" action="{{ route('nursery.classrooms.destroy', $classroom) }}" onsubmit="return confirm('حذف الفصل؟ سيتم فك ارتباط الأطفال به.')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">حذف</button>
                            </form>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ $classroom->children_count }} طفل · سعة {{ $classroom->capacity ?? '—' }}</p>
                        <form method="POST" action="{{ route('nursery.classrooms.update', $classroom) }}" class="mt-3 flex gap-2">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $classroom->name }}" class="flex-1 rounded-lg border-gray-200 text-sm">
                            <input name="capacity" type="number" value="{{ $classroom->capacity }}" placeholder="سعة" class="w-20 rounded-lg border-gray-200 text-sm">
                            <button class="brand-bg text-white px-3 rounded-lg text-xs">حفظ</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">لا توجد فصول.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
            <h2 class="font-bold mb-4">فصل جديد</h2>
            <form method="POST" action="{{ route('nursery.classrooms.store') }}" class="space-y-3">
                @csrf
                <input name="name" placeholder="اسم الفصل" class="w-full rounded-lg border-gray-200 text-sm">
                <input name="capacity" type="number" placeholder="السعة (اختياري)" class="w-full rounded-lg border-gray-200 text-sm">
                <button class="w-full brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">إضافة الفصل</button>
            </form>
        </div>
    </div>
@endsection
