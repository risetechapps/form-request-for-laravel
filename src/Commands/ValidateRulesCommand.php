<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Catalog\FormCatalog;
use RiseTechApps\FormRequest\Validation\RuleInspector;

class ValidateRulesCommand extends Command
{
    protected $signature = 'form-request:validate-rules
                            {--form= : Validar apenas um formulário específico}';

    protected $description = 'Validar sintaxe das regras de validação';

    /** @var array<string> */
    protected array $errors = [];

    /** @var array<string> */
    protected array $warnings = [];

    private RuleInspector $inspector;

    public function handle(FormCatalog $catalog, RuleInspector $inspector): int
    {
        $this->inspector = $inspector;
        $filterForm = $this->option('form') ?: null;
        $entries = $catalog->entries($filterForm);

        if ($filterForm !== null && $entries === []) {
            $this->error("❌ Formulário '{$filterForm}' não encontrado no banco nem no código.");

            return self::FAILURE;
        }

        $this->info('🔍 Validando regras de validação...');
        $this->line('');

        // As duas origens: a definição em código continua valendo se o
        // registro do banco for apagado.
        foreach ($entries as $entry) {
            if ($entry->stored !== null) {
                $this->validateForm($entry->name, (array) $entry->stored->rules, 'database');
            }

            if ($entry->configured !== null) {
                $this->validateForm($entry->name, $entry->configured->rules(), 'config');
            }
        }

        // Resumo
        $this->line('');
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  RESUMO DA VALIDAÇÃO');
        $this->info('═══════════════════════════════════════════════════');

        if (empty($this->errors) && empty($this->warnings)) {
            $this->info('✅ Todas as regras estão válidas!');
            return 0;
        }

        if (!empty($this->warnings)) {
            $this->warn("⚠️  {$this->getWarningCount()} aviso(s) encontrado(s):");
            foreach ($this->warnings as $warning) {
                $this->warn("  • {$warning}");
            }
        }

        if (!empty($this->errors)) {
            $this->error("❌ {$this->getErrorCount()} erro(s) encontrado(s):");
            foreach ($this->errors as $error) {
                $this->error("  • {$error}");
            }
            return 1;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function validateForm(string $formName, array $rules, string $source): void
    {
        $this->line("Validando '{$formName}' ({$source})...");

        foreach ($this->inspector->problems($rules) as $field => $problems) {
            foreach ($problems as $problem) {
                $this->errors[] = "[{$source}] {$formName}.{$field}: {$problem}";
            }
        }

        foreach ($rules as $field => $rule) {
            $segments = is_array($rule) ? $rule : explode('|', (string) $rule);
            $names = array_map(
                fn($segment) => is_string($segment) ? trim(explode(':', $segment, 2)[0]) : null,
                $segments
            );

            if (array_filter($names) === []) {
                $this->warnings[] = "[{$source}] {$formName}.{$field}: Regra vazia";

                continue;
            }

            // Comparação por nome exato: required_if e afins não contradizem nullable.
            if (in_array('nullable', $names, true) && in_array('required', $names, true)) {
                $this->warnings[] = "[{$source}] {$formName}.{$field}: 'nullable' com 'required' são contraditórios";
            }
        }
    }

    private function getErrorCount(): int
    {
        return count($this->errors);
    }

    private function getWarningCount(): int
    {
        return count($this->warnings);
    }
}
