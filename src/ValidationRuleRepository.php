<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class ValidationRuleRepository
{
    private const string CACHE_KEY_PREFIX = 'form-request:';

    private readonly CacheRepository $cache;
    private readonly bool $cacheEnabled;
    private readonly int $cacheTtl;

    public function __construct(
        private readonly FormRequestModel $forms,
        CacheFactory $cacheFactory,
        private readonly FormRegistry $registry
    ) {
        $cacheConfig = config('rules.cache', []);
        $this->cacheEnabled = (bool) ($cacheConfig['enabled'] ?? false);
        $this->cacheTtl = (int) ($cacheConfig['ttl'] ?? 300);

        $store = $cacheConfig['store'] ?? null;
        $this->cache = $store
            ? $cacheFactory->store($store)
            : $cacheFactory->store();
    }

    /**
     * Resolve regras de validação considerando banco, config, cache e parâmetros dinâmicos.
     */
    public function getRules(string $name, array $parameter = []): array
    {
        // O contexto não entra na chave: ele só interpola as regras já
        // resolvidas, então o valor cacheado é o mesmo para qualquer contexto.
        $validationRules = $this->remember($name, function () use ($name) {
            $fromDatabase = $this->fetchRulesFromDatabase($name);

            if (!empty($fromDatabase['rules'])) {
                return $fromDatabase;
            }

            return $this->fetchRulesFromConfiguration($name);
        });

        /**
         * 1️⃣ Ajusta regras `unique:` nativas do Laravel (update)
         */
        if (array_key_exists('id', $parameter)) {
            $validationRules['rules'] = $this->setIdUpdate(
                $parameter['id'],
                $validationRules['rules']
            );
        }

        /**
         * 2️⃣ Resolve placeholders genéricos (:id, {id}, etc)
         */
        if (!empty($parameter)) {
            $validationRules['rules'] = $this->resolveRuleParameters(
                $validationRules['rules'],
                $parameter
            );
        }

        return $validationRules;
    }

    /**
     * Substitui placeholders (:id, {id}) pelos valores reais.
     *
     * A substituição acontece apenas na porção de parâmetros de cada segmento,
     * nunca no nome da regra. O primeiro ':' de um segmento é o separador do
     * Laravel: consumi-lo funde nome e valor e produz uma regra inexistente
     * (uniqueCpfAuthentication:id viraria uniqueCpfAuthentication<uuid>).
     *
     * Como consequência, um parâmetro que apenas se chama como a chave do
     * contexto é preservado — 'exists:tabela,id' mantém a coluna id, e
     * 'regraCustomizada:id' entrega 'id' ao validador, como fazem as regras
     * nativas que referenciam outro campo (same:password, gt:idade).
     */
    private function resolveRuleParameters(array $rules, array $parameters): array
    {
        return array_map(function ($rule) use ($parameters) {
            if (!is_string($rule)) {
                return $rule;
            }

            $segments = array_map(
                fn(string $segment): string => $this->resolveSegmentParameters($segment, $parameters),
                explode('|', $rule)
            );

            return implode('|', $segments);
        }, $rules);
    }

    /**
     * Resolve os placeholders de um único segmento "regra:parametros".
     *
     * @param array<string, mixed> $parameters
     */
    private function resolveSegmentParameters(string $segment, array $parameters): string
    {
        $separator = strpos($segment, ':');

        // Regra sem parâmetros: nada a substituir, e o nome fica intocado.
        if ($separator === false) {
            return $segment;
        }

        $name = substr($segment, 0, $separator);
        $arguments = substr($segment, $separator + 1);

        foreach ($parameters as $key => $value) {
            // Placeholders delimitados ({id}) ou prefixados (:id), nunca a
            // substring crua "id" — evita corromper paid, video_id, width.
            $arguments = str_replace('{' . $key . '}', (string) $value, $arguments);
            $arguments = preg_replace(
                '/:' . preg_quote($key, '/') . '\b/',
                (string) $value,
                $arguments
            );
        }

        return $name . ':' . $arguments;
    }

    /**
     * Ajusta regras unique nativas para update.
     */
    private function setIdUpdate(mixed $id, array $rules): array
    {
        return array_map(function ($rule) use ($id) {
            if (!is_string($rule)) {
                return $rule;
            }

            $parts = array_map(trim(...), explode('|', $rule));

            foreach ($parts as &$part) {
                if (!str_starts_with($part, 'unique:')) {
                    continue;
                }

                $segments = explode(',', $part);

                // Um except explícito é preservado, inclusive a interpolação
                // nativa do Laravel: unique:tabela,coluna,[id].
                if (isset($segments[2]) && $segments[2] !== '') {
                    continue;
                }

                // Coluna omitida (unique:tabela): sem preencher a posição 1 o
                // id cairia nela e viraria o nome da coluna. 'NULL' é o literal
                // que faz o Laravel voltar a usar o nome do atributo.
                if (!isset($segments[1]) || $segments[1] === '') {
                    $segments[1] = 'NULL';
                }

                $segments[2] = (string) $id;

                $part = implode(',', $segments);
            }

            return implode('|', $parts);
        }, $rules);
    }

    /**
     * Busca regras no banco.
     *
     * A resolução é feita apenas pelo nome do formulário. O contexto de
     * validação não entra na consulta: ele é dado de interpolação das regras,
     * aplicado adiante por setIdUpdate() e resolveRuleParameters(), e suas
     * chaves não correspondem a colunas de form_requests.
     */
    private function fetchRulesFromDatabase(string $name): array
    {
        $result = $this->forms->newQuery()
            ->where('form', $name)
            ->first(['rules', 'messages']);

        if (!$result) {
            return ['rules' => [], 'messages' => []];
        }

        $rules = (array) $result->rules;
        $messages = (array) ($result->messages ?? []);

        if (empty($messages)) {
            $messages = $this->generateMessages($rules);
        }

        return compact('rules', 'messages');
    }

    /**
     * Busca regras na configuração.
     */
    private function fetchRulesFromConfiguration(string $name): array
    {
        $definition = $this->registry->get($name);

        if (!$definition instanceof FormDefinition) {
            return ['rules' => [], 'messages' => []];
        }

        $rules = $definition->rules();
        $messages = $definition->messages();

        if (empty($messages)) {
            $messages = $this->generateMessages($rules);
        }

        return compact('rules', 'messages');
    }

    /**
     * Gera mensagens padrão a partir das regras.
     */
    protected function generateMessages(array $rules): array
    {
        $messages = [];

        foreach ($rules as $field => $definition) {
            $messages += $this->extractRules($field, $definition);
        }

        return $messages;
    }

    /**
     * Normaliza regras para geração de mensagens.
     */
    protected function extractRules(string $field, mixed $rulesDefinition): array
    {
        $rules = is_array($rulesDefinition)
            ? $rulesDefinition
            : explode('|', (string) $rulesDefinition);

        $formatted = [];

        foreach ($rules as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue;
            }

            $name = trim(explode(':', $rule)[0]);
            $name = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

            $formatted["{$field}.{$name}"] = "{$field}.{$name}";
        }

        return $formatted;
    }

    /**
     * Cache helpers
     */
    private function remember(string $name, callable $callback): array
    {
        if (!$this->cacheEnabled) {
            return $callback();
        }

        return $this->cache->remember($this->cacheKey($name), $this->cacheTtl, $callback);
    }

    private function cacheKey(string $name): string
    {
        return self::CACHE_KEY_PREFIX . $name;
    }

    /**
     * Remove a entrada de cache do formulário.
     */
    public function clearCache(string $name): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $this->cache->forget($this->cacheKey($name));
    }
}
