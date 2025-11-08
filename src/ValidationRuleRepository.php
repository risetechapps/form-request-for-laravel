<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

class ValidationRuleRepository
{
    private CacheRepository $cache;

    private bool $cacheEnabled;

    private int $cacheTtl;

    public function __construct(
        private readonly FormRequestModel $forms,
        CacheFactory $cacheFactory
    ) {
        $cacheConfig = config('rules.cache', []);
        $this->cacheEnabled = (bool) ($cacheConfig['enabled'] ?? false);
        $this->cacheTtl = (int) ($cacheConfig['ttl'] ?? 300);
        $store = $cacheConfig['store'] ?? null;
        $this->cache = $store ? $cacheFactory->store($store) : $cacheFactory->store();
    }

    public function getRules(string $name, array $parameter = []): array
    {
        $cachedParameters = Arr::except($parameter, ['id']);
        $cacheKey = $this->cacheKey($name, $cachedParameters);

        $validationRules = $this->remember($cacheKey, function () use ($name, $cachedParameters) {
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

    protected function generateMessages(array $rules): array
    {
        $messages = [];

        foreach ($rules as $key => $value) {
            $messages = array_merge($messages, $this->extractRules($key, $value));
        }

        return $messages;
    }

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

    private function fetchRulesFromDatabase(string $name, array $parameter = []): array
    {
        $where = array_merge(['form' => $name], $parameter);

        $result = $this->forms->newQuery()
            ->where($where)
            ->first(['rules']);

        if (empty($result)) {
            return [
                'rules' => [],
                'messages' => [],
            ];
        }

        $rules = (array) $result->rules;

        return [
            'rules' => $rules,
            'messages' => $this->generateMessages($rules),
        ];
    }

    private function fetchRulesFromConfiguration(string $name): array
    {
        $defaults = Rules::defaultRules();

        if (!array_key_exists($name, $defaults)) {
            return [
                'rules' => [],
                'messages' => [],
            ];
        }

        $configuration = $defaults[$name];

        if (is_array($configuration) && array_key_exists('rules', $configuration)) {
            $rules = (array) $configuration['rules'];
            $messages = $configuration['messages'] ?? $this->generateMessages($rules);
        } else {
            $rules = (array) $configuration;
            $messages = $this->generateMessages($rules);
        }

        return [
            'rules' => $rules,
            'messages' => $messages,
        ];
    }

    private function cacheKey(string $name, array $parameter = []): string
    {
        if (empty($parameter)) {
            return sprintf('form-request:%s', $name);
        }

        ksort($parameter);

        return sprintf('form-request:%s:%s', $name, md5(json_encode($parameter)));
    }

    private function remember(string $key, callable $callback): array
    {
        if (!$this->cacheEnabled) {
            return $callback();
        }

        return $this->cache->remember($key, $this->cacheTtl, $callback);
    }
}
