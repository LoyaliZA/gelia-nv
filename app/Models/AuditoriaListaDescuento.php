<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaListaDescuento extends Model
{
    // Nombre explícito de la tabla de auditoría
    protected $table = 'auditorias_listas_descuento';

    // Campos protegidos para asignación masiva de auditoría
    protected $fillable = [
        'lista_id',
        'usuario_id',
        'monto_anterior',
        'monto_nuevo',
        'origen_cambio'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones del Modelo
    |--------------------------------------------------------------------------
    */

    public function lista(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}