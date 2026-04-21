<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class ImportRulesCommand extends Command
{
    protected $signature = 'form-request:import
                            {file : Arquivo JSON para importar}
                            {--force : Sobrescrever regras existentes}';

    protected $description = 'Importar regras de validação de um arquivo JSON';

    public function handle(
        FormRequestModel $formRequestModel,
        ValidationRuleRepository $ruleRepository
    ): int {
        $file = $this->argument('file');
        $force = $this->option('force');

        $filePath = $this->resolveFilePath($file);

        if (!file_exists($filePath)) {
            $this->error("❌ Arquivo não encontrado: {$filePath}");
            return 1;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ Erro ao decodificar JSON: ' . json_last_error_msg());
            return 1;
        }

        if (!is_array($data)) {
            $this->error('❌ Formato de arquivo inválido. Esperado um objeto JSON.');
            return 1;
        }

        $this->info('📥 Importando regras...');
        $this->line('');

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($data as $formName => $formData) {
            $existing = $formRequestModel->newQuery()
                ->where('form', $formName)
                ->first();

            $attributes = [
                'form' => $formName,
                'rules' => $formData['rules'] ?? [],
                'messages' => $formData['messages'] ?? [],
                'description' => $formData['description'] ?? ($formData['metadata']['description'] ?? null),
                'data' => $formData['data'] ?? [],
            ];

            if ($existing) {
                if (!$force) {
                    $this->warn("  ⏭️  '{$formName}' - Já existe (use --force para sobrescrever)");
                    $skipped++;
                    continue;
                }

                $existing->update($attributes);
                $ruleRepository->clearCache($formName);
                $this->info("  🔄 '{$formName}' - Atualizado");
                $updated++;
            } else {
                $formRequestModel->create($attributes);
                $this->info("  ✨ '{$formName}' - Criado");
                $imported++;
            }
        }

        $this->line('');
        $this->info('✅ Importação concluída!');
        $this->line("📊 Criados: {$imported} | Atualizados: {$updated} | Ignorados: {$skipped}");

        return 0;
    }

    private function resolveFilePath(string $file): string
    {
        if (file_exists($file)) {
            return $file;
        }

        if (file_exists(base_path($file))) {
            return base_path($file);
        }

        if (file_exists(storage_path($file))) {
            return storage_path($file);
        }

        return $file;
    }
}
