<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidClientEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        if (User::isInvalidClientEmail($value)) {
            $fail('Debes ingresar un correo electrónico válido y personal (no se permiten correos @tuti).');
        }
    }
}
