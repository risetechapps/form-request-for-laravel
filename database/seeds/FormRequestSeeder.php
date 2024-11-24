<?php

namespace RiseTechApps\FormRequest\Database\Seeds;

use Illuminate\Database\Seeder;
use RiseTechApps\FormRequest\Models\FormRequest;
use RiseTechApps\FormRequest\Rules;

class FormRequestSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Rules::defaultRules() as $key => $value) {

            if(!FormRequest::where('form' , $key)->exists()){

                FormRequest::create([
                    'form' => $key,
                    'rules' => $value,
                ]);
            }
        }
    }
}
