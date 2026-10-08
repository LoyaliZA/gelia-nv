<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use Illuminate\Database\Eloquent\Builder;

class SituacionOperativaDelegadoPedidoBma
{
    public const SIN_GUIA = 'sin_guia';

    public const CON_GUIA = 'con_guia';

    public const ERROR_GUIA = 'error_guia';

    public const RETRASO = 'retraso';

    public const RESGUARDO = 'resguardo';

    /** @var list<string> */
    private const FASES_SIN_ETIQUETA_RESGUARDO = [
        'BORRADOR',
        'PESAJE_PENDIENTE',
        'PESAJE_RESPONDIDO',
        'RECHAZADO_VENDEDORA',
    ];

    /** @var list<string> */
    private const FASES_FIN_RETRASO_RECOLECCION = [
        'ENVIADO',
        'CANCELADO',
        'ENTREGADO',
    ];

    /** @return list<string> */
    public static function valoresPermitidos(): array
    {
        return [
            self::SIN_GUIA,
            self::CON_GUIA,
            self::ERROR_GUIA,
            self::RETRASO,
            self::RESGUARDO,
        ];
    }

    public static function aplicar(Builder $query, string $situacion): void
    {
        match ($situacion) {
            self::SIN_GUIA => self::scopeSinNumeroRastreo($query),
            self::CON_GUIA => self::scopeConNumeroRastreo($query),
            self::ERROR_GUIA => self::scopeErrorGuia($query),
            self::RETRASO => self::scopeRetraso($query),
            self::RESGUARDO => self::scopeResguardoVisible($query),
            default => null,
        };
    }

    public static function scopeSinNumeroRastreo(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereNull('numero_rastreo')
                ->orWhere('numero_rastreo', '');
        });
    }

    public static function scopeConNumeroRastreo(Builder $query): void
    {
        $query->whereNotNull('numero_rastreo')
            ->where('numero_rastreo', '!=', '');
    }

    public static function scopeErrorGuia(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereJsonContains('campos_incorrectos', 'numero_rastreo')
                ->orWhereJsonContains('campos_incorrectos', 'guia_pdf');
        });
    }

    public static function scopeRetraso(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('guia_retraso', true)
                ->orWhere(function (Builder $q2) {
                    $q2->whereNotNull('retraso_empaque_alertado_at')
                        ->whereNull('empacado_at');
                })
                ->orWhere(function (Builder $q3) {
                    $q3->whereNotNull('retraso_recoleccion_alertado_at')
                        ->whereHas('estatus', function (Builder $e) {
                            $e->whereNotIn('fase_ciclo', self::FASES_FIN_RETRASO_RECOLECCION);
                        });
                });
        });
    }

    public static function scopeResguardoVisible(Builder $query): void
    {
        $query->where('es_resguardo', true)
            ->whereHas('estatus', function (Builder $e) {
                $e->whereNotNull('fase_ciclo')
                    ->whereNotIn('fase_ciclo', self::FASES_SIN_ETIQUETA_RESGUARDO);
            });
    }
}
