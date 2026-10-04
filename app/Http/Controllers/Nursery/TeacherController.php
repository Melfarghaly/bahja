<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\StoreTeacherRequest;
use App\Http\Requests\Nursery\UpdateTeacherRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\TeacherService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TeacherController extends Controller
{
    public function __construct(
        private TeacherService $teachers,
        private TenantContext $tenantContext,
    ) {}

    public function index(): View
    {
        $teachers = $this->tenantContext->get()
            ->teachers()
            ->orderBy('name')
            ->paginate(20);

        return view('nursery.teachers.index', [
            'teachers' => $teachers,
            'classrooms' => Classroom::orderBy('name')->get(),
        ]);
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $this->teachers->create($request->validated());

        return redirect()->route('nursery.teachers.index')->with('status', 'تمت إضافة المعلمة.');
    }

    public function update(UpdateTeacherRequest $request, User $teacher): RedirectResponse
    {
        $this->teachers->update($teacher, $request->validated());

        return redirect()->route('nursery.teachers.index')->with('status', 'تم تحديث بيانات المعلمة.');
    }

    public function destroy(User $teacher): RedirectResponse
    {
        $this->teachers->remove($teacher);

        return redirect()->route('nursery.teachers.index')->with('status', 'تمت إزالة المعلمة من الحضانة.');
    }
}
