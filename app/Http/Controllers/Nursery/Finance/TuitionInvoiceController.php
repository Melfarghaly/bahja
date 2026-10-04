<?php

namespace App\Http\Controllers\Nursery\Finance;

use App\Enums\TuitionInvoiceStatus;
use App\Enums\TuitionPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\Finance\GenerateInvoicesRequest;
use App\Models\TuitionInvoice;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TuitionInvoiceController extends Controller
{
    public function __construct(
        private TuitionBillingService $billing,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $status = TuitionInvoiceStatus::tryFrom($request->string('status')->toString());
        $period = $request->string('period')->toString();
        $overdue = $request->boolean('overdue');

        $invoices = TuitionInvoice::query()
            ->with('payer:id,name,phone')
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($overdue, fn ($q) => $q->collectible()->whereDate('due_on', '<', today()))
            ->when(preg_match('/^\d{4}-\d{2}$/', $period), fn ($q) => $q->whereDate('period_start', $period.'-01'))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('payer', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->latest('period_start')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('nursery.finance.invoices.index', [
            'invoices' => $invoices,
            'statuses' => TuitionInvoiceStatus::cases(),
            'filters' => $request->only(['status', 'period', 'q', 'overdue']),
        ]);
    }

    public function show(TuitionInvoice $invoice): View
    {
        $invoice->load([
            'payer:id,name,phone,email',
            'items.child:id,first_name,last_name',
            'payments' => fn ($q) => $q->with('receivedBy:id,name')->latest('paid_at'),
        ]);

        return view('nursery.finance.invoices.show', [
            'invoice' => $invoice,
            'methods' => TuitionPaymentMethod::manual(),
        ]);
    }

    public function generate(GenerateInvoicesRequest $request): RedirectResponse
    {
        $result = $this->billing->generate(
            $this->tenantContext->get(),
            CarbonImmutable::createFromFormat('!Y-m', $request->validated('period')),
            $request->user(),
        );

        $message = "تم إصدار {$result->created} فاتورة بإجمالي {$result->totalBilled()->format()}";
        if ($result->alreadyInvoiced > 0) {
            $message .= "، وتخطّي {$result->alreadyInvoiced} أسرة صدرت فاتورتها من قبل";
        }

        $redirect = redirect()->route('nursery.finance.invoices.index', ['period' => $request->validated('period')])
            ->with('status', $message.'.');

        $problems = [];
        if ($result->unbillableChildren !== []) {
            $problems[] = 'أطفال لديهم رسوم مستحقة بدون وليّ أمر دافع: '.implode('، ', $result->unbillableChildren).'. حدّد وليّ الأمر الدافع من صفحة الطفل.';
        }
        if ($result->shareWarnings !== []) {
            $problems[] = 'نِسب الدفع لا تساوي 100% فقُسِّمت المصروفات بالتساوي لـ: '.implode('، ', $result->shareWarnings).'.';
        }

        return $problems === [] ? $redirect : $redirect->with('error', implode(' ', $problems));
    }
}
