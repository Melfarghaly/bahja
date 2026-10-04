<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount(['tenants', 'wards'])
            ->when($request->string('q')->toString(), function ($query, string $term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->only('q'),
        ]);
    }

    public function toggleSuperAdmin(Request $request, User $user): RedirectResponse
    {
        // Guard: a super admin cannot revoke their own access (avoid lockout).
        if ($user->is($request->user())) {
            return back()->with('error', 'لا يمكنك تعديل صلاحية حسابك الخاص.');
        }

        $user->update(['is_super_admin' => ! $user->is_super_admin]);

        return back()->with('status', 'تم تحديث صلاحية المستخدم.');
    }
}
