<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafCuenta extends Model
{
    protected $table = 'saf_cuentas';

    protected $fillable = [
        'cliente_id',
        'moneda',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creditos(): HasMany
    {
        return $this->hasMany(SafCredito::class, 'saf_cuenta_id');
    }
}
