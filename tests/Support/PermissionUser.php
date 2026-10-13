<?php

namespace RiseTechApps\FormRequest\Tests\Support;

use Illuminate\Foundation\Auth\User;

/**
 * Usuário com o hasPermission() que Store/UpdateFormRequest esperam do
 * model da aplicação.
 */
class PermissionUser extends User
{
    /**
     * @var array<int, string>
     */
    public array $granted = [];

    public function hasPermission(string $module): bool
    {
        return in_array($module, $this->granted, true);
    }
}
