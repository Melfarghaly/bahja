<?php

namespace App\Http\Requests\Api;

use App\Enums\MomentType;
use App\Models\Moment;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMomentRequest extends FormRequest
{
    public const MAX_PHOTOS = 10;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Moment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::enum(MomentType::class)],
            'body' => [Rule::requiredIf(in_array($type, ['note', 'health', 'incident'], true)), 'nullable', 'string', 'max:2000'],

            // Who it is about: chosen children, or a whole class minus exceptions.
            'child_ids' => ['required_without:classroom_id', 'prohibits:classroom_id', 'array', 'min:1', 'max:100'],
            'child_ids.*' => ['integer', 'distinct', TenantRules::exists('children')],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
            'except_child_ids' => ['nullable', 'array', 'max:100'],
            'except_child_ids.*' => ['integer', 'distinct'],

            'photos' => [Rule::requiredIf($type === 'photo'), 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:10240'],

            'payload' => ['nullable', 'array'],
            'payload.meal' => [Rule::requiredIf($type === 'meal'), 'nullable', Rule::in(['breakfast', 'lunch', 'snack', 'dinner'])],
            'payload.amount' => [Rule::requiredIf($type === 'meal'), 'nullable', Rule::in(['all', 'most', 'half', 'little', 'none'])],
            'payload.from' => [Rule::requiredIf($type === 'nap'), 'nullable', 'date_format:H:i'],
            'payload.to' => [Rule::requiredIf($type === 'nap'), 'nullable', 'date_format:H:i', 'after:payload.from'],
            'payload.kind' => [Rule::requiredIf($type === 'diaper'), 'nullable', Rule::in(['wet', 'dirty', 'dry'])],
            'payload.mood' => [Rule::requiredIf($type === 'mood'), 'nullable', Rule::in(['happy', 'calm', 'tired', 'sad', 'upset'])],
            'payload.title' => [Rule::requiredIf($type === 'activity'), 'nullable', 'string', 'max:120'],
        ];
    }
}
