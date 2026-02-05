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

            if(!FormRequest::where('form' , $key)->exists()){

                FormRequest::create([
                    'form' => $key,
                    'rules' => $value,
                ]);
            }
        }
    }
}
