<?php

/*
 * Você pode colocar a configuração personalizada do pacote aqui.
 */
return [

    'validators' => [

    ],

    'forms' => [
        // 'user_registration' => [
        //     'rules' => [
        //         'name' => 'required|string',
        //         'email' => 'required|email|unique:users,email',
        //     ],
        //     'messages' => [
        //         'email.unique' => 'validation.email_unique',
        //     ],
        //     'metadata' => [
        //         'description' => 'Regras padrão para formulários de cadastro de usuário.',
        //         'description' => 'Default rules for user registration forms.',
        //     ],
        // ],
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'store' => null,
    ],
];
