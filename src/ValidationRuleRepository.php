<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class ValidationRuleRepository
{
    private const CACHE_KEY_PREFIX = 'form-request:';
    private const CACHE_KEY_REGISTRY = 'form-request:keys:';

    private CacheRepository $cache;
    private bool $cacheEnabled;
    private int $cacheTtl;

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
        // parâmetros que NÃO devem influenciar o cache
        $cachedParameters = Arr::except($parameter, ['id']);

        $validationRules = $this->remember($name, $cachedParameters, function () use ($name, $cachedParameters) {
            $fromDatabase = $this->fetchRulesFromDatabase($name, $cachedParameters);

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
     */
    private function resolveRuleParameters(array $rules, array $parameters): array
    {
        return array_map(function ($rule) use ($parameters) {
            if (!is_string($rule)) {
                return $rule;
            }

            foreach ($parameters as $key => $value) {

                $rule = str_replace(
                    ["{$key}", "{{$key}}"],
                    (string) $value,
                    $rule
                );
            }

            return $rule;
        }, $rules);
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
     * Busca regras no banco.
     */
    private function fetchRulesFromDatabase(string $name, array $parameter = []): array
    {
        $where = array_merge(['form' => $name], $parameter);

        $result = $this->forms->newQuery()
            ->where($where)
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
            $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

            $formatted["{$field}.{$name}"] = "{$field}.{$name}";
        }

        return $formatted;
    }

    /**
     * Cache helpers
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

    private function cacheKey(string $name, array $parameter = []): string
    {
        if (empty($parameter)) {
            return self::CACHE_KEY_PREFIX . $name;
        }

        ksort($parameter);

        return sprintf(
            '%s%s:%s',
            self::CACHE_KEY_PREFIX,
            $name,
            md5(json_encode($parameter))
        );
    }

    private function storeCacheKey(string $name, string $cacheKey): void
    {
        $registryKey = self::CACHE_KEY_REGISTRY . $name;
        $keys = $this->cache->get($registryKey, []);

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            $this->cache->put($registryKey, $keys, $this->cacheTtl);
        }
    }
}
