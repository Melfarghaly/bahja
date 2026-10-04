@extends('nursery.layout')
@section('title', 'إعداد الرسوم والخصومات')

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Fee plans --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm xl:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">بنود الرسوم</h2>
                <span class="text-xs text-gray-400">أي تعديل يسري على الفواتير القادمة فقط</span>
            </div>

            <div class="space-y-3">
                @forelse ($plans as $plan)
                    <details class="border border-gray-100 rounded-xl p-4 {{ $plan->is_active ? '' : 'opacity-60' }}">
                        <summary class="flex items-center justify-between cursor-pointer list-none">
                            <div>
                                <div class="font-bold">{{ $plan->name }}
                                    @unless ($plan->is_active)<span class="text-xs font-normal text-gray-400">(موقوفة)</span>@endunless
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $plan->frequency->label() }}
                                    @if ($plan->classroom) · فصل {{ $plan->classroom->name }} @endif
                                    · {{ $plan->active_children_count }} طفل مسجّل
                                </div>
                            </div>
                            <div class="text-lg font-extrabold brand-text">{{ $plan->amount()->format() }}</div>
                        </summary>

                        <form method="POST" action="{{ route('nursery.finance.fee-plans.update', $plan) }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-2 text-sm">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $plan->name }}" class="rounded-lg border-gray-200 text-sm md:col-span-2" required>
                            <input name="amount" value="{{ $plan->amount()->toPounds() }}" inputmode="decimal" class="rounded-lg border-gray-200 text-sm" required>
                            <select name="frequency" class="rounded-lg border-gray-200 text-sm">
                                @foreach ($frequencies as $frequency)
                                    <option value="{{ $frequency->value }}" @selected($plan->frequency === $frequency)>{{ $frequency->label() }}</option>
                                @endforeach
                            </select>
                            <select name="classroom_id" class="rounded-lg border-gray-200 text-sm md:col-span-2">
                                <option value="">كل الفصول</option>
                                @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected($plan->classroom_id === $classroom->id)>{{ $classroom->name }}</option>
                                @endforeach
                            </select>
                            <input name="description" value="{{ $plan->description }}" placeholder="وصف (اختياري)" class="rounded-lg border-gray-200 text-sm md:col-span-2">
                            <button class="brand-bg text-white px-4 py-2 rounded-lg text-xs font-medium md:col-span-3">حفظ التعديلات</button>
                        </form>
                        <form method="POST" action="{{ route('nursery.finance.fee-plans.toggle', $plan) }}" class="mt-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">
                            <button class="text-xs {{ $plan->is_active ? 'text-red-500' : 'brand-text' }} hover:underline">{{ $plan->is_active ? 'إيقاف البند' : 'إعادة تفعيل البند' }}</button>
                        </form>
                    </details>
                @empty
                    <p class="text-sm text-gray-400">لم تُضف أي رسوم بعد. ابدأ بالمصروفات الشهرية.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('nursery.finance.fee-plans.store') }}" class="pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-2 text-sm">
                @csrf
                <h3 class="font-bold md:col-span-4">بند رسوم جديد</h3>
                <input name="name" value="{{ old('name') }}" placeholder="مثلاً: المصروفات الشهرية" class="rounded-lg border-gray-200 text-sm md:col-span-2" required>
                <input name="amount" value="{{ old('amount') }}" placeholder="المبلغ بالجنيه" inputmode="decimal" class="rounded-lg border-gray-200 text-sm" required>
                <select name="frequency" class="rounded-lg border-gray-200 text-sm">
                    @foreach ($frequencies as $frequency)
                        <option value="{{ $frequency->value }}" @selected(old('frequency') === $frequency->value)>{{ $frequency->label() }}</option>
                    @endforeach
                </select>
                <select name="classroom_id" class="rounded-lg border-gray-200 text-sm md:col-span-2">
                    <option value="">كل الفصول</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <input name="description" value="{{ old('description') }}" placeholder="وصف (اختياري)" class="rounded-lg border-gray-200 text-sm md:col-span-2">
                @if ($errors->hasAny(['name', 'amount', 'frequency', 'classroom_id']))
                    <ul class="md:col-span-4 text-xs text-red-500 space-y-1">
                        @foreach ($errors->only(['name', 'amount', 'frequency', 'classroom_id']) as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @endif
                <button class="brand-bg text-white px-4 py-2 rounded-lg font-medium md:col-span-4">إضافة البند</button>
            </form>
        </div>

        {{-- Discounts --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
            <h2 class="font-bold">الخصومات</h2>
            <p class="text-xs text-gray-500">خصم الإخوة يُطبَّق تلقائياً من الطفل الثاني لنفس وليّ الأمر الدافع. باقي الخصومات تُربط بطفل بعينه من صفحته.</p>

            <ul class="divide-y divide-gray-50 text-sm">
                @forelse ($discounts as $discount)
                    <li class="py-3 flex items-center justify-between {{ $discount->is_active ? '' : 'opacity-60' }}">
                        <div>
                            <div class="font-medium">{{ $discount->name }}</div>
                            <div class="text-xs text-gray-500">{{ $discount->type->label() }} · {{ $discount->describe() }}</div>
                        </div>
                        <form method="POST" action="{{ route('nursery.finance.discounts.toggle', $discount) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $discount->is_active ? 0 : 1 }}">
                            <button class="text-xs {{ $discount->is_active ? 'text-red-500' : 'brand-text' }} hover:underline">{{ $discount->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                        </form>
                    </li>
                @empty
                    <li class="py-3 text-gray-400">لا توجد خصومات.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('nursery.finance.discounts.store') }}" class="pt-4 border-t border-gray-100 space-y-2 text-sm">
                @csrf
                <h3 class="font-bold">خصم جديد</h3>
                <input name="name" value="{{ old('name') }}" placeholder="اسم الخصم" class="w-full rounded-lg border-gray-200 text-sm" required>
                <select name="type" class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($discountTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <input name="value" value="{{ old('value') }}" placeholder="القيمة" inputmode="decimal" class="flex-1 rounded-lg border-gray-200 text-sm" required>
                    <select name="value_type" class="rounded-lg border-gray-200 text-sm">
                        <option value="percent">%</option>
                        <option value="fixed">ج.م</option>
                    </select>
                </div>
                @error('value')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <button class="w-full brand-bg text-white px-4 py-2 rounded-lg font-medium">إضافة الخصم</button>
            </form>
        </div>
    </div>
@endsection
