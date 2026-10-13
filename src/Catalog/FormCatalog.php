<?php

namespace RiseTechApps\FormRequest\Catalog;

use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

/**
 * Junta os formulários do banco e os registrados em código numa única lista,
 * com a origem efetiva de cada um. Usado pelos comandos Artisan.
 */
class FormCatalog
{
    public function __construct(
        private readonly FormRequestModel $forms,
        private readonly FormRegistry $registry
    ) {
    }

    /**
     * Formulários ordenados por nome.
     *
     * @param string|null $form Só o formulário com este nome.
     * @param string|null $source CatalogEntry::DATABASE ou CONFIG: só os
     *                            formulários cuja origem efetiva é essa.
     * @return array<string, CatalogEntry>
     */
    public function entries(?string $form = null, ?string $source = null): array
    {
        $query = $this->forms->newQuery();

        if ($form !== null) {
            $query->where('form', $form);
        }

        $stored = [];

        foreach ($query->get() as $model) {
            if (is_string($model->form) && $model->form !== '') {
                $stored[$model->form] = $model;
            }
        }

        $configured = $this->registry->all();

        if ($form !== null) {
            $configured = array_intersect_key($configured, [$form => true]);
        }

        $names = array_unique(array_merge(array_keys($stored), array_map(strval(...), array_keys($configured))));
        sort($names);

        $entries = [];

        foreach ($names as $name) {
            $entry = new CatalogEntry($name, $stored[$name] ?? null, $configured[$name] ?? null);

            if ($source === null || $entry->source() === $source) {
                $entries[$name] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Filtro de origem a partir das opções --database e --config. Nenhuma ou
     * as duas juntas significam todas as origens.
     */
    public static function sourceFilter(bool $database, bool $config): ?string
    {
        if ($database === $config) {
            return null;
        }

        return $database ? CatalogEntry::DATABASE : CatalogEntry::CONFIG;
    }
}
