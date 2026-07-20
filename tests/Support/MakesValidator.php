<?php

namespace RiseTechApps\FormRequest\Tests\Support;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;

trait MakesValidator
{
    /**
     * @param array<string, mixed> $data
     */
    protected function makeValidator(array $data = []): Validator
    {
        return new Validator(
            new Translator(new ArrayLoader(), 'en'),
            $data,
            []
        );
    }
}
