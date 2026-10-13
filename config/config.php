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

    /*
     * Log channel used when a built-in validator fails internally, e.g. a
     * misconfigured rule such as 'uniqueJson:table'. Null uses the default.
     */
    'log_channel' => null,

    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'store' => null,
    ],
];
