@php($classes = match ($status) {
    \App\Enums\TuitionInvoiceStatus::Paid => 'bg-green-100 text-green-700',
    \App\Enums\TuitionInvoiceStatus::PartiallyPaid => 'bg-amber-100 text-amber-700',
    \App\Enums\TuitionInvoiceStatus::Void => 'bg-gray-100 text-gray-500 line-through',
    default => 'bg-blue-100 text-blue-700',
})
<span class="text-xs px-2 py-0.5 rounded-full {{ $classes }}">{{ $status->label() }}</span>
