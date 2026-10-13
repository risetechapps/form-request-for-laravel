<?php

namespace RiseTechApps\FormRequest\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use RiseTechApps\HasUuid\Traits\HasUuid;

/**
 * Modelo Eloquent que representa definições de formulários dinâmicos persistidos.
 *
 * @property string $id
 * @property string|null $form
 * @property array<string, mixed>|null $rules
 * @property array<string, string>|null $messages
 * @property string|null $description
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FormRequest extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, Notifiable, HasUuid;

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
