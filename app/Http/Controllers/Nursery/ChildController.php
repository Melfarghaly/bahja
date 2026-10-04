<?php

namespace App\Http\Controllers\Nursery;

use App\Enums\DiscountType;
use App\Enums\RolloutFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\StoreChildRequest;
use App\Http\Requests\Nursery\UpdateChildRequest;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Services\ChildService;
use App\Services\Exceptions\PlanLimitException;
use App\Services\RolloutService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    public function __construct(
        private ChildService $children,
        private RolloutService $rollouts,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $children = Child::query()
            ->with('classroom:id,name')
            ->withCount('guardians')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(function ($w) use ($term) {
                $w->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%");
            }))
            ->when($request->string('classroom')->toString(), fn ($q, $c) => $q->where('classroom_id', $c))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('nursery.children.index', [
            'children' => $children,
            'classrooms' => Classroom::orderBy('name')->get(),
            'filters' => $request->only(['q', 'classroom']),
        ]);
    }

    public function create(): View
    {
        return view('nursery.children.form', [
            'child' => new Child,
            'classrooms' => Classroom::orderBy('name')->get(),
        ]);
    }

    public function store(StoreChildRequest $request): RedirectResponse
    {
        try {
            $child = $this->children->create($request->validated());
        } catch (PlanLimitException $e) {
            return back()->withInput()->with('error', 'تم بلوغ الحد الأقصى للأطفال في خطتك الحالية. يرجى الترقية.');
        }

        return redirect()->route('nursery.children.show', $child)->with('status', 'تم تسجيل الطفل.');
    }

    public function show(Request $request, Child $child): View
    {
        $tenant = $this->tenantContext->get();
        $managesFees = $request->user()->manages($tenant)
            && $this->rollouts->active($tenant, RolloutFlag::BahgaPay);

        $child->load([
            'classroom',
            'guardians',
            'attendances' => fn ($q) => $q->latest('date')->limit(10),
            'feePlans' => fn ($q) => $q->with(['feePlan:id,name,amount_piasters,frequency', 'discount:id,name,value_type,value'])->latest('starts_on'),
        ]);

        return view('nursery.children.show', [
            'child' => $child,
            'managesFees' => $managesFees,
            'feePlanOptions' => $managesFees ? FeePlan::where('is_active', true)->orderBy('name')->get(['id', 'name', 'amount_piasters']) : collect(),
            'discountOptions' => $managesFees
                ? FeeDiscount::where('is_active', true)->where('type', '!=', DiscountType::Sibling)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function edit(Child $child): View
    {
        return view('nursery.children.form', [
            'child' => $child,
            'classrooms' => Classroom::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateChildRequest $request, Child $child): RedirectResponse
    {
        $this->children->update($child, $request->validated());

        return redirect()->route('nursery.children.show', $child)->with('status', 'تم تحديث بيانات الطفل.');
    }
}
