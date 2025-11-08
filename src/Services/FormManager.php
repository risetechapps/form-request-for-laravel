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
 * Camada de serviço responsável por coordenar persistência e cache dos formulários dinâmicos.
 */
class FormManager
{
    /**
     * Cria uma nova instância do gerenciador de formulários.
     */
    public function __construct(
        private readonly FormRequestModel $forms,
        private readonly ValidationRuleRepository $rules,
        private readonly FormRegistry $registry
    ) {
    }

    /**
     * Lista formulários dinâmicos aplicando paginação e filtros opcionais.
     *
     * @param int|null $perPage Quantidade de registros por página ou null para todos.
     * @param array<string, mixed> $filters Filtros opcionais aceitos pelo repositório.
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
     * Recupera um formulário armazenado ou lança exceção caso não exista.
     */
    public function findOrFail(string $id): FormRequestModel
    {
        return $this->forms->newQuery()->findOrFail($id);
    }

    /**
     * Persiste uma nova definição de formulário.
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
     * Atualiza uma instância existente de definição de formulário.
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
     * Remove o modelo de definição de formulário informado.
     */
    public function delete(FormRequestModel $form): void
    {
        $formName = $form->form;
        $form->delete();

        $this->flushCache($formName);
    }

    /**
     * Remove uma definição de formulário pelo identificador primário.
     */
    public function deleteById(string $id): void
    {
        $form = $this->findOrFail($id);
        $this->delete($form);
    }

    /**
     * Limpa as entradas de cache de regras para um formulário.
     */
    private function flushCache(string $formName): void
    {
        $this->rules->clearCache($formName);
    }

    /**
     * Retorna as definições de formulário configuradas em memória.
     *
     * @return array<string, FormDefinition>
     */
    public function configured(): array
    {
        return $this->registry->all();
    }
}
