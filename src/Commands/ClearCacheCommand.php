<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ClearCacheCommand extends Command
{
    protected $signature = 'form-request:clear-cache
                            {form? : Nome específico do formulário para limpar cache}
                            {--all : Limpar cache de todos os formulários}';

    protected $description = 'Limpar o cache de regras de validação';

    public function handle(ValidationRuleRepository $repository): int
    {
        $formName = $this->argument('form');

        if ($formName) {
            $repository->clearCache($formName);
            $this->info("✅ Cache limpo para o formulário '{$formName}'.");
        } elseif ($this->option('all')) {
            // Como não temos método para listar todas as chaves de cache,
            // vamos limpar usando o padrão de chave
            $this->clearAllCache($repository);
        } else {
            $this->warn('⚠️  Use um nome de formulário ou a opção --all');
            $this->line('');
            $this->line('Exemplos:');
            $this->line("  php artisan {$this->signature}");
            $this->line('  php artisan form-request:clear-cache clients');
            $this->line('  php artisan form-request:clear-cache --all');
            return 1;
        }

        return 0;
    }

    private function clearAllCache(ValidationRuleRepository $repository): void
    {
        // Tenta limpar todas as chaves conhecidas
        // Isso é uma aproximação já que não temos listagem de todas as chaves
        $this->info('🧹 Limpando cache de todos os formulários...');

        // Vamos usar reflection para acessar o cache
        try {
            $reflection = new \ReflectionClass($repository);
            $property = $reflection->getProperty('cache');
            $property->setAccessible(true);
            $cache = $property->getValue($repository);

            $prefix = 'form-request:';
            $cache->flush();

            $this->info('✅ Cache de todos os formulários limpo com sucesso!');
        } catch (\Exception $e) {
            $this->error('❌ Erro ao limpar cache: ' . $e->getMessage());
        }
    }
}
