<?php

namespace App\Rules;

use App\Support\PersonName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PersonName::isValid($value)) {
            $fail(PersonName::MESSAGE);
        }
    }
}
