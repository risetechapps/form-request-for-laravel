<?php

namespace RiseTechApps\FormRequest\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use RiseTechApps\HasUuid\Traits\HasUuid;

/**
 * Modelo Eloquent que representa definições de formulários dinâmicos persistidos.
 */
class FormRequest extends Model
{
    use HasFactory, Notifiable, HasUuid;//, HasLoggly;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form',
        'rules',
        'messages',
        'description',
        'data',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'data',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rules' => 'array',
        'messages' => 'array',
        'data' => 'array',
    ];
}
