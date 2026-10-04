@extends('pay.layout')
@section('title', 'دفع مصروفات '.$tenant->name)

@section('content')
    <div class="card">
        <h1>{{ $tenant->name }}</h1>
        <div class="muted">فاتورة {{ $invoice->number }} · مصروفات شهر {{ $invoice->period_start->format('Y-m') }}</div>

        @if ($invoice->status->isCollectible())
            <div class="muted" style="margin-top:16px">المبلغ المطلوب</div>
            <div class="amount">{{ $invoice->balance()->format() }}</div>
            <div class="muted">تاريخ الاستحقاق: {{ $invoice->due_on->toDateString() }}</div>

            @foreach ($options as $option)
                <form method="POST" action="{{ $option['action'] }}">
                    @csrf
                    <button class="btn {{ $loop->first ? '' : 'secondary' }}">ادفع عبر {{ $option['gateway']->label() }}</button>
                </form>
            @endforeach

            @if ($options === [])
                <p class="note">الدفع الإلكتروني غير متاح حالياً. يمكنك الدفع لدى الحضانة مباشرة.</p>
            @endif
            @error('gateway')<p class="error">{{ $message }}</p>@enderror
            @error('invoice')<p class="error">{{ $message }}</p>@enderror
        @else
            <div class="amount">✓ {{ $invoice->status->label() }}</div>
            <p class="muted">لا يوجد مبلغ مستحق على هذه الفاتورة. شكراً لك.</p>
        @endif

        <p class="note">دفع آمن عبر بهجة. لا نحتفظ ببيانات بطاقتك.</p>
    </div>
@endsection
