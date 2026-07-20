<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ClearCacheCommand extends Command
{
    protected $signature = 'form-request:clear-cache
                            {form? : Nome específico do formulário para limpar cache}
                            {--all : Limpar cache de todos os formulários}';

    protected $description = 'Limpar o cache de regras de validação';

    public function handle(
        ValidationRuleRepository $repository,
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $formName = $this->argument('form');

        if ($formName) {
            $repository->clearCache($formName);
            $this->info("✅ Cache limpo para o formulário '{$formName}'.");
        } elseif ($this->option('all')) {
            $this->clearAllCache($repository, $registry, $formRequestModel);
        } else {
            $this->warn('⚠️  Use um nome de formulário ou a opção --all');
            $this->line('');
            $this->line('Exemplos:');
            $this->line('  php artisan form-request:clear-cache clients');
            $this->line('  php artisan form-request:clear-cache --all');
            return 1;
        }

        return 0;
    }

    private function clearAllCache(
        ValidationRuleRepository $repository,
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): void {
        $this->info('🧹 Limpando cache de todos os formulários...');

        // Coleta os nomes de formulários do banco e da configuração e invalida
        // apenas as chaves do pacote — nunca o cache inteiro da aplicação.
        $names = collect($formRequestModel->newQuery()->pluck('form'))
            ->merge(array_keys($registry->all()))
            ->filter()
            ->unique()
            ->values();

        foreach ($names as $name) {
            $repository->clearCache($name);
        }

        $this->info("✅ Cache limpo para {$names->count()} formulário(s).");
    }
}
