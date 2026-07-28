<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceptorFiscal extends Model
{
    protected $table = 'receptores_fiscales';

    protected $fillable = [
        'codigo_interno',
        'rfc',
        'codigo_postal',
        'regimen_fiscal',
        'correo_electronico',
        'uso_factura',
        'nombre_razon_social',
        'telefono',
        'activo',
        'notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_receptor_fiscal')
            ->withTimestamps();
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudFactura::class, 'receptor_fiscal_id');
    }

    /** @return array<string, string|null> */
    public function aDatosFiscales(): array
    {
        return [
            'rfc' => $this->rfc,
            'codigo_postal' => $this->codigo_postal,
            'regimen_fiscal' => $this->regimen_fiscal,
            'correo_electronico' => $this->correo_electronico,
            'uso_factura' => $this->uso_factura,
            'nombre_razon_social' => $this->nombre_razon_social,
            'telefono' => $this->telefono,
        ];
    }
}
