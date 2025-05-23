<?php

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() == 'pgsql') {
            Schema::createExtensionIfNotExists('citext');
        }

        Schema::create('form_requests', function ($table) {
            $table->uuid('id')->primary();
            if(DB::getDriverName() == 'pgsql'){
                $table->caseInsensitiveText('form')->nullable();
            }else{
                $table->string('form')->nullable();
            }

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
