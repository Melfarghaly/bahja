@extends('admin.layout')
@section('title', 'الإطلاق التدريجي')

@section('content')
    <p class="text-sm text-gray-500">
        تحكّم في ظهور وحدات V2. الإطلاق لكل الحضانات يعيد ضبط الاختيارات الفردية لكل حضانة. للإطلاق التجريبي لحضانة بعينها افتح صفحتها من قائمة الحضانات.
    </p>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">الوحدة</th><th class="pb-2 font-medium">المعرّف</th><th class="pb-2 font-medium">لكل الحضانات</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($flags as $row)
                    <tr>
                        <td class="py-3 font-medium">{{ $row['flag']->label() }}</td>
                        <td class="py-3 text-gray-400 font-mono text-xs">{{ $row['flag']->value }}</td>
                        <td class="py-3">
                            @if ($row['everyone'])
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">مُطلَقة للجميع</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">تجريبية / متوقفة</span>
                            @endif
                        </td>
                        <td class="py-3 text-left">
                            <form method="POST" action="{{ route('admin.rollouts.everyone', $row['flag']) }}"
                                  onsubmit="return confirm('سيُعاد ضبط الاختيار الفردي لكل الحضانات. متابعة؟')">
                                @csrf @method('PATCH')
                                <input type="hidden" name="active" value="{{ $row['everyone'] ? 0 : 1 }}">
                                <button class="{{ $row['everyone'] ? 'bg-amber-500' : 'brand-bg' }} text-white px-4 py-1.5 rounded-lg text-xs font-medium">
                                    {{ $row['everyone'] ? 'إيقاف للجميع' : 'إطلاق للجميع' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
