<?php

/*
 * You can place your custom package configuration in here.
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
