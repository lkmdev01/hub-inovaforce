<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordValidationTranslationTest extends TestCase
{
    public function test_mixed_case_password_error_is_translated_to_portuguese(): void
    {
        app()->setLocale('pt_BR');

        $validator = Validator::make([
            'password' => 'somente-minusculas',
        ], [
            'password' => [Password::min(8)->mixedCase()],
        ]);

        $this->assertSame(
            'A senha deve conter pelo menos uma letra maiúscula e uma minúscula.',
            $validator->errors()->first('password'),
        );
    }
}
