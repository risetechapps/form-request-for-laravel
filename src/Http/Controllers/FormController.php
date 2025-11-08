<?php

namespace RiseTechApps\FormRequest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RiseTechApps\FormRequest\Http\Requests\StoreFormRequest;
use RiseTechApps\FormRequest\Http\Requests\UpdateFormRequest;
use RiseTechApps\FormRequest\Http\Resources\FormRequestResource;
use RiseTechApps\FormRequest\Models\FormRequest;
use Throwable;

class FormController extends Controller
{
    public function __construct(private readonly FormRequest $forms)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = $this->forms->newQuery();

            if ($request->filled('form')) {
                $query->where('form', $request->query('form'));
            }

            $perPage = (int) $request->query('per_page', 15);

            $data = $perPage <= 0
                ? FormRequestResource::collection($query->get())
                : FormRequestResource::collection($query->paginate(max($perPage, 1)));

            logglyInfo()->withRequest($request)->log("Successfully loaded datatable");

            return response()->jsonSuccess($data);
        } catch (Throwable $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error loading datatable");

            return response()->jsonGone("Error loading datatable");
        }
    }

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

    public function show(Request $request): JsonResponse
    {
        try {
            $form = $this->forms->newQuery()->findOrFail($request->route('id'));
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

    public function update(UpdateFormRequest $request): JsonResponse
    {

        try {
            $form = $this->forms->newQuery()->findOrFail($request->route('id'));
            $form->fill($request->validationData());
            $form->save();

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

    public function destroy(Request $request): JsonResponse
    {
        try {
            $form = $this->forms->newQuery()->findOrFail($request->route('id'));

            $form->delete();

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
