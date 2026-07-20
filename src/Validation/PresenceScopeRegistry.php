<?php

namespace RiseTechApps\FormRequest\Validation;

use Illuminate\Database\Query\Builder;

/**
 * Holds the extra conditions applied to every unique/exists query, per table.
 *
 * Scopes are plain callables receiving the query builder and the table name,
 * and they run at query time, so tenant aware closures always read the
 * currently resolved tenant.
 */
class PresenceScopeRegistry
{
    /**
     * Key used to register a scope that applies to every table.
     */
    public const ANY_TABLE = '*';

    /**
     * @var array<string, array<string, callable(Builder, string): void>>
     */
    protected array $scopes = [];

    protected bool $enabled = true;

    /**
     * Sequência usada para nomear escopos anônimos sem reaproveitar nomes já
     * liberados por forget().
     */
    protected int $sequence = 0;

    /**
     * Registers a scope for a table. Passing a name allows replacing or
     * removing that specific scope later.
     *
     * @param callable(Builder, string): void $scope
     */
    public function scope(string $table, callable $scope, ?string $name = null): static
    {
        $this->scopes[$table][$name ?? 'scope_' . $this->sequence++] = $scope;

        return $this;
    }

    /**
     * Registers a scope applied to every table.
     *
     * @param callable(Builder, string): void $scope
     */
    public function scopeAll(callable $scope, ?string $name = null): static
    {
        return $this->scope(self::ANY_TABLE, $scope, $name);
    }

    /**
     * Removes a single named scope, or every scope of the table when no name
     * is given.
     */
    public function forget(string $table, ?string $name = null): static
    {
        if (is_null($name)) {
            unset($this->scopes[$table]);

            return $this;
        }

        unset($this->scopes[$table][$name]);

        return $this;
    }

    public function flush(): static
    {
        $this->scopes = [];

        return $this;
    }

    /**
     * Scopes that apply to a table: the global ones first, then the specific.
     *
     * @return array<int, callable(Builder, string): void>
     */
    public function scopesFor(string $table): array
    {
        // array_values antes do merge: chaves string iguais em buckets
        // diferentes se sobrescreveriam e descartariam o escopo global.
        return array_merge(
            array_values($this->scopes[self::ANY_TABLE] ?? []),
            $table === self::ANY_TABLE ? [] : array_values($this->scopes[$table] ?? [])
        );
    }

    public function has(string $table): bool
    {
        return $this->scopesFor($table) !== [];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Runs the callback with every scope disabled, restoring the previous
     * state even if the callback throws.
     *
     * @template TValue
     * @param callable(): TValue $callback
     * @return TValue
     */
    public function without(callable $callback): mixed
    {
        $previous = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $previous;
        }
    }

}
