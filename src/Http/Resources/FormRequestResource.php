<?php

namespace RiseTechApps\FormRequest\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->form,
            'rule' => $this->rules,
            'rules' => $this->rules,
            'messages' => $this->messages ?? [],
            'metadata' => $this->data ?? [],
            'description' => $this->description
        ];
    }
}
