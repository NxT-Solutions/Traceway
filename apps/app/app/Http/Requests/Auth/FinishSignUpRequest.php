<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The name and email of a new rider who came from a connected service. An email that already has an account is
 * not an error here: the rider is told to log in to it and connect the service from Settings.
 */
class FinishSignUpRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->toString();
    }

    public function email(): string
    {
        return $this->string('email')->trim()->lower()->toString();
    }
}
