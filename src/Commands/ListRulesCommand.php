<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ListRulesCommand extends Command
{
    protected $signature = 'form-request:list
                            {--database : Mostrar apenas regras do banco de dados}
                            {--config : Mostrar apenas regras configuradas em código}
                            {--form= : Filtrar por nome específico do formulário}
                            {--field= : Filtrar por campo específico (ex: email, name)}';

    protected $description = 'Listar regras de validação e mensagens do form-request';

    public function handle(
        ValidationRuleRepository $repository,
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $showDatabase = $this->option('database') || (!$this->option('config') && !$this->option('database'));
        $showConfig = $this->option('config') || (!$this->option('config') && !$this->option('database'));
        $filterForm = $this->option('form');
        $filterField = $this->option('field');

        $hasOutput = false;

        if ($showConfig) {
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  Regras Configuradas em Código (RulesRegistry)');
            $this->info('═══════════════════════════════════════════════════');

            $forms = $registry->all();

            if (empty($forms)) {
                $this->warn('  Nenhuma regra configurada em código.');
            } else {
                foreach ($forms as $name => $definition) {
                    if ($filterForm && $name !== $filterForm) {
                        continue;
                    }

                    // Usa o repository para resolver regras e mensagens corretamente
                    $resolved = $repository->getRules($name);
                    $this->displayForm($name, $resolved['rules'], $resolved['messages'], $definition->metadata(), $filterField);
                    $hasOutput = true;
                }
            }
        }

        if ($showDatabase) {
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  Regras do Banco de Dados');
            $this->info('═══════════════════════════════════════════════════');

            $query = $formRequestModel->newQuery();

            if ($filterForm) {
                $query->where('form', $filterForm);
            }

            $forms = $query->get();

            if ($forms->isEmpty()) {
                $this->warn('  Nenhuma regra encontrada no banco de dados.');
            } else {
                foreach ($forms as $form) {
                    // Usa o repository para resolver regras e mensagens corretamente
                    $resolved = $repository->getRules($form->form);
                    $this->displayForm(
                        $form->form,
                        $resolved['rules'],
                        $resolved['messages'],
                        $form->data ?? [],
                        $filterField
                    );
                    $hasOutput = true;
                }
            }
        }

        if (!$hasOutput && $filterForm) {
            $this->warn("Nenhuma regra encontrada para o formulário '{$filterForm}'.");
            return 1;
        }

        return 0;
    }

    /**
     * Exibe um formulário com suas regras e mensagens em formato de tabela.
     *
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, mixed> $metadata
     * @param string|null $filterField
     */
    private function displayForm(string $name, array $rules, array $messages, array $metadata, ?string $filterField = null): void
    {
        // Filtra regras por campo se especificado
        if ($filterField) {
            $rules = array_filter($rules, function ($field) use ($filterField) {
                return $field === $filterField || str_contains($field, $filterField);
            }, ARRAY_FILTER_USE_KEY);

            if (empty($rules)) {
                return; // Pula formulários que não têm o campo
            }
        }

        $this->line('');
        $this->info("  📋 Formulário: {$name}");
        $this->line('  ' . str_repeat('─', 100));

        if (!empty($metadata)) {
            $this->line('  📌 Metadados:');
            foreach ($metadata as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $this->line("     • {$key}: {$displayValue}");
            }
            $this->line('');
        }

        if (!empty($rules)) {
            // Garante que todas as mensagens existam
            $completeMessages = $this->generateMessagesForRules($rules, $messages);
            $rows = [];

            foreach ($rules as $field => $rule) {
                $ruleArray = is_array($rule) ? $rule : explode('|', $rule);
                $fieldName = $field; // Guarda o nome do campo
                $isFirstRule = true;

                foreach ($ruleArray as $individualRule) {
                    $individualRule = trim($individualRule);
                    if (empty($individualRule)) continue;

                    $ruleName = $this->extractRuleName($individualRule);
                    $messageKey = "{$fieldName}.{$ruleName}";
                    $message = $completeMessages[$messageKey] ?? $messageKey;

                    $rows[] = [
                        'campo' => $isFirstRule ? $fieldName : '',
                        'regra' => $individualRule,
                        'mensagem' => $message,
                    ];

                    $isFirstRule = false;
                }
            }

            $this->table(
                ['Campo', 'Regra', 'Mensagem'],
                $rows
            );
        } else {
            $this->warn('     Nenhuma regra definida.');
        }

        $this->line('');
    }

    /**
     * Extrai o nome da regra (remove parâmetros).
     */
    private function extractRuleName(string $rule): string
    {
        $parts = explode(':', $rule);
        $name = $parts[0];

        // Converte camelCase para snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Gera mensagens padrão para todas as regras, mesclando com as existentes.
     *
     * @param array<string, mixed> $rules
     * @param array<string, string> $existingMessages
     * @return array<string, string>
     */
    private function generateMessagesForRules(array $rules, array $existingMessages): array
    {
        $messages = $existingMessages;

        foreach ($rules as $field => $rule) {
            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleArray as $individualRule) {
                $individualRule = trim($individualRule);
                if (empty($individualRule) || !is_string($individualRule)) {
                    continue;
                }

                $ruleName = $this->extractRuleName($individualRule);
                $messageKey = "{$field}.{$ruleName}";

                // Se não existe mensagem personalizada, gera a padrão
                if (!isset($messages[$messageKey])) {
                    $messages[$messageKey] = $messageKey;
                }
            }
        }

        return $messages;
    }
}
