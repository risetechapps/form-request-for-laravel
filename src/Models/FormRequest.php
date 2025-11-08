<?php

namespace RiseTechApps\FormRequest\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use RiseTechApps\HasUuid\Traits\HasUuid\HasUuid;
use RiseTechApps\Monitoring\Traits\HasLoggly\HasLoggly;

class FormRequest extends Model
{
    use HasFactory, Notifiable, HasUuid, HasLoggly;

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
