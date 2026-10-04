@extends('pay.layout')
@section('title', 'كود الدفع')

@section('content')
    <div class="card">
        <h1>ادفع في أي منفذ فوري</h1>
        <div class="muted">{{ $tenant->name }} · فاتورة {{ $invoice->number }}</div>
        <div class="amount">{{ $intent->amount()->format() }}</div>
        <div class="muted">كود الدفع</div>
        <div class="code">{{ $intent->payment_code }}</div>
        <p class="muted">قدّم هذا الكود في أي منفذ فوري أو من تطبيق «myfawry» قبل {{ $intent->expires_at?->format('Y-m-d H:i') }}.</p>
        <p class="note">قد يضيف فوري رسوم خدمة صغيرة. سيصلك تأكيد فور الدفع.</p>
    </div>
@endsection
