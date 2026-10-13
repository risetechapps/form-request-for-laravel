<?php

namespace RiseTechApps\FormRequest\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RiseTechApps\FormRequest\Http\Requests\FormAccessRequest;
use RiseTechApps\FormRequest\Http\Requests\StoreFormRequest;
use RiseTechApps\FormRequest\Http\Requests\UpdateFormRequest;
use RiseTechApps\FormRequest\Http\Resources\FormRequestResource;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\Services\FormManager;
use RiseTechApps\FormRequest\Services\FormSchema;
use RiseTechApps\FormRequest\Support\ErrorReporter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controller de API que expõe endpoints CRUD para formulários dinâmicos.
 */
class FormController extends Controller
{
    /**
     * Injeta o serviço de formulários responsável pelo acesso aos dados.
     */
    public function __construct(private readonly FormManager $forms)
    {
    }

    /**
     * Lista os formulários armazenados com paginação opcional e metadados do registro.
     */
    public function index(FormAccessRequest $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $filters = array_filter([
                'form' => $request->query('form'),
            ]);

            $result = $this->forms->list($perPage, $filters);

            $data = FormRequestResource::collection($result);
            $extra = [];

            // O envelope leva só os itens em data; sem meta o cliente não
            // saberia o total nem quantas páginas existem.
            if ($result instanceof LengthAwarePaginator) {
                $extra['meta'] = [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                ];
            }

            if ($request->boolean('include_configured')) {
                $extra['configured'] = collect($this->forms->configured())
                    ->map(fn(FormDefinition $definition) => [
                        'form' => $definition->name(),
                        'rules' => $definition->rules(),
                        'messages' => $definition->messages(),
                        'metadata' => $definition->metadata(),
                    ])
                    ->values();
            }

            return $this->success($data, $extra);
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error loading datatable');

            return $this->gone('Error loading datatable');
        }
    }

    /**
     * Persiste uma nova definição de formulário dinâmico.
     */
    public function store(StoreFormRequest $request): JsonResponse
    {
        try {
            $form = $this->forms->create($request->validated());

            return $this->success(FormRequestResource::make($form));
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error by registering registration');

            return $this->gone('Error by registering registration');
        }
    }

    /**
     * Exibe uma definição de formulário específica.
     */
    public function show(FormAccessRequest $request): JsonResponse
    {
        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));
            $data = FormRequestResource::make($form);

            return $this->success($data);
        } catch (ModelNotFoundException) {
            return $this->notFound('Form request not found');
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error when loading the record to be viewed');

            return $this->gone('Error when loading the record to be viewed');
        }
    }

    /**
     * Atualiza um registro existente de definição de formulário.
     */
    public function update(UpdateFormRequest $request): JsonResponse
    {
        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));
            $form = $this->forms->update($form, $request->validated());

            return $this->success(FormRequestResource::make($form->refresh()));
        } catch (ModelNotFoundException) {
            return $this->notFound('Form request not found');
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error update the registration');

            return $this->gone('Error update the registration');
        }
    }

    /**
     * Remove a definição de formulário informada.
     */
    public function destroy(FormAccessRequest $request): JsonResponse
    {
        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));

            $this->forms->delete($form);

            return $this->success();
        } catch (ModelNotFoundException) {
            return $this->notFound('Form request not found');
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error by deleting the record');

            return $this->gone('Error by deleting the record');
        }
    }

    /**
     * Descreve as regras de um formulário para validação no cliente.
     */
    public function schema(FormAccessRequest $request, FormSchema $schema): JsonResponse
    {
        try {
            $data = $schema->build((string) $request->route('form'));

            if ($data === null) {
                return $this->notFound('Form request not found');
            }

            return $this->success($data);
        } catch (Throwable $exception) {
            $this->report($exception, $request, 'Error when loading the form schema');

            return $this->gone('Error when loading the form schema');
        }
    }

    /**
     * Resposta de sucesso (200) no envelope da API.
     *
     * @param JsonResource|array<string, mixed>|null $data
     * @param array<string, mixed> $extra Chaves de topo ao lado de data.
     */
    private function success(JsonResource|array|null $data = null, array $extra = []): JsonResponse
    {
        return $this->respond(true, Response::HTTP_OK, 'Operation completed successfully.', $data, $extra);
    }

    /**
     * Registro inexistente (404).
     */
    private function notFound(string $message): JsonResponse
    {
        return $this->respond(false, Response::HTTP_NOT_FOUND, $message);
    }

    /**
     * Falha inesperada (410), mantida do contrato original da API.
     */
    private function gone(string $message): JsonResponse
    {
        return $this->respond(false, Response::HTTP_GONE, $message);
    }

    /**
     * Envelope {success, code, message, data} usado por todos os endpoints.
     * data é omitido quando vazio.
     *
     * @param JsonResource|array<string, mixed>|null $data
     * @param array<string, mixed> $extra
     */
    private function respond(bool $success, int $code, string $message, JsonResource|array|null $data = null, array $extra = []): JsonResponse
    {
        $payload = ['success' => $success, 'code' => $code, 'message' => $message];

        if ($data instanceof JsonResource) {
            $payload['data'] = $data->jsonSerialize();
        } elseif (!empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload + $extra, $code);
    }

    /**
     * Registra uma falha inesperada com o contexto da requisição.
     */
    private function report(Throwable $exception, Request $request, string $message): void
    {
        ErrorReporter::report($exception, $message, [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
    }
}
