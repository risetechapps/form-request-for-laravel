<?php

namespace RiseTechApps\FormRequest\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

/**
 * Service layer responsible for coordinating persistence and cache concerns for form requests.
 */
class FormManager
{
    /**
     * Create a new form manager instance.
     */
    public function __construct(
        private readonly FormRequestModel $forms,
        private readonly ValidationRuleRepository $rules,
        private readonly FormRegistry $registry
    ) {
    }

    /**
     * List stored dynamic forms applying optional pagination and filters.
     *
     * @param int|null $perPage Number of records per page or null for all.
     * @param array<string, mixed> $filters Optional filters accepted by the repository.
     * @return Collection<int, FormRequestModel>|LengthAwarePaginator<FormRequestModel>
     */
    public function list(?int $perPage = 15, array $filters = []): Collection|LengthAwarePaginator
    {
        $query = $this->forms->newQuery();

        if ($form = Arr::get($filters, 'form')) {
            $query->where('form', $form);
        }

        if ($perPage === null || $perPage <= 0) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    /**
     * Retrieve a stored form or throw if it does not exist.
     */
    public function findOrFail(string $id): FormRequestModel
    {
        return $this->forms->newQuery()->findOrFail($id);
    }

    /**
     * Persist a new form request definition.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): FormRequestModel
    {
        $form = $this->forms->create($attributes);

        $this->flushCache($form->form);

        return $form;
    }

    /**
     * Update an existing form definition instance.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(FormRequestModel $form, array $attributes): FormRequestModel
    {
        $form->fill($attributes);
        $form->save();

        $this->flushCache($form->form);

        return $form;
    }

    /**
     * Delete the given form definition model.
     */
    public function delete(FormRequestModel $form): void
    {
        $formName = $form->form;
        $form->delete();

        $this->flushCache($formName);
    }

    /**
     * Delete a form definition by its primary identifier.
     */
    public function deleteById(string $id): void
    {
        $form = $this->findOrFail($id);
        $this->delete($form);
    }

    /**
     * Flush the cached rule entries for a given form name.
     */
    private function flushCache(string $formName): void
    {
        $this->rules->clearCache($formName);
    }

    /**
     * Return the configured in-memory form definitions.
     *
     * @return array<string, FormDefinition>
     */
    public function configured(): array
    {
        return $this->registry->all();
    }
}
