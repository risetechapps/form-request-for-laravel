<?php

namespace RiseTechApps\FormRequest\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class FormManager
{
    public function __construct(
        private readonly FormRequestModel $forms,
        private readonly ValidationRuleRepository $rules,
        private readonly FormRegistry $registry
    ) {
    }

    /**
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

    public function findOrFail(string $id): FormRequestModel
    {
        return $this->forms->newQuery()->findOrFail($id);
    }

    public function create(array $attributes): FormRequestModel
    {
        $form = $this->forms->create($attributes);

        $this->flushCache($form->form);

        return $form;
    }

    public function update(FormRequestModel $form, array $attributes): FormRequestModel
    {
        $form->fill($attributes);
        $form->save();

        $this->flushCache($form->form);

        return $form;
    }

    public function delete(FormRequestModel $form): void
    {
        $formName = $form->form;
        $form->delete();

        $this->flushCache($formName);
    }

    public function deleteById(string $id): void
    {
        $form = $this->findOrFail($id);
        $this->delete($form);
    }

    private function flushCache(string $formName): void
    {
        $this->rules->clearCache($formName);
    }

    /**
     * @return array<string, FormDefinition>
     */
    public function configured(): array
    {
        return $this->registry->all();
    }
}
