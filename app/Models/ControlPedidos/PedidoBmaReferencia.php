<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaReferencia extends Model
{
    public const TIPO_COTIZACION = 'COTIZACION';

    public const TIPO_PEDIDO = 'PEDIDO';

    public const TIPO_REMISION = 'REMISION';

    public const TIPO_OTRO = 'OTRO';

    public const TIPO_SIN_CLASIFICAR = 'SIN_CLASIFICAR';

    public const TIPOS = [
        self::TIPO_COTIZACION,
        self::TIPO_PEDIDO,
        self::TIPO_REMISION,
        self::TIPO_OTRO,
        self::TIPO_SIN_CLASIFICAR,
    ];

    public const ETIQUETAS = [
        self::TIPO_COTIZACION => 'Cotización',
        self::TIPO_PEDIDO => 'Pedido',
        self::TIPO_REMISION => 'Remisión',
        self::TIPO_OTRO => 'Otro',
        self::TIPO_SIN_CLASIFICAR => 'Sin clasificar',
    ];

    protected $table = 'pedido_bma_referencias';

    protected $fillable = [
        'pedido_bma_id',
        'tipo',
        'folio',
        'registrado_por_id',
        'registrado_at',
        'documento_id',
        'observaciones',
        'vigente',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
            'vigente' => 'boolean',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaDocumento::class, 'documento_id');
    }

    public static function registrar(
        PedidoBma $pedido,
        string $tipo,
        string $folio,
        ?int $usuarioId = null,
        ?int $documentoId = null,
        ?string $observaciones = null,
    ): self {
        static::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('tipo', $tipo)
            ->where('vigente', true)
            ->update(['vigente' => false]);

        return static::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => $tipo,
            'folio' => $folio,
            'registrado_por_id' => $usuarioId,
            'registrado_at' => now(),
            'documento_id' => $documentoId,
            'observaciones' => $observaciones,
            'vigente' => true,
        ]);
    }

    /**
     * @return array{folio: string, etiqueta: string, tipo: ?string}
     */
    public static function visible(PedidoBma $pedido): array
    {
        $referencia = $pedido->relationLoaded('referencias')
            ? $pedido->referencias->where('vigente', true)->sortByDesc('id')->first()
            : $pedido->referencias()->where('vigente', true)->orderByDesc('id')->first();

        if ($referencia) {
            $nombre = self::ETIQUETAS[$referencia->tipo] ?? null;
            $etiqueta = $nombre && $referencia->tipo !== self::TIPO_SIN_CLASIFICAR
                ? 'Folio · '.$nombre
                : 'Folio';

            return [
                'folio' => (string) $referencia->folio,
                'etiqueta' => $etiqueta,
                'tipo' => $referencia->tipo,
            ];
        }

        $documental = trim((string) ($pedido->folio_remision ?? ''));

        return [
            'folio' => $documental !== '' ? $documental : (string) ($pedido->folio ?? ''),
            'etiqueta' => 'Folio',
            'tipo' => null,
        ];
    }
}
