<?php

namespace RiseTechApps\FormRequest\Contracts;

interface RulesContract
{
    public static function Rules(): array;

    public static function Validator(): array;
}
