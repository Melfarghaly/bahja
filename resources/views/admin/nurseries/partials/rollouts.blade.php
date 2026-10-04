{{-- Gradual release: per-nursery state of each V2 module. --}}
<div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-3">
    <h3 class="font-bold">الإطلاق التدريجي لوحدات V2</h3>
    <ul class="text-sm divide-y divide-gray-50">
        @foreach ($rollouts as $flagValue => $active)
            @php($flag = \App\Enums\RolloutFlag::from($flagValue))
            <li class="py-2 flex items-center justify-between">
                <span>{{ $flag->label() }}</span>
                <form method="POST" action="{{ route('admin.nurseries.rollouts.update', [$tenant, $flag]) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="active" value="{{ $active ? 0 : 1 }}">
                    <button class="text-xs px-3 py-1 rounded-full {{ $active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $active ? 'مفعّلة — إيقاف' : 'متوقفة — تفعيل' }}
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
