<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Database\Seeds\FormRequestSeeder;

class SeedCommand extends Command
{
    protected $signature = 'form-request:seed';

    protected $description = 'Popular a tabela form_requests com o seeder do pacote';

    public function handle(): int
    {
        $exitCode = $this->call('db:seed', [
            '--class' => FormRequestSeeder::class,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error('Form request seeder failed.');

            return $exitCode;
        }

        $this->info('Form request seeder executed successfully.');

        return self::SUCCESS;
    }
}
