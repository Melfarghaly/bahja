<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إيصال {{ $payment->receipt_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; --brand: #0d9488; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: var(--ink); font-family: 'Cairo', system-ui, sans-serif; }
        .sheet { position: relative; max-width: 720px; margin: 24px auto; background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--brand); padding-bottom: 16px; }
        h1 { margin: 0; font-size: 22px; }
        .muted { color: var(--muted); font-size: 13px; }
        .number { font-family: ui-monospace, monospace; font-size: 15px; font-weight: 700; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; margin: 24px 0; }
        dt { color: var(--muted); font-size: 12px; } dd { margin: 0; font-weight: 700; }
        .amount { text-align: center; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 12px; padding: 20px; margin: 24px 0; }
        .amount .value { font-size: 32px; font-weight: 800; color: var(--brand); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 8px 0; border-bottom: 1px solid var(--line); } td:last-child { text-align: left; }
        footer { margin-top: 32px; display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); }
        .void { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .void span { transform: rotate(-25deg); font-size: 96px; font-weight: 800; color: rgba(220, 38, 38, .18); border: 8px solid rgba(220, 38, 38, .18); padding: 0 32px; border-radius: 16px; }
        .actions { max-width: 720px; margin: 0 auto; text-align: left; }
        .actions button { background: var(--brand); color: #fff; border: 0; padding: 10px 24px; border-radius: 8px; font-family: inherit; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } .actions { display: none; } }
        @media (max-width: 640px) { .sheet { padding: 20px; margin: 0; border-radius: 0; } dl { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">طباعة / حفظ PDF</button></div>

    <main class="sheet">
        @if ($payment->isVoid())
            <div class="void"><span>ملغي</span></div>
        @endif

        <header>
            <div>
                <h1>{{ $tenant->name }}</h1>
                <div class="muted">{{ $tenant->address }} {{ $tenant->phone ? '· '.$tenant->phone : '' }}</div>
            </div>
            <div style="text-align:left">
                <div class="muted">إيصال استلام نقدية</div>
                <div class="number">{{ $payment->receipt_number }}</div>
            </div>
        </header>

        <dl>
            <div><dt>استلمنا من</dt><dd>{{ $payment->invoice->payer->name }}</dd></div>
            <div><dt>التاريخ</dt><dd>{{ $payment->paid_at->format('Y-m-d H:i') }}</dd></div>
            <div><dt>عن الفاتورة</dt><dd>{{ $payment->invoice->number }} — شهر {{ $payment->invoice->period_start->format('Y-m') }}</dd></div>
            <div><dt>طريقة الدفع</dt><dd>{{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</dd></div>
        </dl>

        <div class="amount">
            <div class="muted">المبلغ المستلم</div>
            <div class="value">{{ $payment->amount()->format() }}</div>
        </div>

        <table>
            @foreach ($payment->invoice->items as $item)
                <tr><td>{{ $item->description }}</td><td>{{ $item->amount()->format() }}</td></tr>
            @endforeach
            <tr><td><strong>إجمالي الفاتورة</strong></td><td><strong>{{ $payment->invoice->total()->format() }}</strong></td></tr>
            <tr><td>المتبقي على الفاتورة حالياً</td><td>{{ $payment->invoice->balance()->format() }}</td></tr>
        </table>

        @if ($payment->isVoid())
            <p class="muted">أُلغي هذا الإيصال في {{ $payment->voided_at->format('Y-m-d') }}: {{ $payment->void_reason }}</p>
        @endif

        <footer>
            <span>المستلم: {{ $payment->receivedBy?->name ?? '—' }}</span>
            <span>صادر عبر بهجة</span>
        </footer>
    </main>
</body>
</html>
