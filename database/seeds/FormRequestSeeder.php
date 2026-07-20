<?php

namespace RiseTechApps\FormRequest\Database\Seeds;

use Illuminate\Database\Seeder;
use RiseTechApps\FormRequest\Models\FormRequest;
use RiseTechApps\FormRequest\RulesRegistry;

class FormRequestSeeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RulesRegistry::class);

        foreach ($rules->allRules() as $key => $value) {

            // 'form_request' é o schema interno do CRUD do próprio pacote,
            // resolvido via configuração. Não deve ser persistido como form editável.
            if ($key === 'form_request') {
                continue;
            }

            if(!FormRequest::where('form' , $key)->exists()){

                FormRequest::create([
                    'form' => $key,
                    'rules' => $value,
                ]);
            }
        }
    }
}
