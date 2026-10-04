@extends('admin.layout')
@section('title', 'المستخدمون')

@section('content')
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">بحث</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="الاسم أو الهاتف أو البريد"
                   class="w-full rounded-lg border-gray-200 text-sm">
        </div>
        <button class="brand-bg text-white px-5 py-2 rounded-lg text-sm font-medium">بحث</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-right">
                <tr>
                    <th class="px-4 py-3 font-medium">الاسم</th>
                    <th class="px-4 py-3 font-medium">الهاتف</th>
                    <th class="px-4 py-3 font-medium">الحضانات</th>
                    <th class="px-4 py-3 font-medium">الأطفال</th>
                    <th class="px-4 py-3 font-medium">سوبر أدمن</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $user->email ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $user->phone ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $user->tenants_count }}</td>
                        <td class="px-4 py-3">{{ $user->wards_count }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is_super_admin)
                                <span class="text-xs px-2 py-0.5 rounded-full brand-accent-bg text-white">نعم</span>
                            @else
                                <span class="text-xs text-gray-400">لا</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.toggle-super-admin', $user) }}"
                                      onsubmit="return confirm('تغيير صلاحية السوبر-أدمن لهذا المستخدم؟')">
                                    @csrf @method('PATCH')
                                    <button class="text-xs font-medium brand-text hover:underline">
                                        {{ $user->is_super_admin ? 'إلغاء الصلاحية' : 'منح الصلاحية' }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">لا يوجد مستخدمون.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>
@endsection
