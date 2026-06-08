<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobranzaConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'cobranza_configuraciones';

    protected $fillable = [
        'llave',
        'valor',
    ];

    protected $casts = [
        'valor' => 'array',
    ];
}
