<?php

namespace App\Models\Escalonamiento;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVentaRevision extends Model
{
    protected $table = 'documentos_venta_revisiones';

    protected $fillable = [
        'documento_venta_id',
        'total_anterior',
        'total_nuevo',
        'efecto_anterior',
        'efecto_nuevo',
        'user_id',
    ];

    protected $casts = [
        'total_anterior' => 'decimal:2',
        'total_nuevo' => 'decimal:2',
        'efecto_anterior' => 'decimal:2',
        'efecto_nuevo' => 'decimal:2',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
