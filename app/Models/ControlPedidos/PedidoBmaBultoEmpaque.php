<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaBultoEmpaque extends Model
{
    protected $table = 'pedido_bma_bultos_empaque';

    protected $fillable = [
        'pedido_bma_id',
        'numero',
        'empacado_por_id',
        'empacado_at',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'empacado_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function empacadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacado_por_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'relacion_id')
            ->where('relacion_tipo', PedidoBmaDocumento::RELACION_BULTO_EMPAQUE)
            ->orderBy('orden');
    }
}
