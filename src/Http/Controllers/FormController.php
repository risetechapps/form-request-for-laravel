<?php

namespace RiseTechApps\FormRequest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RiseTechApps\FormRequest\Http\Requests\StoreFormRequest;
use RiseTechApps\FormRequest\Http\Requests\UpdateFormRequest;
use RiseTechApps\FormRequest\Http\Resources\FormRequestResource;
use RiseTechApps\FormRequest\Models\FormRequest;
use RiseTechApps\FormRequest\Rules;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class FormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {

            $model = new FormRequest();

            $data = FormRequestResource::collection($model->all());

            logglyInfo()->withRequest($request)->log("Successfully loaded datatable");

            return response()->jsonSuccess($data);
        } catch (\Exception $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error loading datatable");

            return response()->jsonGone("Error loading datatable");
        }
    }

    public function store(StoreFormRequest $request): JsonResponse
    {
        try {
            $model = new FormRequest();
            $model->create($request->validationData());

            logglyInfo()->withRequest($request)->performedOn($model)->log("Success when registering registration");

            return response()->jsonSuccess();
        } catch (\Exception $exception) {

            logglyError()->exception($exception)->withRequest($request)->performedOn(self::class)
                ->withTags(['action' => 'store'])->log("Error by registering registration");

            return response()->jsonGone("Error by registering registration");
        }
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $model = new FormRequest();
            $data = FormRequestResource::make( $model->find($request->id));

            logglyInfo()->withRequest($request)->performedOn($model)->log("Success when loading the record for viewing");

            return response()->jsonSuccess($data);
        } catch (\Exception $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error when loading the record to be viewed");

            return response()->jsonGone("Error when loading the record to be viewed");
        }
    }

    public function update(UpdateFormRequest $request): JsonResponse
    {

        try {
            $model = new FormRequest();
            $model = $model->find($request->id);
            $update = $model->update($request->validationData());

            logglyInfo()->withRequest($request)->performedOn($model)->log("Success by updating the registration");

            return response()->jsonSuccess($update);

        } catch (\Exception $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error update the registration");

            return response()->jsonGone("Error update the registration");
        }
    }

    public function delete(Request $request): JsonResponse
    {
        try {

            $model = new FormRequest();
            $data = $model->find($request->id);

            if($data->delete()){
                logglyInfo()->withRequest($request)->performedOn($model)->log("Success by deleting the record");

                return response()->jsonSuccess();

            }else{
                logglyError()->withRequest($request)->log("Error by deleting the record");

                return response()->jsonGone("Error by deleting the record");
            }

        } catch (\Exception $exception) {

            logglyError()->exception($exception)->withRequest($request)->log("Error by deleting the record");

            return response()->jsonGone("Error by deleting the record");
        }
    }
}
