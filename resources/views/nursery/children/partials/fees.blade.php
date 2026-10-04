{{-- Bahga Pay: the child's fee enrollments. --}}
<div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="font-bold">الرسوم المسجّلة</h3>
        <a href="{{ route('nursery.finance.setup') }}" class="text-xs brand-text hover:underline">إعداد بنود الرسوم</a>
    </div>

    <table class="w-full text-sm">
        <thead class="text-gray-400 text-right">
            <tr><th class="pb-2 font-medium">البند</th><th class="pb-2 font-medium">المبلغ</th><th class="pb-2 font-medium">خصم خاص</th><th class="pb-2 font-medium">الفترة</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($child->feePlans as $assignment)
                @php($ended = $assignment->ends_on !== null && $assignment->ends_on->isPast())
                <tr class="{{ $ended ? 'text-gray-400' : '' }}">
                    <td class="py-2">{{ $assignment->feePlan->name }} <span class="text-xs text-gray-400">({{ $assignment->feePlan->frequency->label() }})</span></td>
                    <td class="py-2">{{ $assignment->feePlan->amount()->format() }}</td>
                    <td class="py-2">{{ $assignment->discount ? $assignment->discount->name.' — '.$assignment->discount->describe() : '—' }}</td>
                    <td class="py-2 text-gray-500">من {{ $assignment->starts_on->format('Y-m') }} {{ $assignment->ends_on ? 'حتى '.$assignment->ends_on->format('Y-m') : '' }}</td>
                    <td class="py-2 text-left">
                        @if ($assignment->ends_on === null)
                            <form method="POST" action="{{ route('nursery.finance.child-fees.end', $assignment) }}" class="flex gap-1 justify-end">
                                @csrf @method('PATCH')
                                <input type="month" name="ends_on" value="{{ now()->format('Y-m') }}" class="rounded-lg border-gray-200 text-xs">
                                <button class="text-xs text-red-500 hover:underline">إنهاء</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-3 text-center text-gray-400">الطفل غير مسجّل في أي رسوم.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($feePlanOptions->isNotEmpty())
        <form method="POST" action="{{ route('nursery.finance.child-fees.store', $child) }}" class="pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-2 text-sm">
            @csrf
            <select name="fee_plan_id" class="rounded-lg border-gray-200 text-sm" required>
                @foreach ($feePlanOptions as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $plan->amount()->format() }}</option>
                @endforeach
            </select>
            <select name="fee_discount_id" class="rounded-lg border-gray-200 text-sm">
                <option value="">بدون خصم خاص</option>
                @foreach ($discountOptions as $discount)
                    <option value="{{ $discount->id }}">{{ $discount->name }} — {{ $discount->describe() }}</option>
                @endforeach
            </select>
            <input type="month" name="starts_on" value="{{ now()->format('Y-m') }}" class="rounded-lg border-gray-200 text-sm" required>
            <button class="brand-bg text-white px-4 py-2 rounded-lg font-medium">تسجيل في الرسوم</button>
            @error('fee_plan_id')<p class="md:col-span-4 text-xs text-red-500">{{ $message }}</p>@enderror
        </form>
    @else
        <p class="text-xs text-gray-400">أضف بنود الرسوم أولاً من صفحة الإعداد.</p>
    @endif
</div>
