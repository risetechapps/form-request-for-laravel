<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use JsonException;
use RiseTechApps\FormRequest\Catalog\FormCatalog;

class ExportRulesCommand extends Command
{
    protected $signature = 'form-request:export
                            {--file= : Arquivo de saída (JSON). Sem a opção, o JSON sai no stdout}
                            {--form= : Exportar apenas um formulário específico}
                            {--database : Incluir apenas formulários cujas regras vêm do banco}
                            {--config : Incluir apenas formulários cujas regras vêm do código}';

    protected $description = 'Exportar regras de validação em JSON, no formato aceito por form-request:import';

    public function handle(FormCatalog $catalog): int
    {
        $entries = $catalog->entries(
            $this->option('form') ?: null,
            FormCatalog::sourceFilter((bool) $this->option('database'), (bool) $this->option('config'))
        );

        $file = $this->option('file') ?: null;

        if ($entries === []) {
            // Em stdout o aviso iria parar dentro do JSON de quem redireciona.
            $file === null
                ? $this->getOutput()->getErrorStyle()->warning('Nenhuma regra encontrada para exportar.')
                : $this->warn('⚠️  Nenhuma regra encontrada para exportar.');

            return self::FAILURE;
        }

        $exportData = [];

        foreach ($entries as $name => $entry) {
            $exportData[$name] = [
                'source' => $entry->source(),
                'rules' => $entry->rules(),
                'messages' => $entry->messages(),
                ...$entry->details(),
            ];
        }

        try {
            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('❌ Erro ao gerar o JSON: ' . $exception->getMessage());

            return self::FAILURE;
        }

        if ($file === null) {
            $this->getOutput()->writeln($json, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $outputPath = $this->getOutputPath($file);

        if (file_put_contents($outputPath, $json . PHP_EOL) === false) {
            $this->error("❌ Erro ao salvar arquivo: {$outputPath}");

            return self::FAILURE;
        }

        $this->info('✅ Regras exportadas com sucesso!');
        $this->line("📁 Arquivo: {$outputPath}");
        $this->line('📊 Total de formulários: ' . count($exportData));
        $this->line('');

        foreach ($entries as $entry) {
            $this->line("  • {$entry->name} ({$entry->sourceLabel()})");
        }

        return self::SUCCESS;
    }

    /**
     * Caminhos relativos partem da raiz da aplicação.
     */
    private function getOutputPath(string $file): string
    {
        // Absoluto: /caminho, C:\caminho ou C:/caminho.
        if (preg_match('#^([A-Za-z]:)?[\\\\/]#', $file) === 1) {
            return $file;
        }

        return base_path($file);
    }
}
