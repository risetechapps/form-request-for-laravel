<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Validation\RuleInspector;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ImportRulesCommand extends Command
{
    protected $signature = 'form-request:import
                            {file : Arquivo JSON para importar}
                            {--force : Sobrescrever regras existentes}
                            {--dry-run : Mostrar o que seria feito, sem gravar}';

    protected $description = 'Importar regras de validação de um arquivo JSON';

    public function handle(
        FormRequestModel $formRequestModel,
        ValidationRuleRepository $ruleRepository,
        RuleInspector $inspector
    ): int {
        $filePath = $this->resolveFilePath($this->argument('file'));

        if (!is_file($filePath)) {
            $this->error("❌ Arquivo não encontrado: {$filePath}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ Erro ao decodificar JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        // Um objeto { "formulario": {...} }; uma lista viraria formulários "0", "1"...
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            $this->error('❌ Formato de arquivo inválido. Esperado um objeto JSON: { "formulario": { "rules": {...} } }.');

            return self::FAILURE;
        }

        // Valida o arquivo inteiro antes de gravar qualquer coisa: as mesmas
        // checagens da API (POST /forms), inclusive regras desconhecidas.
        $records = [];
        $problems = [];

        foreach ($data as $formName => $formData) {
            $formName = (string) $formName;
            $formProblems = $this->problems($formName, $formData, $inspector);

            if ($formProblems !== []) {
                $problems[$formName] = $formProblems;

                continue;
            }

            /** @var array<string, mixed> $formData */
            $records[$formName] = $this->attributes($formName, $formData);
        }

        if ($problems !== []) {
            $this->error('❌ Arquivo recusado, nada foi importado:');

            foreach ($problems as $formName => $formProblems) {
                foreach ($formProblems as $problem) {
                    $this->line("  • {$formName}: {$problem}");
                }
            }

            return self::FAILURE;
        }

        $existing = $formRequestModel->newQuery()
            ->whereIn('form', array_keys($records))
            ->get()
            ->keyBy('form');

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info($dryRun ? '🔎 Simulação (--dry-run), nada será gravado:' : '📥 Importando regras...');
        $this->line('');

        $plan = ['create' => [], 'update' => [], 'skip' => []];

        foreach (array_keys($records) as $formName) {
            $action = !$existing->has($formName) ? 'create' : ($force ? 'update' : 'skip');
            $plan[$action][] = $formName;

            match ($action) {
                'create' => $this->info("  ✨ '{$formName}' - " . ($dryRun ? 'Seria criado' : 'Criado')),
                'update' => $this->info("  🔄 '{$formName}' - " . ($dryRun ? 'Seria atualizado' : 'Atualizado')),
                'skip' => $this->warn("  ⏭️  '{$formName}' - Já existe (use --force para sobrescrever)"),
            };
        }

        if (!$dryRun) {
            // Tudo ou nada: uma falha no meio não deixa metade do arquivo gravada.
            $formRequestModel->getConnection()->transaction(function () use ($plan, $records, $existing, $formRequestModel) {
                foreach ($plan['create'] as $formName) {
                    $formRequestModel->newQuery()->create($records[$formName]);
                }

                foreach ($plan['update'] as $formName) {
                    $existing->get($formName)?->update($records[$formName]);
                }
            });

            // Também ao criar: o nome pode estar em cache com as regras da configuração.
            foreach ([...$plan['create'], ...$plan['update']] as $formName) {
                $ruleRepository->clearCache($formName);
            }
        }

        $created = count($plan['create']);
        $updated = count($plan['update']);
        $skipped = count($plan['skip']);

        $this->line('');
        $this->info($dryRun ? '✅ Simulação concluída.' : '✅ Importação concluída!');
        $this->line("📊 Criados: {$created} | Atualizados: {$updated} | Ignorados: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Problemas de um formulário do arquivo.
     *
     * @return array<int, string>
     */
    private function problems(string $formName, mixed $formData, RuleInspector $inspector): array
    {
        if (mb_strlen($formName) < 3) {
            return ['o nome do formulário precisa ter pelo menos 3 caracteres'];
        }

        if (!is_array($formData)) {
            return ['esperado um objeto com a chave "rules"'];
        }

        if (!is_array($formData['rules'] ?? null) || $formData['rules'] === []) {
            return ['"rules" é obrigatório e precisa ser um objeto com as regras de cada campo'];
        }

        $problems = [];

        foreach (['messages', 'data', 'metadata'] as $key) {
            if (isset($formData[$key]) && !is_array($formData[$key])) {
                $problems[] = "\"{$key}\" precisa ser um objeto";
            }
        }

        if (isset($formData['description']) && !is_string($formData['description'])) {
            $problems[] = '"description" precisa ser texto';
        }

        foreach ($inspector->problems($formData['rules']) as $field => $fieldProblems) {
            foreach ($fieldProblems as $problem) {
                $problems[] = "{$field}: {$problem}";
            }
        }

        return $problems;
    }

    /**
     * Atributos do registro. Aceita também o formato antigo do export, em que
     * formulários de código traziam description e dados dentro de "metadata".
     *
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    private function attributes(string $formName, array $formData): array
    {
        $metadata = (array) ($formData['metadata'] ?? []);
        $description = $formData['description'] ?? $metadata['description'] ?? null;
        unset($metadata['description']);

        return [
            'form' => $formName,
            'rules' => $formData['rules'],
            'messages' => $formData['messages'] ?? [],
            'description' => $description,
            'data' => $formData['data'] ?? $metadata,
        ];
    }

    private function resolveFilePath(string $file): string
    {
        foreach ([$file, base_path($file), storage_path($file)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $file;
    }
}
