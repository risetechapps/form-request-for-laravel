<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

/**
 * Repositório responsável por resolver regras de validação do banco, da configuração e do cache.
 */
class ValidationRuleRepository
{
    private const CACHE_KEY_PREFIX = 'form-request:';
    private const CACHE_KEY_REGISTRY = 'form-request:keys:';

    private CacheRepository $cache;

    private bool $cacheEnabled;

    private int $cacheTtl;

    /**
     * Configura as dependências do repositório e o comportamento do cache.
     */
    public function __construct(
        private readonly FormRequestModel $forms,
        CacheFactory $cacheFactory,
        private readonly FormRegistry $registry
    ) {
        $cacheConfig = config('rules.cache', []);
        $this->cacheEnabled = (bool) ($cacheConfig['enabled'] ?? false);
        $this->cacheTtl = (int) ($cacheConfig['ttl'] ?? 300);
        $store = $cacheConfig['store'] ?? null;
        $this->cache = $store ? $cacheFactory->store($store) : $cacheFactory->store();
    }

    /**
     * Resolve as regras de validação para o formulário informado e contexto adicional.
     *
     * @param array<string, mixed> $parameter Filtros adicionais para escopo da consulta.
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    public function getRules(string $name, array $parameter = []): array
    {
        $cachedParameters = Arr::except($parameter, ['id']);
        $validationRules = $this->remember($name, $cachedParameters, function () use ($name, $cachedParameters) {
            $rulesFromDatabase = $this->fetchRulesFromDatabase($name, $cachedParameters);

            if (!empty($rulesFromDatabase['rules'])) {
                return $rulesFromDatabase;
            }

            return $this->fetchRulesFromConfiguration($name);
        });

        if (array_key_exists('id', $parameter)) {
            $validationRules['rules'] = $this->setIdUpdate($parameter['id'], $validationRules['rules']);
        }

        return $validationRules;
    }

    /**
     * Limpa as regras em cache e as chaves registradas para o formulário informado.
     */
    public function clearCache(string $name): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $registryKey = $this->cacheRegistryKey($name);
        $keys = $this->cache->pull($registryKey, []);

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }

        $this->cache->forget($this->cacheKey($name));
    }

    /**
     * Gera mensagens padrão para cada regra de validação.
     *
     * @param array<string, mixed> $rules
     * @return array<string, string>
     */
    protected function generateMessages(array $rules): array
    {
        $messages = [];

        foreach ($rules as $key => $value) {
            $messages = array_merge($messages, $this->extractRules($key, $value));
        }

        return $messages;
    }

    /**
     * Normaliza definições de regras em chaves de tradução.
     *
     * @param mixed $rulesDefinition
     * @return array<string, string>
     */
    protected function extractRules(string $field, mixed $rulesDefinition): array
    {
        $rules = is_array($rulesDefinition) ? $rulesDefinition : explode('|', (string) $rulesDefinition);

        $formattedRules = [];

        foreach ($rules as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue;
            }

            $normalizedRule = trim(explode(':', $rule)[0]);
            $normalizedRule = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $normalizedRule));

            $messageKey = $field . '.' . $normalizedRule;
            $formattedRules[$field . '.' . $normalizedRule] = $messageKey;
        }

        return $formattedRules;
    }

    /**
     * Ajusta regras de unique com o identificador informado durante atualizações.
     *
     * @param array<int, mixed> $rules
     * @return array<int, mixed>
     */
    private function setIdUpdate(mixed $id, array $rules): array
    {
        return array_map(function ($rule) use ($id) {
            if (!is_string($rule)) {
                return $rule;
            }

            $parts = array_map('trim', explode('|', $rule));

            foreach ($parts as &$part) {
                if (!str_starts_with($part, 'unique:')) {
                    continue;
                }

                $segments = explode(',', $part);
                if (isset($segments[2])) {
                    $segments[2] = (string) $id;
                } else {
                    $segments[] = (string) $id;
                }

                $part = implode(',', $segments);
            }

            return implode('|', $parts);
        }, $rules);
    }

    /**
     * Carrega as regras armazenadas no banco para o formulário informado.
     *
     * @param array<string, mixed> $parameter
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    private function fetchRulesFromDatabase(string $name, array $parameter = []): array
    {
        $where = array_merge(['form' => $name], $parameter);

        $result = $this->forms->newQuery()
            ->where($where)
            ->first(['rules', 'messages']);

        if (empty($result)) {
            return [
                'rules' => [],
                'messages' => [],
            ];
        }

        $rules = (array) $result->rules;
        $messages = (array) ($result->messages ?? []);

        if (empty($messages)) {
            $messages = $this->generateMessages($rules);
        }

        return [
            'rules' => $rules,
            'messages' => $messages,
        ];
    }

    /**
     * Carrega as regras definidas na configuração para o formulário informado.
     *
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    private function fetchRulesFromConfiguration(string $name): array
    {
        $definition = $this->registry->get($name);

        if (!$definition instanceof FormDefinition) {
            return [
                'rules' => [],
                'messages' => [],
            ];
        }

        $rules = $definition->rules();
        $messages = $definition->messages();

        if (empty($messages)) {
            $messages = $this->generateMessages($rules);
        }

        return [
            'rules' => $rules,
            'messages' => $messages,
        ];
    }

    /**
     * Monta a chave de cache para um formulário e conjunto de parâmetros.
     *
     * @param array<string, mixed> $parameter
     */
    private function cacheKey(string $name, array $parameter = []): string
    {
        if (empty($parameter)) {
            return sprintf('%s%s', self::CACHE_KEY_PREFIX, $name);
        }

        ksort($parameter);

        return sprintf('%s%s:%s', self::CACHE_KEY_PREFIX, $name, md5(json_encode($parameter)));
    }

    /**
     * Armazena o resultado de um callback usando o cache configurado quando habilitado.
     *
     * @param array<string, mixed> $parameter
     * @param callable(): array{rules: array<string, mixed>, messages: array<string, string>} $callback
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    private function remember(string $name, array $parameter, callable $callback): array
    {
        if (!$this->cacheEnabled) {
            return $callback();
        }

        $key = $this->cacheKey($name, $parameter);

        return $this->cache->remember($key, $this->cacheTtl, function () use ($callback, $name, $key) {
            $value = $callback();
            $this->storeCacheKey($name, $key);

            return $value;
        });
    }

    /**
     * Registra as chaves de cache usadas para um formulário permitindo invalidação direcionada.
     */
    private function storeCacheKey(string $name, string $cacheKey): void
    {
        $registryKey = $this->cacheRegistryKey($name);
        $keys = $this->cache->get($registryKey, []);

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            $this->cache->put($registryKey, $keys, $this->cacheTtl);
        }
    }

    /**
     * Monta a chave de registro utilizada para armazenar as referências de cache.
     */
    private function cacheRegistryKey(string $name): string
    {
        return self::CACHE_KEY_REGISTRY . $name;
    }
}
