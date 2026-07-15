<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListadoGenerado extends Model
{
    use HasFactory;

    protected $table = 'listado_generados';

    protected $fillable = [
        'user_id',
        'tipo_lista',
        'custom_list_id',
        'nombre_archivo',
        'ruta_fisica',
        'tamano_kb',
        'enviado_correo',
    ];

    protected $casts = [
        'enviado_correo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customList()
    {
        return $this->belongsTo(CustomList::class);
    }
}
