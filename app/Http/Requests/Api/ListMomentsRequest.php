<?php

namespace App\Http\Requests\Api;

use App\Models\Moment;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class ListMomentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Moment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'child_id' => ['nullable', 'integer', TenantRules::exists('children')],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
