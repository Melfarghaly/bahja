@extends('nursery.layout')
@section('title', 'الأطفال')

@section('content')
    @if (session('import_errors') && count(session('import_errors')) > 0)
        <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            <div class="font-medium mb-1">صفوف لم تُستورد:</div>
            <ul class="list-disc pr-5 space-y-0.5">
                @foreach (session('import_errors') as $err)<li>صف {{ $err['row'] }}: {{ $err['message'] }}</li>@endforeach
            </ul>
        </div>
    @endif
    <div class="flex items-center justify-between">
        <form method="GET" class="flex gap-2 items-end flex-1">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="بحث بالاسم"
                   class="rounded-lg border-gray-200 text-sm w-56">
            <select name="classroom" class="rounded-lg border-gray-200 text-sm">
                <option value="">كل الفصول</option>
                @foreach ($classrooms as $c)
                    <option value="{{ $c->id }}" @selected(($filters['classroom'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <button class="brand-bg text-white px-4 py-2 rounded-lg text-sm">تصفية</button>
        </form>
        <div class="flex gap-2">
            <a href="{{ route('nursery.children.import.form') }}" class="border border-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">⬆ استيراد من Excel</a>
            <a href="{{ route('nursery.children.create') }}" class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">+ تسجيل طفل</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-right">
                <tr>
                    <th class="px-4 py-3 font-medium">الطفل</th>
                    <th class="px-4 py-3 font-medium">الفصل</th>
                    <th class="px-4 py-3 font-medium">الأوصياء</th>
                    <th class="px-4 py-3 font-medium">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($children as $child)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('nursery.children.show', $child) }}" class="font-medium hover:underline">{{ $child->first_name }} {{ $child->last_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $child->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $child->guardians_count }}</td>
                        <td class="px-4 py-3">@include('nursery.partials.child-status', ['status' => $child->status->value])</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">لا يوجد أطفال.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $children->links() }}</div>
@endsection
