<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\StoreClassroomRequest;
use App\Http\Requests\Nursery\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Services\ClassroomService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ClassroomController extends Controller
{
    public function __construct(private ClassroomService $classrooms) {}

    public function index(): View
    {
        return view('nursery.classrooms.index', [
            'classrooms' => Classroom::withCount('children')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreClassroomRequest $request): RedirectResponse
    {
        $this->classrooms->create($request->validated());

        return back()->with('status', 'تمت إضافة الفصل.');
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $this->classrooms->update($classroom, $request->validated());

        return back()->with('status', 'تم تحديث الفصل.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $this->classrooms->delete($classroom);

        return back()->with('status', 'تم حذف الفصل.');
    }
}
