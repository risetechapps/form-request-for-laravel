<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

class FactoryRequest extends DynamicFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function formKey(): string
    {
        return 'factory';
    }
}

class DynamicFormRequestFactoryTest extends TestCase
{
    public function test_the_symfony_factory_can_build_the_request(): void
    {
        // Regressão: o construtor declarava ValidationRuleRepository como
        // argumento #1, e Request::create() chama new static() com a assinatura
        // do Symfony — o que resultava em TypeError.
        $request = FactoryRequest::create('/factory', 'POST', ['name' => 'x']);

        $this->assertInstanceOf(FactoryRequest::class, $request);
        $this->assertSame('x', $request->input('name'));
    }

    public function test_the_repository_is_resolved_on_demand(): void
    {
        FormRequest::register('factory', ['name' => 'required|string']);

        $request = FactoryRequest::create('/factory', 'POST', ['name' => 'x']);
        $request->setContainer($this->app);

        $this->assertSame(['name' => 'required|string'], $request->rules());
    }

    public function test_duplicate_keeps_working(): void
    {
        $request = FactoryRequest::create('/factory', 'POST', ['name' => 'x']);

        $this->assertInstanceOf(FactoryRequest::class, $request->duplicate());
    }
}
