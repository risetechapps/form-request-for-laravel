<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Executa as migrações.
     */
    public function up(): void
    {
        $usesPostgres = DB::getDriverName() === 'pgsql';

        // citext deixa a chave do formulário case-insensitive no PostgreSQL.
        if ($usesPostgres) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
        }

        Schema::create('form_requests', function (Blueprint $table) use ($usesPostgres) {
            // O identificador primário usa UUID para permitir compartilhamento seguro entre serviços.
            $table->uuid('id')->primary();

            // O tipo citext não existe no Blueprint nativo: no PostgreSQL a
            // coluna nasce como string e é convertida logo após a criação.
            $table->string('form')->nullable();

            // Persiste regras de validação e mensagens utilizando JSON/JSONB conforme o driver.
            $jsonColumn = $usesPostgres ? 'jsonb' : 'json';
            $table->{$jsonColumn}('rules')->nullable();
            $table->{$jsonColumn}('messages')->nullable();

            // Permite anexar metadados arbitrários em cada definição de formulário dinâmico.
            $table->{$jsonColumn}('data')->nullable();

            // Descrição textual opcional para ferramentas administrativas.
            $table->string('description')->nullable();

            $table->timestamps();
            $table->unique('form');
        });

        if ($usesPostgres) {
            $tableName = DB::getTablePrefix() . 'form_requests';

            DB::statement("ALTER TABLE \"{$tableName}\" ALTER COLUMN form TYPE citext");
        }
    }

    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_requests');
    }
};
