{{-- Payments on the invoice: record (cashier), list with receipts, void. --}}
@if ($invoice->status->isCollectible())
    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <h3 class="font-bold mb-3">تسجيل دفعة</h3>
        <form method="POST" action="{{ route('nursery.finance.payments.store', $invoice) }}" class="space-y-2 text-sm">
            @csrf
            <div class="flex gap-2">
                <input name="amount" value="{{ old('amount', $invoice->balance()->toPounds()) }}" inputmode="decimal" class="flex-1 rounded-lg border-gray-200 text-sm" required>
                <span class="self-center text-gray-400">ج.م</span>
            </div>
            <select name="method" class="w-full rounded-lg border-gray-200 text-sm">
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </select>
            <input name="reference" value="{{ old('reference') }}" placeholder="رقم المرجع (إلزامي لغير النقدي)" class="w-full rounded-lg border-gray-200 text-sm">
            <input type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-gray-200 text-sm">
            <input name="notes" value="{{ old('notes') }}" placeholder="ملاحظات (اختياري)" class="w-full rounded-lg border-gray-200 text-sm">
            @if ($errors->hasAny(['amount', 'method', 'reference', 'paid_at']))
                <ul class="text-xs text-red-500 space-y-1">
                    @foreach ($errors->only(['amount', 'method', 'reference', 'paid_at']) as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
            <button class="w-full brand-bg text-white px-4 py-2 rounded-lg font-medium">تسجيل وإصدار إيصال</button>
        </form>
    </div>
@endif

<div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
    <h3 class="font-bold mb-3">الإيصالات</h3>
    <ul class="divide-y divide-gray-50 text-sm">
        @forelse ($invoice->payments as $payment)
            <li class="py-3 space-y-1 {{ $payment->isVoid() ? 'text-gray-400' : '' }}">
                <div class="flex items-center justify-between">
                    <a href="{{ route('nursery.finance.payments.receipt', $payment) }}" target="_blank" class="font-mono brand-text hover:underline">{{ $payment->receipt_number }}</a>
                    <span class="font-bold {{ $payment->isVoid() ? 'line-through' : '' }}">{{ $payment->amount()->format() }}</span>
                </div>
                <div class="text-xs text-gray-500">
                    {{ $payment->method->label() }} · {{ $payment->paid_at->format('Y-m-d H:i') }} · {{ $payment->receivedBy?->name }}
                    @if ($payment->reference) · مرجع {{ $payment->reference }} @endif
                </div>
                @if ($payment->isVoid())
                    <div class="text-xs">ملغي: {{ $payment->void_reason }}</div>
                @else
                    <details class="text-xs">
                        <summary class="text-red-500 cursor-pointer">إلغاء الإيصال</summary>
                        <form method="POST" action="{{ route('nursery.finance.payments.void', $payment) }}" class="mt-2 flex gap-2">
                            @csrf
                            <input name="reason" placeholder="سبب الإلغاء" class="flex-1 rounded-lg border-gray-200 text-xs" required>
                            <button class="bg-red-500 text-white px-3 rounded-lg">تأكيد</button>
                        </form>
                    </details>
                @endif
            </li>
        @empty
            <li class="py-3 text-gray-400">لا توجد دفعات.</li>
        @endforelse
    </ul>
</div>

@if ($invoice->status !== \App\Enums\TuitionInvoiceStatus::Void && $invoice->paid_piasters === 0)
    <details class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm text-sm">
        <summary class="text-red-500 cursor-pointer font-medium">إلغاء الفاتورة</summary>
        <p class="text-xs text-gray-500 mt-2">للفواتير الصادرة بالخطأ. بعد الإلغاء يمكن إصدار فاتورة جديدة لنفس الشهر.</p>
        <form method="POST" action="{{ route('nursery.finance.invoices.void', $invoice) }}" class="mt-2 flex gap-2">
            @csrf
            <input name="reason" placeholder="سبب الإلغاء" class="flex-1 rounded-lg border-gray-200 text-sm" required>
            <button class="bg-red-500 text-white px-3 rounded-lg">إلغاء</button>
        </form>
        @error('reason')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </details>
@endif

@if ($invoice->paymentIntents->isNotEmpty() || $invoice->dunningNotices->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm text-sm space-y-4">
        @if ($invoice->paymentIntents->isNotEmpty())
            <div>
                <h3 class="font-bold mb-2">محاولات الدفع الإلكتروني</h3>
                <ul class="divide-y divide-gray-50">
                    @foreach ($invoice->paymentIntents as $intent)
                        <li class="py-2 flex justify-between gap-2">
                            <span>{{ $intent->gateway->label() }} · {{ $intent->amount()->format() }}</span>
                            <span class="text-xs {{ $intent->status === \App\Enums\PaymentIntentStatus::NeedsReview ? 'text-amber-700 font-medium' : 'text-gray-500' }}">
                                {{ $intent->status->label() }}{{ $intent->failure_reason ? ' — '.$intent->failure_reason : '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($invoice->dunningNotices->isNotEmpty())
            <div>
                <h3 class="font-bold mb-2">التذكيرات</h3>
                <ul class="divide-y divide-gray-50">
                    @foreach ($invoice->dunningNotices as $notice)
                        <li class="py-2 flex justify-between gap-2">
                            <span>{{ $notice->step->label() }}</span>
                            <span class="text-xs text-gray-500">
                                {{ match ($notice->status) { 'sent' => 'أُرسل', 'skipped' => 'لم يُرسل ('.$notice->skip_reason.')', 'failed' => 'فشل الإرسال', default => $notice->status } }}
                                · {{ $notice->created_at->format('Y-m-d') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
