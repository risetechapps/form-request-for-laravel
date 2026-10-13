<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Catalog\FormCatalog;
use RiseTechApps\FormRequest\ValidationRuleRepository;
use Throwable;

class WarmCacheCommand extends Command
{
    protected $signature = 'form-request:warm-cache
                            {--form= : Aquecer cache apenas de um formulário específico}';

    protected $description = 'Pré-carregar (ou recarregar) o cache de regras de validação';

    public function handle(ValidationRuleRepository $repository, FormCatalog $catalog): int
    {
        if (!$repository->cacheEnabled()) {
            // Não é falha: deploys que sempre rodam o comando não devem quebrar.
            $this->warn('⚠️  Cache desabilitado (rules.cache.enabled). Nada a aquecer.');

            return self::SUCCESS;
        }

        $filterForm = $this->option('form') ?: null;
        $entries = $catalog->entries($filterForm);

        if ($entries === []) {
            $this->warn($filterForm !== null
                ? "⚠️  Formulário '{$filterForm}' não encontrado no banco nem no código."
                : '⚠️  Nenhum formulário encontrado para aquecer cache.');

            return self::FAILURE;
        }

        $this->info('🔥 Aquecendo cache de regras...');
        $this->line('');

        $warmed = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $start = microtime(true);

            try {
                // Sem limpar antes, o remember devolveria a entrada antiga.
                $repository->clearCache($entry->name);
                $repository->getRules($entry->name);

                $time = round((microtime(true) - $start) * 1000, 2);
                $this->info("  ✅ {$entry->name} ({$entry->sourceLabel()}) - {$time}ms");
                $warmed++;
            } catch (Throwable $e) {
                $this->error("  ❌ {$entry->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line('');
        $this->info("✅ Cache aquecido: {$warmed} formulário(s)");

        if ($failed > 0) {
            $this->error("❌ Falhas: {$failed} formulário(s)");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
