<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Catalog\CatalogEntry;
use RiseTechApps\FormRequest\Catalog\FormCatalog;
use RiseTechApps\FormRequest\RulesRegistry;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class StatsCommand extends Command
{
    protected $signature = 'form-request:stats
                            {--detailed : Mostrar estatísticas detalhadas}';

    protected $description = 'Mostrar estatísticas de uso do form-request';

    public function handle(FormCatalog $catalog, ValidationRuleRepository $repository, RulesRegistry $rules): int
    {
        $entries = $catalog->entries();

        $this->info('═══════════════════════════════════════════════════');
        $this->info('  ESTATÍSTICAS DO FORM REQUEST');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('');

        $stored = count(array_filter($entries, fn(CatalogEntry $entry) => $entry->stored !== null));
        $configured = count(array_filter($entries, fn(CatalogEntry $entry) => $entry->configured !== null));
        $overrides = count(array_filter($entries, fn(CatalogEntry $entry) => $entry->overridesConfig()));

        $this->info('📊 Resumo:');
        $this->line("  • Formulários no banco: {$stored}");
        $this->line("  • Formulários em código: {$configured}");
        $this->line("  • Do código sobrescritos pelo banco: {$overrides}");
        // Um formulário nas duas origens conta uma vez.
        $this->line('  • Total: ' . count($entries));
        $this->line('');

        $this->displayCacheStats($repository, $entries);

        if ($this->option('detailed')) {
            $this->displayDetailedStats($entries, $rules);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, CatalogEntry> $entries
     */
    private function displayCacheStats(ValidationRuleRepository $repository, array $entries): void
    {
        $cacheConfig = config('rules.cache', []);

        $this->info('💾 Cache:');
        $this->line('  • Habilitado: ' . ($repository->cacheEnabled() ? 'Sim' : 'Não'));
        $this->line('  • Store: ' . ($cacheConfig['store'] ?? 'default'));
        $this->line('  • TTL: ' . ($cacheConfig['ttl'] ?? 300) . 's');

        if ($repository->cacheEnabled()) {
            $cached = count(array_filter($entries, fn(CatalogEntry $entry) => $repository->isCached($entry->name)));
            $this->line("  • Formulários em cache: {$cached} de " . count($entries));
        }

        $this->line('');
    }

    /**
     * @param array<string, CatalogEntry> $entries
     */
    private function displayDetailedStats(array $entries, RulesRegistry $rules): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  DETALHES');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('');

        if ($entries !== []) {
            $this->info('📁 Formulários:');

            foreach ($entries as $entry) {
                $updated = $entry->source() === CatalogEntry::DATABASE
                    ? ' | Atualizado: ' . ($entry->stored?->updated_at?->format('d/m/Y H:i') ?? 'N/A')
                    : '';

                $this->line("  • {$entry->name} ({$entry->sourceLabel()})");
                $this->line('    Campos: ' . count($entry->rules()) . ' | Mensagens: ' . count($entry->messages()) . $updated);
            }

            $this->line('');
        }

        // Lista real: as do pacote, as de RulesContract e as de rules.validators.
        $validators = $rules->allValidators();
        ksort($validators);

        $this->info('🔧 Validadores customizados (' . count($validators) . '):');

        foreach ($validators as $rule => $class) {
            $this->line("  • {$rule} - {$class}");
        }

        $this->line('');
    }
}
