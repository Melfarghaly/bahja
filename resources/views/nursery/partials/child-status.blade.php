@php
    $map = ['active' => ['نشط', 'bg-green-100 text-green-700'], 'graduated' => ['متخرّج', 'bg-blue-100 text-blue-700'], 'withdrawn' => ['منسحب', 'bg-gray-100 text-gray-600']];
    [$label, $classes] = $map[$status] ?? [$status, 'bg-gray-100 text-gray-600'];
@endphp
<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium {{ $classes }}">{{ $label }}</span>
