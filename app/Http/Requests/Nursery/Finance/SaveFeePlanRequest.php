<?php

namespace App\Http\Requests\Nursery\Finance;

use App\Enums\FeeFrequency;
use App\Rules\PoundAmount;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SaveFeePlanRequest extends FormRequest
{
    /**
     * Access is restricted to nursery admins by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', new PoundAmount],
            'frequency' => ['required', new Enum(FeeFrequency::class)],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'اسم الرسوم', 'amount' => 'المبلغ', 'frequency' => 'التكرار'];
    }
}
