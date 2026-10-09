<?php

namespace App\Support\ControlPedidos;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda tipificada en la bandeja Delegado (Actualizar guías).
 */
class BusquedaDelegadoPedidoBma
{
    public const CAMPO_GENERAL = 'general';

    public const CAMPO_FOLIO_REMISION = 'folio_remision';

    public const CAMPO_FOLIO = 'folio';

    public const CAMPO_GUIA = 'guia';

    public const CAMPO_CLIENTE = 'cliente';

    /** @return list<string> */
    public static function valoresPermitidos(): array
    {
        return [
            self::CAMPO_GENERAL,
            self::CAMPO_FOLIO_REMISION,
            self::CAMPO_FOLIO,
            self::CAMPO_GUIA,
            self::CAMPO_CLIENTE,
        ];
    }

    public static function normalizar(?string $campo): string
    {
        $campo = strtolower(trim((string) $campo));

        return in_array($campo, self::valoresPermitidos(), true)
            ? $campo
            : self::CAMPO_GENERAL;
    }

    public static function aplicar(Builder $query, string $termino, ?string $campo = null): void
    {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $campo = self::normalizar($campo);

        $query->where(function (Builder $q) use ($termino, $campo) {
            match ($campo) {
                self::CAMPO_FOLIO_REMISION => $q->where('folio_remision', 'like', "%{$termino}%"),
                self::CAMPO_FOLIO => $q->where(function (Builder $f) use ($termino) {
                    $f->where('folio', 'like', "%{$termino}%")
                        ->orWhereHas('principal', fn (Builder $p) => $p->where('folio', 'like', "%{$termino}%"));
                }),
                self::CAMPO_GUIA => $q->where('numero_rastreo', 'like', "%{$termino}%"),
                self::CAMPO_CLIENTE => $q->whereHas('cliente', function (Builder $c) use ($termino) {
                    $c->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('numero_cliente', 'like', "%{$termino}%");
                }),
                default => self::aplicarGeneral($q, $termino),
            };
        });
    }

    private static function aplicarGeneral(Builder $q, string $termino): void
    {
        $q->where('folio', 'like', "%{$termino}%")
            ->orWhere('folio_remision', 'like', "%{$termino}%")
            ->orWhere('numero_rastreo', 'like', "%{$termino}%")
            ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                $c->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('numero_cliente', 'like', "%{$termino}%");
            });

        if (ctype_digit($termino)) {
            $q->orWhere('id', (int) $termino);
        }
    }
}
