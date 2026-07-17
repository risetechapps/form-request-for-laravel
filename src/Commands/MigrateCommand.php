<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as CommandMigrate;
use Illuminate\Database\Migrations\Migrator;

class MigrateCommand extends CommandMigrate
{
    protected $description = 'Migrate the form-request';

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);
        $this->specifyParameters();
    }

    #[\Override]
    public function getName(): ?string
    {
        return static::getCommandName();
    }

    #[\Override]
    public static function getDefaultName(): ?string
    {
        return static::getCommandName();
    }

    protected static function getCommandName(): string
    {
        return 'form-request:migrate';
    }

    #[\Override]
    protected function getMigrationPaths(): array|string
    {
        if ($this->input->hasOption('path') && $this->input->getOption('path')) {
            return parent::getMigrationPaths();
        }

        return __DIR__ . '/../../database/migrations';
    }

    #[\Override]
    public function handle(): void
    {
        parent::handle();
    }
}
