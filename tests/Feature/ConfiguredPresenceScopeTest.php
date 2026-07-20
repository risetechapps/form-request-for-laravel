<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Exercita os escopos declarados em config, registrados durante o boot do
 * provider — por isso a configuração precisa existir antes da aplicação subir.
 */
class ConfiguredPresenceScopeTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     */
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('rules.presence_scopes', [
            '*' => [NotDeletedScope::class],
            'authentications' => [TenantTwoScope::class],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('authentications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->unsignedInteger('tenant_id');
            $table->timestamp('deleted_at')->nullable();
        });

        DB::table('authentications')->insert([
            ['email' => 'user@teste.com', 'tenant_id' => 1, 'deleted_at' => null],
            ['email' => 'other@teste.com', 'tenant_id' => 2, 'deleted_at' => null],
            ['email' => 'gone@teste.com', 'tenant_id' => 2, 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }

    public function test_table_scope_from_config_is_applied(): void
    {
        // Pertence ao tenant 1, então some para o escopo configurado.
        $this->assertFalse($this->fails('user@teste.com'));
        $this->assertTrue($this->fails('other@teste.com'));
    }

    public function test_wildcard_scope_from_config_is_applied(): void
    {
        $this->assertFalse($this->fails('gone@teste.com'));
    }

    private function fails(mixed $value): bool
    {
        return Validator::make(
            ['email' => $value],
            ['email' => 'unique:authentications,email']
        )->fails();
    }
}

class TenantTwoScope
{
    public function __invoke(Builder $query, string $table): void
    {
        $query->where('tenant_id', 2);
    }
}

class NotDeletedScope
{
    public function __invoke(Builder $query, string $table): void
    {
        $query->whereNull('deleted_at');
    }
}
