<?php

namespace App\Http\Requests\Nursery\Finance;

use Illuminate\Foundation\Http\FormRequest;

class EndFeePlanRequest extends FormRequest
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
        return [
            'ends_on' => ['required', 'date', 'after_or_equal:'.$this->route('childFeePlan')->starts_on->toDateString()],
        ];
    }
}
