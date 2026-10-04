@extends('nursery.layout')
@section('title', 'الحضور والانصراف')

@section('content')
    <p class="text-sm text-gray-500">سجلّ اليوم: {{ now()->translatedFormat('l j F Y') }}</p>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-right">
                <tr>
                    <th class="px-4 py-3 font-medium">الطفل</th>
                    <th class="px-4 py-3 font-medium">الفصل</th>
                    <th class="px-4 py-3 font-medium">الحضور</th>
                    <th class="px-4 py-3 font-medium">الانصراف</th>
                    <th class="px-4 py-3 font-medium">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($children as $child)
                    @php($today = $child->attendances->first())
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-3 font-medium">{{ $child->first_name }} {{ $child->last_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $child->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $today?->checked_in_at?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $today?->checked_out_at?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if (! $today || ! $today->checked_in_at)
                                <form method="POST" action="{{ route('nursery.attendance.check-in', $child) }}">
                                    @csrf
                                    <button class="text-xs font-medium text-green-600 hover:underline">تسجيل حضور</button>
                                </form>
                            @elseif (! $today->checked_out_at)
                                <form method="POST" action="{{ route('nursery.attendance.check-out', $child) }}" class="flex items-center gap-2">
                                    @csrf
                                    <select name="collector_id" class="rounded-lg border-gray-200 text-xs py-1">
                                        @foreach ($child->guardians as $g)
                                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="text-xs font-medium brand-text hover:underline">انصراف</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">مكتمل</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">لا يوجد أطفال نشطون.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400">عند الانصراف يتحقّق النظام أن المستلِم ضمن المخوَّلين بالاستلام لهذا الطفل.</p>
@endsection
