<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;

class MatrizRequisitosPreparacion extends Model
{
    protected $table = 'control_pedidos_matriz_requisitos_preparacion';

    protected $fillable = [
        'codigo_modalidad',
        'departamento_codigo',
        'almacen_origen_id',
        'destino_codigo',
        'tipo_integracion',
        'requisitos_json',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'requisitos_json' => 'array',
            'activo' => 'boolean',
            'orden' => 'integer',
            'almacen_origen_id' => 'integer',
        ];
    }
}
