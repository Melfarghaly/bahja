<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\StoreGuardianRequest;
use App\Models\Child;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Http\RedirectResponse;

class GuardianController extends Controller
{
    public function __construct(private GuardianService $guardians) {}

    public function store(StoreGuardianRequest $request, Child $child): RedirectResponse
    {
        $this->guardians->attachByInput($child, $request->validated());

        return back()->with('status', 'تمت إضافة وليّ الأمر.');
    }

    public function destroy(Child $child, User $guardian): RedirectResponse
    {
        $this->guardians->detach($child, $guardian);

        return back()->with('status', 'تم فصل وليّ الأمر.');
    }
}
