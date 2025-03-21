<?php

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::createExtensionIfNotExists('citext');

        Schema::create('form_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->caseInsensitiveText('form')->nullable();
            $table->jsonb('rules')->nullable();
            $table->string('description')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_requests');
    }
};
