<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobranzaBitacora extends Model
{
    use BelongsToUsuario, HasFactory;

    protected $table = 'cobranza_bitacoras';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'tipo_evento',
        'monto_anterior',
        'monto_nuevo',
        'descripcion',
        'es_alerta',
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'es_alerta' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsToUsuario('usuario_id');
    }
}
