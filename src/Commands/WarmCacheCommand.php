<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class WarmCacheCommand extends Command
{
    protected $signature = 'form-request:warm-cache
                            {--form= : Aquecer cache apenas de um formulário específico}';

    protected $description = 'Pré-carregar cache de regras de validação';

    public function handle(
        ValidationRuleRepository $repository,
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $filterForm = $this->option('form');

        $this->info('🔥 Aquecendo cache de regras...');
        $this->line('');

        $warmed = 0;
        $forms = [];

        // Coletar formulários do banco
        $query = $formRequestModel->newQuery();
        if ($filterForm) {
            $query->where('form', $filterForm);
        }

        foreach ($query->get() as $form) {
            $forms[] = [
                'name' => $form->form,
                'source' => 'database',
            ];
        }

        // Coletar formulários da config (se não filtrado)
        if (!$filterForm) {
            foreach ($registry->all() as $name => $definition) {
                // Verificar se já não foi adicionado do banco
                $exists = array_filter($forms, fn($f) => $f['name'] === $name);
                if (empty($exists)) {
                    $forms[] = [
                        'name' => $name,
                        'source' => 'config',
                    ];
                }
            }
        }

        if (empty($forms)) {
            $this->warn('⚠️  Nenhum formulário encontrado para aquecer cache.');
            return 1;
        }

        // Aquecer cache
        foreach ($forms as $form) {
            $start = microtime(true);

            try {
                $repository->getRules($form['name']);
                $time = round((microtime(true) - $start) * 1000, 2);
                $this->info("  ✅ {$form['name']} ({$form['source']}) - {$time}ms");
                $warmed++;
            } catch (\Exception $e) {
                $this->error("  ❌ {$form['name']}: {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info('═══════════════════════════════════════════════════');
        $this->info("✅ Cache aquecido: {$warmed} formulário(s)");
        $this->info('═══════════════════════════════════════════════════');

        return 0;
    }
}
