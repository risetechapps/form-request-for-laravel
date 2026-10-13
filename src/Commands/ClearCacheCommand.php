<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Catalog\FormCatalog;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ClearCacheCommand extends Command
{
    protected $signature = 'form-request:clear-cache
                            {form? : Nome específico do formulário para limpar cache}
                            {--all : Limpar cache de todos os formulários}';

    protected $description = 'Limpar o cache de regras de validação';

    public function handle(ValidationRuleRepository $repository, FormCatalog $catalog): int
    {
        $formName = $this->argument('form');

        // Sem alvo continua sendo erro de uso, mesmo com o cache desligado.
        if (!$repository->cacheEnabled() && ($formName || $this->option('all'))) {
            $this->warn('⚠️  Cache desabilitado (rules.cache.enabled). Nada a limpar.');

            return self::SUCCESS;
        }

        if ($formName) {
            $repository->clearCache($formName);
            $this->info("✅ Cache limpo para o formulário '{$formName}'.");
        } elseif ($this->option('all')) {
            $this->clearAllCache($repository, $catalog);
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

    private function clearAllCache(ValidationRuleRepository $repository, FormCatalog $catalog): void
    {
        $this->info('🧹 Limpando cache de todos os formulários...');

        // Invalida apenas as chaves do pacote, nunca o cache inteiro da aplicação.
        $names = array_keys($catalog->entries());

        foreach ($names as $name) {
            $repository->clearCache($name);
        }

        $this->info('✅ Cache limpo para ' . count($names) . ' formulário(s).');
    }
}
