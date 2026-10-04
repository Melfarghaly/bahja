@extends('nursery.layout')
@section('title', 'الرئيسية')

@section('header')
    @php($greeting = now()->hour < 12 ? 'صباح الخير' : 'مساء الخير')
    <div>
        <h1 class="text-2xl font-extrabold">{{ $greeting }}، {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $tenant->name }} · {{ now()->translatedFormat('l j F') }}</p>
    </div>
@endsection

@section('content')
    {{-- KPI row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        {{-- Accent card --}}
        <div class="rounded-3xl p-6 text-white flex flex-col justify-between" style="background:linear-gradient(135deg, var(--coral), color-mix(in srgb, var(--coral) 80%, #000 20%))">
            <div class="text-sm opacity-90">حاضرون اليوم</div>
            <div class="text-4xl font-extrabold mt-2">{{ $presentToday }}</div>
            <div class="text-xs opacity-90 mt-1">{{ $absentToday }} غائب</div>
        </div>

        @foreach ([
            ['المعلّمات', $teachersCount, 'عبر '.$classroomsCount.' فصول'],
            ['الأطفال', $childrenCount, 'مُسجّل نشط'],
            ['نسبة الحضور', $attendanceRate.'%', 'اليوم'],
        ] as $card)
            <div class="card p-6 flex flex-col justify-between">
                <div class="text-sm text-gray-400">{{ $card[0] }}</div>
                <div class="text-4xl font-extrabold brand-text mt-2">{{ $card[1] }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $card[2] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Weekly attendance chart --}}
        <div class="card p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-bold text-lg">الحضور خلال الأسبوع</h2>
                <span class="text-xs text-gray-400">آخر 7 أيام</span>
            </div>
            @php($maxDay = max(1, collect($weeklyAttendance)->max('count')))
            <div class="flex items-end justify-between gap-3 h-48">
                @foreach ($weeklyAttendance as $day)
                    <div class="flex-1 flex flex-col items-center justify-end h-full">
                        <div class="w-full rounded-t-xl transition-all"
                             style="height: {{ max(6, ($day['count'] / $maxDay) * 100) }}%; background: {{ $day['is_today'] ? 'linear-gradient(180deg, var(--coral), color-mix(in srgb, var(--coral) 80%, #000))' : 'linear-gradient(180deg, color-mix(in srgb, var(--teal) 55%, #fff 45%), color-mix(in srgb, var(--teal) 70%, #fff 30%))' }};">
                        </div>
                        <div class="mt-2 text-xs {{ $day['is_today'] ? 'font-bold brand-text' : 'text-gray-400' }}">
                            {{ $day['is_today'] ? 'اليوم' : $day['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Subscription usage + recent --}}
        <div class="card p-6 space-y-4">
            <h2 class="font-bold">الاشتراك</h2>
            @if ($subscription)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">الخطة</span>
                    <span class="font-bold brand-text">{{ $subscription->plan?->name }}</span>
                </div>
                <div>
                    <div class="flex justify-between text-xs mb-1"><span>استهلاك الأطفال</span><span>{{ $planUsage['children'] }} / {{ $planUsage['max_children'] ?? '∞' }}</span></div>
                    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $planUsage['max_children'] ? min(100, ($planUsage['children'] / max($planUsage['max_children'],1)) * 100) : 8 }}%; background:var(--coral)"></div>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400">لا يوجد اشتراك نشط.</p>
            @endif

            <div class="pt-4 border-t border-gray-50">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm">أحدث الأطفال</h3>
                    <a href="{{ route('nursery.children.create') }}" class="brand-bg px-3 py-1.5 rounded-lg text-xs font-medium">+ تسجيل</a>
                </div>
                <ul class="space-y-2">
                    @forelse ($recentChildren as $child)
                        <li class="flex items-center justify-between text-sm">
                            <a href="{{ route('nursery.children.show', $child) }}" class="hover:underline">{{ $child->first_name }} {{ $child->last_name }}</a>
                            <span class="text-xs text-gray-400">{{ $child->classroom?->name ?? '—' }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">لا يوجد أطفال بعد.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
