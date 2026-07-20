<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Database\Seeds\FormRequestSeeder;

class SeedCommand extends Command
{
    protected $signature = 'form-request:seed';

    protected $description = 'Run Seed command';

    public function handle(): void
    {
        $this->call('db:seed', [
            '--class' => FormRequestSeeder::class,
        ]);

        $this->info('Form request seeder executed successfully.');

    }
}
