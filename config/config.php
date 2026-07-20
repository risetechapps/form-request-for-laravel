<?php

/*
 * Você pode colocar a configuração personalizada do pacote aqui.
 */
return [

    /*
     * Application specific validators, in the 'rule' => Class::class format.
     * The class must implement RiseTechApps\FormRequest\Contracts\ValidatorContract.
     * Keys declared here override the package built-in validators.
     */
    'validators' => [
        // 'my_document' => \App\Validators\MyDocument::class,
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

    /*
     * Extra conditions appended to the queries of the unique and exists rules,
     * without touching the rule strings. Use '*' to reach every table.
     *
     * Each entry is an invokable class resolved through the container,
     * receiving the query builder and the table name.
     * Closures can be registered at runtime with FormRequest::presenceScope().
     */
    'presence_scopes' => [
        // '*' => [\App\Validation\TenantScope::class],
        // 'authentications' => [\App\Validation\NotDeletedScope::class],
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'store' => null,
    ],
];
