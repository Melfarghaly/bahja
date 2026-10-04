@php($branding = \App\Models\PlatformSetting::current())
@php($tenant = app(\App\Support\TenantContext::class)->get())
@php($isAdmin = auth()->user()->manages($tenant))
@php($currentPlan = $tenant->activeSubscription()->with('plan')->first()?->plan)
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'إدارة الحضانة') — {{ $tenant->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --coral: {{ $branding->primary_color }};
            --teal: {{ $branding->secondary_color }};
            --accent: {{ $branding->accent_color }};
            --brand-primary: var(--coral);
            --bahja-bg: #f4f2ed;
        }
        body { font-family: 'Tajawal', 'Segoe UI', system-ui, sans-serif; background: var(--bahja-bg); }
        .brand-bg { background-color: var(--coral); color: #fff; }
        .brand-text { color: var(--teal); }
        .brand-accent-bg { background-color: var(--accent); }
        .sidebar { background: linear-gradient(180deg, color-mix(in srgb, var(--teal) 92%, #fff 8%), color-mix(in srgb, var(--teal) 78%, #000 22%)); }
        .nav-link { display:flex; align-items:center; gap:.75rem; padding:.7rem .9rem; border-radius:.9rem; color:rgba(255,255,255,.72); font-size:.95rem; transition:.15s; }
        .nav-link:hover { background:rgba(255,255,255,.08); color:#fff; }
        .nav-link.is-active { background:rgba(255,255,255,.13); color:#fff; font-weight:700; }
        .nav-ic { width:1.05rem; height:1.05rem; border-radius:9999px; border:2px solid currentColor; opacity:.7; flex-shrink:0; }
        .nav-link.is-active .nav-ic { background:var(--coral); border-color:var(--coral); opacity:1; }
        .card { background:#fff; border:1px solid #f0eee9; border-radius:1.25rem; box-shadow:0 1px 2px rgba(16,24,40,.04), 0 8px 24px -18px rgba(16,24,40,.25); }
    </style>
</head>
<body class="h-full text-gray-800">
<div class="min-h-full flex">
    {{-- Sidebar (right, RTL) --}}
    <aside class="sidebar w-64 shrink-0 flex flex-col text-white">
        <div class="h-20 flex items-center gap-3 px-6">
            <span class="relative inline-grid place-items-center w-10 h-10 rounded-full" style="background:rgba(255,255,255,.12)">
                <span class="w-4 h-4 rounded-full" style="background:var(--coral)"></span>
            </span>
            <div class="leading-tight">
                <div class="font-extrabold text-lg">بهجة</div>
                <div class="text-[10px] tracking-widest" style="color:var(--accent)">{{ $isAdmin ? 'ADMIN' : 'TEACHER' }}</div>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1">
            @php($is = fn ($p) => request()->routeIs($p) ? 'is-active' : '')
            <a href="{{ route('nursery.dashboard') }}" class="nav-link {{ $is('nursery.dashboard') }}"><span class="nav-ic"></span><span>الرئيسية</span></a>
            <a href="{{ route('nursery.children.index') }}" class="nav-link {{ $is('nursery.children.*') }}"><span class="nav-ic"></span><span>الأطفال</span></a>
            <a href="{{ route('nursery.attendance.index') }}" class="nav-link {{ $is('nursery.attendance.*') }}"><span class="nav-ic"></span><span>الحضور والانصراف</span></a>
            @if ($isAdmin)
                <a href="{{ route('nursery.teachers.index') }}" class="nav-link {{ $is('nursery.teachers.*') }}"><span class="nav-ic"></span><span>المعلّمات</span></a>
                <a href="{{ route('nursery.classrooms.index') }}" class="nav-link {{ $is('nursery.classrooms.*') }}"><span class="nav-ic"></span><span>الفصول</span></a>
                @rolledout('bahga-pay')
                @entitled('finance_ledger')
                    <a href="{{ route('nursery.finance.dashboard') }}" class="nav-link {{ $is('nursery.finance.dashboard') }}"><span class="nav-ic"></span><span>بهجة باي: التحصيل</span></a>
                    <a href="{{ route('nursery.finance.invoices.index') }}" class="nav-link {{ $is('nursery.finance.invoices.*') }}"><span class="nav-ic"></span><span>فواتير المصروفات</span></a>
                    <a href="{{ route('nursery.finance.setup') }}" class="nav-link {{ $is('nursery.finance.setup') }}"><span class="nav-ic"></span><span>إعداد الرسوم</span></a>
                @endentitled
                @endrolledout
                <a href="{{ route('nursery.subscription.show') }}" class="nav-link {{ $is('nursery.subscription.*') }}"><span class="nav-ic"></span><span>اشتراك بهجة</span></a>
                <a href="{{ route('nursery.settings.edit') }}" class="nav-link {{ $is('nursery.settings.*') }}"><span class="nav-ic"></span><span>الإعدادات</span></a>
            @endif
        </nav>

        <div class="px-4 pb-4">
            <div class="rounded-2xl px-4 py-3" style="background:rgba(255,255,255,.08)">
                <div class="text-[11px]" style="color:rgba(255,255,255,.6)">باقة الحضانة</div>
                <div class="flex items-center justify-between mt-0.5">
                    <span class="font-bold">{{ $currentPlan?->name ?? 'مجاني' }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background:var(--accent);color:#04302c">نشطة</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf
                <button class="w-full text-right px-4 py-2 rounded-xl text-sm" style="color:rgba(255,255,255,.7)">تسجيل الخروج</button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-20 flex items-center justify-between px-8">
            <div>
                @if (View::hasSection('header'))
                    @yield('header')
                @else
                    <h1 class="text-2xl font-extrabold">@yield('title', 'إدارة الحضانة')</h1>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('nursery.children.index') }}" class="hidden sm:block">
                    <div class="flex items-center gap-2 bg-white rounded-full px-4 py-2 border border-gray-100 shadow-sm">
                        <span class="w-4 h-4 rounded-full border-2 border-gray-300"></span>
                        <input name="q" placeholder="بحث..." class="border-0 p-0 text-sm focus:ring-0 w-36 bg-transparent">
                    </div>
                </form>
                <span class="w-11 h-11 rounded-full grid place-items-center text-white font-bold" style="background:var(--coral)">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </span>
            </div>
        </header>

        <main class="flex-1 px-8 pb-8 space-y-6">
            @if (session('status'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc pr-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
