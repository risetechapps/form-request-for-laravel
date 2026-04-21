<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class ValidateRulesCommand extends Command
{
    protected $signature = 'form-request:validate-rules
                            {--form= : Validar apenas um formulário específico}
                            {--fix : Tentar corrigir regras inválidas}';

    protected $description = 'Validar sintaxe das regras de validação';

    /** @var array<string> */
    protected array $errors = [];

    /** @var array<string> */
    protected array $warnings = [];

    public function handle(
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $filterForm = $this->option('form');

        $this->info('🔍 Validando regras de validação...');
        $this->line('');

        // Validar regras do banco
        $query = $formRequestModel->newQuery();
        if ($filterForm) {
            $query->where('form', $filterForm);
        }

        $dbForms = $query->get();
        foreach ($dbForms as $form) {
            $this->validateForm($form->form, $form->rules ?? [], 'database');
        }

        // Validar regras da config
        if (!$filterForm) {
            foreach ($registry->all() as $name => $definition) {
                $this->validateForm($name, $definition->rules(), 'config');
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

        foreach ($rules as $field => $rule) {
            $ruleString = is_array($rule) ? implode('|', $rule) : $rule;

            // Verificar regras vazias
            if (empty($ruleString)) {
                $this->warnings[] = "{$formName}.{$field}: Regra vazia";
                continue;
            }

            // Verificar sintaxe de regras específicas
            $this->checkRuleSyntax($formName, $field, $ruleString);

            // Verificar regras comuns
            $this->checkCommonRules($formName, $field, $ruleString);
        }
    }

    private function checkRuleSyntax(string $formName, string $field, string $ruleString): void
    {
        $rules = explode('|', $ruleString);

        foreach ($rules as $rule) {
            $rule = trim($rule);

            if (empty($rule)) {
                continue;
            }

            // Verificar unique: sem parâmetros
            if (preg_match('/^unique$/i', $rule)) {
                $this->errors[] = "{$formName}.{$field}: 'unique' requer parâmetros (ex: unique:table,column)";
            }

            // Verificar exists: sem parâmetros
            if (preg_match('/^exists$/i', $rule)) {
                $this->errors[] = "{$formName}.{$field}: 'exists' requer parâmetros (ex: exists:table,column)";
            }

            // Verificar regras inexistentes
            $ruleName = strtolower(explode(':', $rule)[0]);
            $validRules = $this->getValidRules();

            if (!in_array($ruleName, $validRules)) {
                // Pode ser uma regra customizada, apenas avisar
                if (!in_array($ruleName, ['cpf', 'cnpj', 'uniquejson', 'required_if_any'])) {
                    $this->warnings[] = "{$formName}.{$field}: '{$ruleName}' não é uma regra padrão do Laravel";
                }
            }

            // Verificar parâmetros mal formatados
            if (str_contains($rule, '::')) {
                $this->errors[] = "{$formName}.{$field}: Sintaxe inválida (contém '::')";
            }
        }
    }

    private function checkCommonRules(string $formName, string $field, string $ruleString): void
    {
        // Verificar required sometimes sem lógica
        if (str_contains($ruleString, 'sometimes') && str_contains($ruleString, 'required')) {
            $this->warnings[] = "{$formName}.{$field}: Combinação 'sometimes|required' pode causar comportamento inesperado";
        }

        // Verificar nullable com required
        if (str_contains($ruleString, 'nullable') && str_contains($ruleString, 'required')) {
            $this->warnings[] = "{$formName}.{$field}: 'nullable' com 'required' são contraditórios";
        }
    }

    /**
     * @return array<int, string>
     */
    private function getValidRules(): array
    {
        return [
            'accepted', 'active_url', 'after', 'after_or_equal', 'alpha', 'alpha_dash',
            'alpha_num', 'array', 'bail', 'before', 'before_or_equal', 'between',
            'boolean', 'confirmed', 'current_password', 'date', 'date_equals',
            'date_format', 'declined', 'declined_if', 'different', 'digits',
            'digits_between', 'dimensions', 'distinct', 'email', 'ends_with',
            'enum', 'exclude', 'exclude_if', 'exclude_unless', 'exclude_with',
            'exclude_without', 'exists', 'file', 'filled', 'gt', 'gte', 'image',
            'in', 'in_array', 'integer', 'ip', 'ipv4', 'ipv6', 'json', 'lt',
            'lte', 'mac_address', 'max', 'mimes', 'mimetypes', 'min', 'multiple_of',
            'not_in', 'not_regex', 'nullable', 'numeric', 'password', 'present',
            'prohibited', 'prohibited_if', 'prohibited_unless', 'prohibits',
            'regex', 'required', 'required_if', 'required_unless', 'required_with',
            'required_with_all', 'required_without', 'required_without_all',
            'same', 'size', 'sometimes', 'starts_with', 'string', 'timezone',
            'unique', 'url', 'uuid'
        ];
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
