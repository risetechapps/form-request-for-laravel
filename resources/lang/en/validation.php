<?php

return [

    'form_request' => [
        "form" => [
            "required" => "It is necessary that the name form",
            "min" => "The form name must have at least 3 characters",
            "unique" => "Necessary to select whether registration is a company or individual",
        ],
        "rules" => [
            "required" => "Necessary to enter the rules",
            "array" => "You need to type the rules in the correct format",
        ]
    ]
];
