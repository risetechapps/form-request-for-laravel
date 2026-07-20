<?php

/*
 * Default messages for the validators shipped with the package.
 *
 * These are only fallbacks: an application message defined in
 * validation.{rule} or validation.custom.{attribute}.{rule} always wins,
 * which is where translations to other locales belong.
 *
 * Keys are the snake_case form of the rule name, matching the key Laravel
 * looks up for custom rules.
 */
return [
    'cpf' => 'The :attribute field must be a valid CPF.',
    'cnpj' => 'The :attribute field must be a valid CNPJ.',
    'cnae' => 'The :attribute field must be a valid CNAE code.',
    'ncm' => 'The :attribute field must be a valid NCM code.',

    'credit_card' => 'The :attribute field must be a valid card number.',
    'pix_key' => 'The :attribute field must be a valid Pix key.',
    'bank_barcode' => 'The :attribute field must be a valid bank barcode.',
    'digitable_line' => 'The :attribute field must be a valid digitable line.',
    'bank_slip' => 'The :attribute field must be a valid bank slip.',

    'strong_password' => 'The :attribute field is not strong enough.',
    'unique_json' => 'The :attribute has already been taken.',
    'exists_json' => 'The selected :attribute is invalid.',
    'required_if_any' => 'The :attribute field is required.',
];
