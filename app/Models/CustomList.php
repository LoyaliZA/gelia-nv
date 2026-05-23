<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', // Añadido
        'nombre_creador',
        'titulo_lista',
        'descripcion',
        'color',
        'icono_personalizado',
        'archivos_requeridos',
        'columnas_exportar',
        'nombre_archivo_salida',
        'active',
        'solo_con_existencia',
        'filtro_relojes',
        'pct_venta_especial',
    ];

    protected $casts = [
        'archivos_requeridos' => 'array',
        'columnas_exportar' => 'array',
        'active' => 'boolean',
        'solo_con_existencia' => 'boolean',
        'filtro_relojes' => 'boolean',
        'pct_venta_especial' => 'decimal:2', 
    ];

    // Relación: El creador de la lista
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación: Usuarios con los que se compartió
    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'custom_list_user', 'custom_list_id', 'user_id');
    }
}