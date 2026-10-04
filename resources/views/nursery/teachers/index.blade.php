@extends('nursery.layout')
@section('title', 'المعلمات')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm lg:col-span-2">
            <h2 class="font-bold mb-4">معلمات الحضانة</h2>
            <table class="w-full text-sm">
                <thead class="text-gray-400 text-right"><tr>
                    <th class="pb-2 font-medium">الاسم</th><th class="pb-2 font-medium">الدور</th>
                    <th class="pb-2 font-medium">النوع</th><th class="pb-2 font-medium">الحالة</th><th class="pb-2 font-medium"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td class="py-2.5"><span class="font-medium">{{ $teacher->name }}</span><div class="text-xs text-gray-400">{{ $teacher->phone }}</div></td>
                            <td class="py-2.5 text-gray-500">{{ $teacher->pivot->role }}</td>
                            <td class="py-2.5 text-gray-500">{{ $teacher->pivot->employment_type }}</td>
                            <td class="py-2.5 text-gray-500">{{ $teacher->pivot->status }}</td>
                            <td class="py-2.5">
                                <form method="POST" action="{{ route('nursery.teachers.destroy', $teacher) }}" onsubmit="return confirm('إزالة المعلمة من الحضانة؟')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:underline">إزالة</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-center text-gray-400">لا توجد معلمات.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $teachers->links() }}</div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
            <h2 class="font-bold mb-4">إضافة معلمة</h2>
            <form method="POST" action="{{ route('nursery.teachers.store') }}" class="space-y-3">
                @csrf
                <input name="name" placeholder="الاسم" class="w-full rounded-lg border-gray-200 text-sm">
                <input name="phone" placeholder="الهاتف" class="w-full rounded-lg border-gray-200 text-sm">
                <input name="email" placeholder="البريد (اختياري)" class="w-full rounded-lg border-gray-200 text-sm">
                <select name="role" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="teacher">معلمة</option>
                    <option value="head_teacher">معلمة أولى</option>
                    <option value="assistant">مساعدة</option>
                </select>
                <select name="employment_type" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="full_time">دوام كامل</option>
                    <option value="part_time">دوام جزئي</option>
                    <option value="substitute">بديلة</option>
                </select>
                <select name="classroom_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— بدون فصل —</option>
                    @foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <button class="w-full brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">إضافة</button>
            </form>
        </div>
    </div>
@endsection
