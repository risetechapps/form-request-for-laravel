<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RiseTechApps\FormRequest\Skeleton\SkeletonClass
 */
class FormRequestFacade extends Facade
{
    /**
     * Obtém o nome registrado do componente.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'form-request';
    }
}
