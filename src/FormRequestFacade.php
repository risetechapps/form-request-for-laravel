<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RiseTechApps\FormRequest\Skeleton\SkeletonClass
 */
class FormRequestFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'form-request';
    }
}
