<?php

namespace RiseTechApps\FormRequest\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use RiseTechApps\HasUuid\Traits\HasUuid\HasUuid;


class FormRequest extends Model
{
    use HasFactory, Notifiable, HasUuid;

    protected $fillable = [
      'form',
      'rules',
      'description',
      'data',
    ];

    protected $hidden = [
        'id',
        'data'
    ];
    protected $casts = [
        'rules' => 'array',
        'data' => 'array',
    ];
}
