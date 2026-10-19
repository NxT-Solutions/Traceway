<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rider = $this->route('rider');

        return [
            ...$this->profileRules(is_numeric($rider) ? (int) $rider : null),
            'confirm_email' => ['sometimes', 'boolean'],
        ];
    }
}
