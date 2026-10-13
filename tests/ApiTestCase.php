<?php

namespace RiseTechApps\FormRequest\Tests;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Controllers\FormController;
use RiseTechApps\FormRequest\Tests\Support\PermissionUser;

/**
 * Base dos testes da API: registra as rotas e um usuário com o
 * hasPermission() que FormController espera do model da aplicação.
 */
abstract class ApiTestCase extends TestCase
{
    protected PermissionUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Autenticado e com todas as permissões; cada teste restringe o que precisar.
        $this->user = new PermissionUser();
        $this->user->granted = array_map(
            fn(string $action) => FormController::class . '@' . $action,
            ['index', 'store', 'show', 'update', 'destroy', 'schema']
        );

        $this->actingAs($this->user);
    }


    /**
     * @param \Illuminate\Routing\Router $router
     */
    protected function defineRoutes($router): void
    {
        FormRequest::routes(['prefix' => 'api']);
    }

}
