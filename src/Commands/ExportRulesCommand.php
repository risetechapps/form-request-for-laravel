<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class ExportRulesCommand extends Command
{
    protected $signature = 'form-request:export
                            {--file= : Arquivo de saída (JSON)}
                            {--form= : Exportar apenas um formulário específico}
                            {--database : Incluir apenas regras do banco}
                            {--config : Incluir apenas regras configuradas}';

    protected $description = 'Exportar regras de validação para um arquivo JSON';

    public function handle(
        FormRegistry $registry,
        FormRequestModel $formRequestModel
    ): int {
        $file = $this->option('file') ?: 'form-requests-' . date('Y-m-d-His') . '.json';
        $filterForm = $this->option('form');
        $databaseOnly = $this->option('database');
        $configOnly = $this->option('config');

        $exportData = [];

        // Exportar do banco
        if (!$configOnly) {
            $query = $formRequestModel->newQuery();

            if ($filterForm) {
                $query->where('form', $filterForm);
            }

            $dbForms = $query->get();

            foreach ($dbForms as $form) {
                $exportData[$form->form] = [
                    'source' => 'database',
                    'rules' => $form->rules,
                    'messages' => $form->messages ?? [],
                    'description' => $form->description,
                    'data' => $form->data ?? [],
                ];
            }
        }

        // Exportar da config
        if (!$databaseOnly) {
            $configForms = $registry->all();

            foreach ($configForms as $name => $definition) {
                if ($filterForm && $name !== $filterForm) {
                    continue;
                }

                if (!isset($exportData[$name])) {
                    $exportData[$name] = [
                        'source' => 'config',
                        'rules' => $definition->rules(),
                        'messages' => $definition->messages(),
                        'metadata' => $definition->metadata(),
                    ];
                }
            }
        }

        if (empty($exportData)) {
            $this->warn('⚠️  Nenhuma regra encontrada para exportar.');
            return 1;
        }

        $outputPath = $this->getOutputPath($file);
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($outputPath, $jsonContent) === false) {
            $this->error('❌ Erro ao salvar arquivo.');
            return 1;
        }

        $this->info('✅ Regras exportadas com sucesso!');
        $this->line("📁 Arquivo: {$outputPath}");
        $this->line("📊 Total de formulários: " . count($exportData));

        // Mostrar resumo
        $this->line('');
        $this->info('Resumo:');
        foreach (array_keys($exportData) as $formName) {
            $this->line("  • {$formName}");
        }

        return 0;
    }

    private function getOutputPath(string $file): string
    {
        if (str_starts_with($file, '/') || str_starts_with($file, 'C:\\') || str_starts_with($file, 'D:\\')) {
            return $file;
        }

        return base_path($file);
    }
}
