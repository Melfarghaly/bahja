<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\StoreChildRequest;
use App\Http\Requests\Nursery\UpdateChildRequest;
use App\Models\Child;
use App\Models\Classroom;
use App\Services\ChildService;
use App\Services\Exceptions\PlanLimitException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    public function __construct(private ChildService $children) {}

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
            'child' => new Child(),
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

    public function show(Child $child): View
    {
        $child->load(['classroom', 'guardians', 'attendances' => fn ($q) => $q->latest('date')->limit(10)]);

        return view('nursery.children.show', ['child' => $child]);
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
