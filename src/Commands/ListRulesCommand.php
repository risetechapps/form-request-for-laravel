<?php

namespace RiseTechApps\FormRequest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use RiseTechApps\FormRequest\Catalog\CatalogEntry;
use RiseTechApps\FormRequest\Catalog\FormCatalog;
use RiseTechApps\FormRequest\Validation\MessageTranslator;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

class ListRulesCommand extends Command
{
    protected $signature = 'form-request:list
                            {--database : Mostrar apenas formulários cujas regras vêm do banco}
                            {--config : Mostrar apenas formulários cujas regras vêm do código}
                            {--form= : Filtrar por nome específico do formulário}
                            {--field= : Filtrar por campo (nome exato ou padrão com *, ex: itens.*)}
                            {--m|messages : Mostrar a mensagem de cada regra}
                            {--missing : Só as regras sem mensagem personalizada (código de saída 1 se houver alguma)}
                            {--json : Saída em JSON}';

    protected $description = 'Listar regras de validação e mensagens do form-request';

    private ?Validator $validator = null;

    public function handle(FormCatalog $catalog, MessageTranslator $translator): int
    {
        $filterForm = $this->option('form') ?: null;
        $filterField = $this->option('field') ?: null;

        $entries = $catalog->entries(
            $filterForm,
            FormCatalog::sourceFilter((bool) $this->option('database'), (bool) $this->option('config'))
        );

        $missing = (bool) $this->option('missing');
        $json = (bool) $this->option('json');
        $matched = 0;
        $forms = [];

        foreach ($entries as $entry) {
            // A checagem é sobre os formulários da aplicação; o do pacote só
            // entra se for pedido pelo nome.
            if ($missing && $filterForm === null && $entry->isInternal()) {
                continue;
            }

            $fields = $this->describeFields($entry, $translator, $filterField);

            // Com --field, formulários sem o campo ficam de fora.
            if ($filterField !== null && $fields === []) {
                continue;
            }

            $matched++;

            if ($missing) {
                $fields = $this->withoutCustomMessages($fields);

                if ($fields === []) {
                    continue;
                }
            }

            $forms[] = ['entry' => $entry, 'fields' => $fields];
        }

        // Nada casou com os filtros: formulário ou campo inexistente.
        if ($matched === 0 && ($filterForm !== null || $filterField !== null)) {
            $json
                ? $this->line('[]')
                : $this->components->warn('Nenhuma regra encontrada para o filtro informado.');

            return self::FAILURE;
        }

        if ($json) {
            $this->line((string) json_encode(
                array_map(fn(array $form) => $this->toJson($form['entry'], $form['fields']), $forms),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        } elseif ($forms !== []) {
            $this->newLine();

            foreach ($forms as $form) {
                $this->displayForm($form['entry'], $form['fields']);
            }

            $this->displayFooter($forms);
        } elseif ($missing) {
            $this->components->info('Todas as regras têm mensagem personalizada.');
        } else {
            $this->components->info('Nenhum formulário encontrado.');
        }

        // Com --missing o comando serve de checagem na CI.
        if ($missing && $forms !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Regras de cada campo com a mensagem que o usuário recebe.
     *
     * @return array<string, array<int, array{rule: string, custom: bool, message: string}>>
     */
    private function describeFields(CatalogEntry $entry, MessageTranslator $translator, ?string $filterField): array
    {
        $messages = $entry->messages();
        $fields = [];

        foreach ($entry->rules() as $field => $definition) {
            $field = (string) $field;

            if ($filterField !== null && $field !== $filterField && !Str::is($filterField, $field)) {
                continue;
            }

            $segments = is_array($definition) ? $definition : explode('|', is_string($definition) ? $definition : '');
            $type = $this->sizeType($segments);
            $rules = [];

            foreach ($segments as $segment) {
                if ($segment instanceof \Stringable) {
                    $segment = (string) $segment;
                }

                if (!is_string($segment)) {
                    // Objeto de regra sem forma textual (closure, ValidationRule).
                    $rules[] = ['rule' => get_debug_type($segment), 'custom' => false, 'message' => ''];

                    continue;
                }

                $segment = trim($segment);

                if ($segment === '') {
                    continue;
                }

                $key = "{$field}.{$this->messageRuleName($segment)}";
                $message = $messages[$key] ?? $key;

                $text = $this->formatMessage($translator->translateMessage($key, is_string($message) ? $message : $key), $type);

                // Sem tradução o marcador volta intacto: a regra não gera
                // mensagem própria (bail, nullable, sometimes).
                if ($text === $key) {
                    $text = '';
                }
                [$rule, $parameters] = ValidationRuleParser::parse($segment);

                $rules[] = [
                    'rule' => $segment,
                    'custom' => is_string($message) && $message !== $key,
                    // Mesma substituição que o Validator faz ao reprovar:
                    // :min vira 3, :values vira a lista do in etc.
                    'message' => $this->validator()->makeReplacements($text, $field, (string) $rule, $parameters),
                ];
            }

            $fields[$field] = $rules;
        }

        return $fields;
    }

    /**
     * Só as regras que geram mensagem e não têm uma personalizada; campos
     * que ficam sem regras saem da lista.
     *
     * @param array<string, array<int, array{rule: string, custom: bool, message: string}>> $fields
     * @return array<string, array<int, array{rule: string, custom: bool, message: string}>>
     */
    private function withoutCustomMessages(array $fields): array
    {
        $result = [];

        foreach ($fields as $field => $rules) {
            $rules = array_values(array_filter(
                $rules,
                fn(array $rule) => !$rule['custom'] && $rule['message'] !== ''
            ));

            if ($rules !== []) {
                $result[$field] = $rules;
            }
        }

        return $result;
    }

    private function validator(): Validator
    {
        return $this->validator ??= ValidatorFacade::make([], []);
    }

    /**
     * @param array<string, array<int, array{rule: string, custom: bool, message: string}>> $fields
     */
    private function displayForm(CatalogEntry $entry, array $fields): void
    {
        $details = $entry->details();
        $missing = (bool) $this->option('missing');
        $count = $missing ? $this->ruleCount($fields) : count($fields);
        $unit = $missing ? ['regra', 'regras'] : ['campo', 'campos'];

        $this->dottedLine(
            2,
            "<fg=green;options=bold>{$entry->name}</> {$this->sourceBadge($entry)}",
            '<fg=gray>' . $count . ' ' . ($count === 1 ? $unit[0] : $unit[1]) . '</>'
        );

        if ($details['description'] !== null) {
            $this->line('  <fg=gray>' . OutputFormatter::escape($details['description']) . '</>');
        }

        foreach ($details['data'] as $key => $value) {
            $value = is_scalar($value) ? (string) $value : (string) json_encode($value);
            $this->line('  <fg=gray>' . OutputFormatter::escape("{$key}: {$value}") . '</>');
        }

        if ($fields === []) {
            $this->line('    <fg=yellow>Nenhuma regra definida.</>');
        }

        $showMessages = $missing || $this->option('messages');

        foreach ($fields as $field => $rules) {
            $this->dottedLine(
                4,
                OutputFormatter::escape($field),
                implode(' <fg=gray>·</> ', array_map(fn(array $rule) => $this->formatRule($rule['rule']), $rules))
            );

            if (!$showMessages) {
                continue;
            }

            $rules = array_filter($rules, fn(array $rule) => $rule['message'] !== '');

            if ($rules === []) {
                continue;
            }

            $width = max(array_map(fn(array $rule) => mb_strlen($rule['rule']), $rules));

            foreach ($rules as $rule) {
                // Personalizada em amarelo; a padrão do Laravel/pacote em cinza.
                $color = $rule['custom'] ? 'yellow' : 'gray';

                $this->line(sprintf(
                    '      <fg=gray>↳ %s</>  <fg=%s>%s</>',
                    OutputFormatter::escape(str_pad($rule['rule'], $width + strlen($rule['rule']) - mb_strlen($rule['rule']))),
                    $color,
                    OutputFormatter::escape($rule['message'])
                ));
            }
        }

        $this->newLine();
    }

    /**
     * @param array<int, array{entry: CatalogEntry, fields: array<string, array<int, array{rule: string, custom: bool, message: string}>>}> $forms
     */
    private function displayFooter(array $forms): void
    {
        $count = count($forms);

        if ($this->option('missing')) {
            $rules = array_sum(array_map(fn(array $form) => $this->ruleCount($form['fields']), $forms));
            $hint = '<fg=yellow>' . $rules . ($rules === 1 ? ' regra' : ' regras') . ' sem mensagem personalizada</>';
        } elseif ($this->option('messages')) {
            $hint = '<fg=yellow>amarelo</>: mensagem personalizada';
        } else {
            $hint = 'use <fg=gray>-m</> para ver as mensagens';
        }

        $this->dottedLine(
            2,
            $hint,
            '<fg=blue;options=bold>' . ($count === 1 ? '1 formulário' : "{$count} formulários") . '</>'
        );
        $this->newLine();
    }

    /**
     * @param array<string, array<int, mixed>> $fields
     */
    private function ruleCount(array $fields): int
    {
        return array_sum(array_map(count(...), $fields));
    }

    /**
     * Linha "esquerda ...... direita" na largura do terminal (até 150 colunas),
     * no estilo do route:list. Aceita as tags de cor do console.
     */
    private function dottedLine(int $indent, string $left, string $right): void
    {
        $formatter = $this->output->getFormatter();
        $width = min((new Terminal())->getWidth(), 150) - 2;
        $used = $indent
            + Helper::width(Helper::removeDecoration($formatter, $left))
            + Helper::width(Helper::removeDecoration($formatter, $right));

        $dots = max($width - $used - 2, 1);

        $this->line(str_repeat(' ', $indent) . $left . ' <fg=gray>' . str_repeat('.', $dots) . '</> ' . $right);
    }

    /**
     * Origem como etiqueta colorida.
     */
    private function sourceBadge(CatalogEntry $entry): string
    {
        return match (true) {
            $entry->overridesConfig() => '<fg=magenta>database</> <fg=gray>(sobrescreve config)</>',
            $entry->source() === CatalogEntry::DATABASE => '<fg=blue>database</>',
            default => '<fg=yellow>config</>',
        };
    }

    /**
     * Nome da regra em destaque e parâmetros em cinza: min<fg=gray>:3</>.
     */
    private function formatRule(string $rule): string
    {
        // Objeto de regra (closure, ValidationRule) chega como nome de classe.
        if (!str_contains($rule, ':') && (str_contains($rule, '\\') || $rule === 'Closure')) {
            return '<fg=magenta>' . OutputFormatter::escape(class_basename($rule)) . '</>';
        }

        [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);

        return OutputFormatter::escape($name)
            . ($parameters !== null ? '<fg=gray>:' . OutputFormatter::escape($parameters) . '</>' : '');
    }

    /**
     * @param array<string, array<int, array{rule: string, custom: bool, message: string}>> $fields
     * @return array<string, mixed>
     */
    private function toJson(CatalogEntry $entry, array $fields): array
    {
        return [
            'form' => $entry->name,
            'source' => $entry->source(),
            'overrides_config' => $entry->overridesConfig(),
            ...$entry->details(),
            'fields' => $fields,
        ];
    }

    /**
     * Nome da regra no formato das chaves de mensagem "campo.regra", o mesmo
     * de ValidationRuleRepository::extractRules().
     */
    private function messageRuleName(string $segment): string
    {
        $name = trim(explode(':', $segment, 2)[0]);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Tipo que o Laravel usa para escolher a mensagem das regras de tamanho
     * (min, max, size, between), deduzido das outras regras do campo.
     *
     * @param array<mixed> $segments
     */
    private function sizeType(array $segments): string
    {
        $names = array_map(
            fn($segment) => is_string($segment) ? trim(explode(':', $segment, 2)[0]) : '',
            $segments
        );

        return match (true) {
            array_intersect($names, ['numeric', 'integer', 'decimal']) !== [] => 'numeric',
            in_array('array', $names, true) => 'array',
            array_intersect($names, ['file', 'image', 'mimes', 'mimetypes', 'extensions']) !== [] => 'file',
            default => 'string',
        };
    }

    /**
     * Regras de tamanho traduzem para uma mensagem por tipo; mostra a que
     * vale para o campo.
     *
     * @param string|array<string, string> $message
     */
    private function formatMessage(string|array $message, string $type): string
    {
        if (is_string($message)) {
            return $message;
        }

        return $message[$type] ?? (string) reset($message);
    }
}
