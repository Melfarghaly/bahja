{{-- Entitlements: effective features/limits + overrides + add-ons. --}}
<div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="font-bold">الاستحقاقات الفعلية</h3>
        <span class="text-xs text-gray-400">الخطة الأساس: {{ $entitlements->planName }}</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="text-sm font-medium text-gray-500 mb-2">المزايا</h4>
            <ul class="text-sm space-y-1">
                @foreach (\App\Enums\Feature::cases() as $feature)
                    <li class="flex justify-between">
                        <span>{{ $feature->label() }}</span>
                        @if ($entitlements->allows($feature))
                            <span class="text-emerald-600">✓</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-medium text-gray-500 mb-2">الحدود</h4>
            <ul class="text-sm space-y-1">
                @foreach (\App\Enums\Limit::cases() as $limit)
                    <li class="flex justify-between">
                        <span>{{ $limit->label() }}</span>
                        <span>
                            @isset($usage[$limit->value]){{ $usage[$limit->value] }} / @endisset{{ $entitlements->limit($limit) ?? 'غير محدود' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="pt-4 border-t border-gray-100 space-y-3">
        <h4 class="font-medium">الاستثناءات (Overrides)</h4>
        <table class="w-full text-sm">
            <thead class="text-gray-400 text-right"><tr><th class="pb-2 font-medium">البند</th><th class="pb-2 font-medium">القيمة</th><th class="pb-2 font-medium">السبب</th><th class="pb-2 font-medium">ينتهي</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($tenant->entitlementOverrides as $override)
                    <tr>
                        <td class="py-2">{{ (\App\Enums\Feature::tryFrom($override->key) ?? \App\Enums\Limit::tryFrom($override->key))?->label() ?? $override->key }}</td>
                        <td class="py-2">
                            @if (is_bool($override->value))
                                {{ $override->value ? 'مفعّل' : 'معطّل' }}
                            @else
                                {{ $override->value ?? 'غير محدود' }}
                            @endif
                        </td>
                        <td class="py-2 text-gray-500">{{ $override->reason }} <span class="text-xs">({{ $override->grantedBy?->name ?? '—' }})</span></td>
                        <td class="py-2 text-gray-500">{{ $override->expires_at?->toDateString() ?? 'دائم' }}</td>
                        <td class="py-2 text-left">
                            <form method="POST" action="{{ route('admin.nurseries.overrides.destroy', [$tenant, $override]) }}" onsubmit="return confirm('إلغاء هذا الاستثناء؟')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 text-xs hover:underline">إلغاء</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-center text-gray-400">لا توجد استثناءات.</td></tr>
                @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.nurseries.overrides.store', $tenant) }}" class="grid grid-cols-1 md:grid-cols-5 gap-2 text-sm">
            @csrf
            <select name="key" class="rounded-lg border-gray-200 text-sm" required>
                <optgroup label="المزايا">
                    @foreach (\App\Enums\Feature::cases() as $feature)
                        <option value="{{ $feature->value }}">{{ $feature->label() }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="الحدود">
                    @foreach (\App\Enums\Limit::cases() as $limit)
                        <option value="{{ $limit->value }}">{{ $limit->label() }}</option>
                    @endforeach
                </optgroup>
            </select>
            <input name="value" placeholder="on / off أو رقم (فارغ = غير محدود)" class="rounded-lg border-gray-200 text-sm">
            <input name="reason" placeholder="السبب (مثلاً: الخمسون المؤسِّسون)" class="rounded-lg border-gray-200 text-sm" required>
            <input type="date" name="expires_at" class="rounded-lg border-gray-200 text-sm">
            <button class="brand-bg text-white px-4 py-2 rounded-lg font-medium">حفظ الاستثناء</button>
        </form>
    </div>

    <div class="pt-4 border-t border-gray-100 space-y-3">
        <h4 class="font-medium">الإضافات المدفوعة</h4>
        <ul class="text-sm divide-y divide-gray-50">
            @forelse ($tenant->addons as $addon)
                <li class="py-2 flex items-center justify-between">
                    <span>{{ $addon->addon->label() }} × {{ $addon->quantity }}
                        <span class="text-xs text-gray-400">حتى {{ $addon->ends_at?->toDateString() ?? 'إلغاء يدوي' }}</span></span>
                    <form method="POST" action="{{ route('admin.nurseries.addons.destroy', [$tenant, $addon]) }}" onsubmit="return confirm('حذف هذه الإضافة؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 text-xs hover:underline">حذف</button>
                    </form>
                </li>
            @empty
                <li class="py-2 text-gray-400">لا توجد إضافات.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('admin.nurseries.addons.store', $tenant) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 text-sm">
            @csrf
            <select name="addon" class="rounded-lg border-gray-200 text-sm" required>
                @foreach (\App\Enums\Addon::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            <input type="number" name="quantity" value="1" min="1" class="rounded-lg border-gray-200 text-sm" required>
            <input type="date" name="ends_at" class="rounded-lg border-gray-200 text-sm">
            <button class="brand-bg text-white px-4 py-2 rounded-lg font-medium">إضافة</button>
        </form>
    </div>
</div>
