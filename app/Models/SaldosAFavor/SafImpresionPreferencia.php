<?php

namespace App\Models\SaldosAFavor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafImpresionPreferencia extends Model
{
    protected $table = 'saf_impresion_preferencias';

    protected $fillable = [
        'user_id',
        'terminal_key',
        'perfil',
        'copias',
        'sucursal',
        'caja',
    ];

    protected $casts = [
        'copias' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
