<?php

namespace App\Support\Solicitudes;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda tipificada del listado de solicitudes: un campo por consulta.
 */
class BusquedaSolicitudTag
{
    public const CAMPO_GENERAL = 'general';

    public const CAMPO_FOLIO = 'folio';

    public const CAMPO_NUMERO_CLIENTE = 'numero_cliente';

    public const CAMPO_NOMBRE_CLIENTE = 'nombre_cliente';

    public const CAMPO_RESPONSABLE = 'responsable';

    /** @return list<string> */
    public static function valoresPermitidos(): array
    {
        return [
            self::CAMPO_GENERAL,
            self::CAMPO_FOLIO,
            self::CAMPO_NUMERO_CLIENTE,
            self::CAMPO_NOMBRE_CLIENTE,
            self::CAMPO_RESPONSABLE,
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
        $like = '%'.addcslashes($termino, '%_\\').'%';

        match ($campo) {
            self::CAMPO_FOLIO => self::aplicarFolio($query, $termino),
            self::CAMPO_NUMERO_CLIENTE => $query->whereHas('cliente', function (Builder $cliente) use ($like) {
                $cliente->where('numero_cliente', 'like', $like);
            }),
            self::CAMPO_NOMBRE_CLIENTE => $query->whereHas('cliente', function (Builder $cliente) use ($like) {
                $cliente->where('nombre', 'like', $like);
            }),
            self::CAMPO_RESPONSABLE => $query->whereHas('vendedor', function (Builder $responsable) use ($like) {
                $responsable->where('name', 'like', $like);
            }),
            default => self::aplicarGeneral($query, $termino, $like),
        };
    }

    public static function idDesdeFolio(string $termino): ?int
    {
        $normalizado = trim(preg_replace('/^fol[.\-\s]*/i', '', trim($termino)) ?? '');

        return $normalizado !== '' && ctype_digit($normalizado)
            ? (int) $normalizado
            : null;
    }

    private static function aplicarFolio(Builder $query, string $termino): void
    {
        $id = self::idDesdeFolio($termino);
        if ($id !== null) {
            $query->where('id', $id);

            return;
        }

        $query->where('id', 'like', '%'.addcslashes($termino, '%_\\').'%');
    }

    private static function aplicarGeneral(Builder $query, string $termino, string $like): void
    {
        $id = self::idDesdeFolio($termino);
        $query->where(function (Builder $q) use ($termino, $like, $id) {
            if ($id !== null) {
                $q->where('id', $id);
            } else {
                $q->where('id', 'like', '%'.addcslashes($termino, '%_\\').'%');
            }
            $q->orWhereHas('cliente', function (Builder $cliente) use ($like) {
                $cliente->where('nombre', 'like', $like)
                    ->orWhere('numero_cliente', 'like', $like);
            });
        });
    }
}
