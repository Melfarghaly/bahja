<?php

namespace App\Http\Requests\Nursery\Finance;

use App\Enums\TuitionPaymentMethod;
use App\Rules\PoundAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $manual = array_map(fn (TuitionPaymentMethod $m) => $m->value, TuitionPaymentMethod::manual());

        return [
            'amount' => ['required', new PoundAmount],
            'method' => ['required', Rule::in($manual)],
            // Anything but cash must be traceable (transfer / InstaPay / wallet reference).
            'reference' => ['nullable', 'required_unless:method,'.TuitionPaymentMethod::Cash->value, 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'method' => 'طريقة الدفع', 'reference' => 'رقم المرجع', 'paid_at' => 'تاريخ الدفع'];
    }
}
