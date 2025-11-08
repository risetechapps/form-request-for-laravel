<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Executa as migrações.
     */
    public function up(): void
    {
        // Habilita as extensões do PostgreSQL quando a conexão oferece suporte.
        if (DB::getDriverName() === 'pgsql') {
            Schema::createExtensionIfNotExists('citext');
        }

        $usesPostgres = DB::getDriverName() === 'pgsql';

        Schema::create('form_requests', function (Blueprint $table) use ($usesPostgres) {
            // O identificador primário usa UUID para permitir compartilhamento seguro entre serviços.
            $table->uuid('id')->primary();

            // Armazena a chave legível do formulário usando coluna case-insensitive no PostgreSQL.
            if ($usesPostgres) {
                $table->caseInsensitiveText('form')->nullable();
            } else {
                $table->string('form')->nullable();
            }

            // Persiste regras de validação e mensagens utilizando JSON/JSONB conforme o driver.
            $jsonColumn = $usesPostgres ? 'jsonb' : 'json';
            $table->{$jsonColumn}('rules')->nullable();
            $table->{$jsonColumn}('messages')->nullable();

            // Permite anexar metadados arbitrários em cada definição de formulário dinâmico.
            $table->{$jsonColumn}('data')->nullable();

            // Descrição opcional para auxiliar ferramentas administrativas.
            $table->string('description')->nullable();

            $table->timestamps();
            $table->unique('form');
        });
    }

    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_requests');
    }
};
