<?php

namespace RiseTechApps\FormRequest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RiseTechApps\FormRequest\Http\Requests\StoreFormRequest;
use RiseTechApps\FormRequest\Http\Requests\UpdateFormRequest;
use RiseTechApps\FormRequest\Http\Resources\FormRequestResource;
use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\Services\FormManager;
use Throwable;

/**
 * API controller exposing CRUD endpoints for dynamic form requests.
 */
class FormController extends Controller
{
    /**
     * Inject the form manager service for data access.
     */
    public function __construct(private readonly FormManager $forms)
    {
    }

    /**
     * List stored forms with optional pagination and registry metadata.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $filters = array_filter([
                'form' => $request->query('form'),
            ]);

            $result = $this->forms->list($perPage, $filters);

            $data = FormRequestResource::collection($result);

            if ($request->boolean('include_configured')) {
                $configured = collect($this->forms->configured())
                    ->map(function (FormDefinition $definition) {
                        return [
                            'form' => $definition->name(),
                            'rules' => $definition->rules(),
                            'messages' => $definition->messages(),
                            'metadata' => $definition->metadata(),
                        ];
                    })
                    ->values();

                $data->additional(['configured' => $configured]);
            }

            logglyInfo()->withRequest($request)->log("Successfully loaded datatable");

            return response()->jsonSuccess($data);
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error loading datatable");

            return response()->jsonGone("Error loading datatable");
        }
    }

    /**
     * Persist a new dynamic form definition.
     */
    public function store(StoreFormRequest $request): JsonResponse
    {
        try {
            $form = $this->forms->create($request->validationData());

            logglyInfo()->withRequest($request)->performedOn($form)->log("Success when registering registration");

            return response()->jsonSuccess(FormRequestResource::make($form));
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->performedOn(self::class)
                ->withTags(['action' => 'store'])->log("Error by registering registration");

            return response()->jsonGone("Error by registering registration");
        }
    }

    /**
     * Display a specific form definition.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));
            $data = FormRequestResource::make($form);

            logglyInfo()->withRequest($request)->performedOn($form)->log("Success when loading the record for viewing");

            return response()->jsonSuccess($data);
        } catch (ModelNotFoundException $exception) {
            logglyError()->exception($exception)->withRequest($request)->log("Record not found for viewing");

            return response()->jsonNotFound("Form request not found");
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error when loading the record to be viewed");

            return response()->jsonGone("Error when loading the record to be viewed");
        }
    }

    /**
     * Update an existing form definition record.
     */
    public function update(UpdateFormRequest $request): JsonResponse
    {

        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));
            $form = $this->forms->update($form, $request->validationData());

            logglyInfo()->withRequest($request)->performedOn($form)->log("Success by updating the registration");

            return response()->jsonSuccess(FormRequestResource::make($form->refresh()));

        } catch (ModelNotFoundException $exception) {
            logglyError()->exception($exception)->withRequest($request)->log("Record not found for update");

            return response()->jsonNotFound("Form request not found");
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error update the registration");

            return response()->jsonGone("Error update the registration");
        }
    }

    /**
     * Delete the specified form definition.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $form = $this->forms->findOrFail((string) $request->route('id'));

            $this->forms->delete($form);

            logglyInfo()->withRequest($request)->performedOn($form)->log("Success by deleting the record");

            return response()->jsonSuccess();

        } catch (ModelNotFoundException $exception) {
            logglyError()->exception($exception)->withRequest($request)->log("Record not found for deletion");

            return response()->jsonNotFound("Form request not found");
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error by deleting the record");

            return response()->jsonGone("Error by deleting the record");
        }
    }
}
