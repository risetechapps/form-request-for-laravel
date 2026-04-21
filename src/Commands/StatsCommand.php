<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class StatsCommand extends Command
{
    protected $signature = 'form-request:stats
                            {--detailed : Mostrar estatísticas detalhadas}';

    protected $description = 'Mostrar estatísticas de uso do form-request';

    public function handle(
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  ESTATÍSTICAS DO FORM REQUEST');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('');

        // Contagem de formulários
        $dbCount = $formRequestModel->newQuery()->count();
        $configCount = count($registry->all());

        $this->info('📊 Resumo:');
        $this->line("  • Formulários no banco: {$dbCount}");
        $this->line("  • Formulários em código: {$configCount}");
        $this->line("  • Total: " . ($dbCount + $configCount));
        $this->line('');

        // Estatísticas de cache
        $this->displayCacheStats();

        // Detalhes
        if ($this->option('detailed')) {
            $this->displayDetailedStats($formRequestModel, $registry);
        }

        return 0;
    }

    private function displayCacheStats(): void
    {
        $this->info('💾 Cache:');

        $cacheConfig = config('rules.cache', []);
        $enabled = ($cacheConfig['enabled'] ?? false) ? 'Sim' : 'Não';
        $store = $cacheConfig['store'] ?? 'default';
        $ttl = $cacheConfig['ttl'] ?? 300;

        $this->line("  • Habilitado: {$enabled}");
        $this->line("  • Store: {$store}");
        $this->line("  • TTL: {$ttl}s");

        // Tentar contar chaves de cache
        try {
            $cacheKeys = $this->getCacheKeys();
            $this->line("  • Chaves em cache: " . count($cacheKeys));
        } catch (\Exception $e) {
            $this->line("  • Chaves em cache: Não foi possível determinar");
        }

        $this->line('');
    }

    private function displayDetailedStats(
        FormRequestModel $formRequestModel,
        FormRegistry $registry
    ): void {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  DETALHES');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('');

        // Formulários do banco
        if ($formRequestModel->newQuery()->count() > 0) {
            $this->info('📁 Formulários do Banco:');
            foreach ($formRequestModel->all() as $form) {
                $rulesCount = count($form->rules ?? []);
                $messagesCount = count($form->messages ?? []);
                $updated = $form->updated_at->format('d/m/Y H:i');
                $this->line("  • {$form->form}");
                $this->line("    Regras: {$rulesCount} | Mensagens: {$messagesCount} | Atualizado: {$updated}");
            }
            $this->line('');
        }

        // Formulários da config
        $configForms = $registry->all();
        if (count($configForms) > 0) {
            $this->info('⚙️  Formulários em Código:');
            foreach ($configForms as $name => $definition) {
                $rulesCount = count($definition->rules());
                $messagesCount = count($definition->messages());
                $this->line("  • {$name}");
                $this->line("    Regras: {$rulesCount} | Mensagens: {$messagesCount}");
            }
            $this->line('');
        }

        // Regras customizadas disponíveis
        $this->info('🔧 Validadores Customizados:');
        $this->line('  • cpf - Valida CPF brasileiro');
        $this->line('  • cnpj - Valida CNPJ brasileiro');
        $this->line('  • uniqueJson - Valida unicidade em JSON');
        $this->line('  • required_if_any - Requerido se qualquer campo for preenchido');
        $this->line('');
    }

    /**
     * @return array<int, string>
     */
    private function getCacheKeys(): array
    {
        // Tentar obter chaves do cache (método varia por driver)
        $keys = [];

        try {
            // Verificar se é possível listar chaves (Redis, por exemplo)
            $store = Cache::store(config('rules.cache.store'));

            // Alguns drivers suportam getAllKeys ou similar
            if (method_exists($store->getStore(), 'getAllKeys')) {
                $allKeys = $store->getStore()->getAllKeys();
                $keys = array_filter($allKeys, fn($k) => str_starts_with($k, 'form-request:'));
            }
        } catch (\Exception $e) {
            // Silenciar erro - nem todos os drivers suportam listagem
        }

        return $keys;
    }
}
