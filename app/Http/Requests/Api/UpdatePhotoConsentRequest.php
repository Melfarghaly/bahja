<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhotoConsentRequest extends FormRequest
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
            // Photos of my child on our wall.
            'wall' => ['required_without:group_photos', 'boolean'],
            // My child in group photos that other families see.
            'group_photos' => ['required_without:wall', 'boolean'],
        ];
    }
}
