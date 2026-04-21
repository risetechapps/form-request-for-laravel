<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'form-request:seed';

    protected $description = 'Run Seed command';

    public function handle(): void
    {
        $this->call('db:seed', [
            '--class' => 'RiseTechApps\\FormRequest\\Database\\seeds\\FormRequestSeeder',

        ]);

        $this->info('Form request seeder executed successfully.');

    }
}
