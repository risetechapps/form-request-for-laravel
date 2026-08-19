<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Http\Request;
use RiseTechApps\FormRequest\Tests\TestCase;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;

/** Form request que usa apenas o trait, sem estender DynamicFormRequest. */
class TraitOnlyRequest extends LaravelFormRequest
{
    use HasFormValidation;

    public static string $id = '';

    public function rules(): array { return []; }
    public function authorize(): bool { return true; }

    protected function validationContext(): array
    {
        return ['id' => self::$id];
    }
}

/** Sem contexto declarado: comportamento deve ficar inalterado. */
class PlainTraitRequest extends LaravelFormRequest
{
    use HasFormValidation;

    public function rules(): array { return []; }
    public function authorize(): bool { return true; }
}

class TraitContextTest extends TestCase
{
    private function make(string $class, array $payload): LaravelFormRequest
    {
        $request = $class::createFrom(Request::create('/x', 'POST', $payload), new $class());
        $request->setContainer($this->app);

        return $request;
    }

    public function test_trait_exposes_the_context_to_the_validation_data(): void
    {
        TraitOnlyRequest::$id = 'the-id';

        $request = $this->make(TraitOnlyRequest::class, ['name' => 'x']);

        $this->assertSame(['name' => 'x', 'id' => 'the-id'], $request->validationData());
    }

    public function test_context_wins_over_the_payload(): void
    {
        TraitOnlyRequest::$id = 'from-route';

        $request = $this->make(TraitOnlyRequest::class, ['id' => 'from-body']);

        $this->assertSame('from-route', $request->validationData()['id']);
    }

    public function test_without_context_the_payload_is_untouched(): void
    {
        $request = $this->make(PlainTraitRequest::class, ['name' => 'x']);

        $this->assertSame(['name' => 'x'], $request->validationData());
    }
}
