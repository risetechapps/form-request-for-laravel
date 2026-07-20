<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\FormRequestFacade;
use RiseTechApps\FormRequest\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_accessor_is_bound_in_the_container(): void
    {
        $this->assertInstanceOf(FormRequest::class, $this->app->make('form-request'));
    }

    public function test_accessor_resolves_to_the_same_singleton(): void
    {
        $this->assertSame($this->app->make(FormRequest::class), $this->app->make('form-request'));
    }

    public function test_facade_proxies_calls(): void
    {
        FormRequestFacade::register('via_facade', ['name' => 'required|string']);

        $resolved = FormRequestFacade::resolve('via_facade');

        $this->assertSame('required|string', $resolved['rules']['name']);
    }

    public function test_facade_alias_is_registered_for_the_application(): void
    {
        // Alias declarado no composer.json extra.laravel.aliases.
        $this->assertTrue(class_exists(\FormRequest::class));
    }
}
