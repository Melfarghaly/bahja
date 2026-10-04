@php
    $map = [
        'active' => ['نشطة', 'bg-green-100 text-green-700'],
        'trial' => ['تجريبية', 'bg-blue-100 text-blue-700'],
        'suspended' => ['معلّقة', 'bg-amber-100 text-amber-700'],
        'cancelled' => ['ملغاة', 'bg-red-100 text-red-700'],
    ];
    [$label, $classes] = $map[$status] ?? [$status, 'bg-gray-100 text-gray-600'];
@endphp
<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium {{ $classes }}">{{ $label }}</span>
