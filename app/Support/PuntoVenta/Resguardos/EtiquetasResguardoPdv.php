<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;

final class EtiquetasResguardoPdv
{
    /**
     * @return array<string, string>
     */
    public static function bandejas(): array
    {
        return [
            BandejaResguardoPdv::POR_RECIBIR => 'Por recibir',
            BandejaResguardoPdv::EN_CUSTODIA => 'En custodia',
            BandejaResguardoPdv::INCIDENCIAS => 'Incidencias',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return [
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION => 'Pendiente de recepción',
            ResguardoPdv::ESTADO_EN_CUSTODIA => 'En custodia',
            ResguardoPdv::ESTADO_ENTREGADO => 'Entregado',
            ResguardoPdv::ESTADO_DEVUELTO => 'Devuelto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function antiguedades(): array
    {
        return [
            AntiguedadOperativaResguardoPdv::REZAGADO => 'Rezagado',
            AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER => 'Próximo a vencer',
            AntiguedadOperativaResguardoPdv::VENCIDO => 'Vencido',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function eventos(): array
    {
        return [
            ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA => 'Recepción esperada registrada',
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA => 'Recepción completa',
            ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL => 'Recepción parcial',
            ResguardoPdvEvento::TIPO_INCIDENCIA_FOLIO_NO_ENCONTRADO => 'Incidencia: folio no encontrado',
            ResguardoPdvEvento::TIPO_INCIDENCIA_DANO => 'Incidencia: daño',
            ResguardoPdvEvento::TIPO_INCIDENCIA_FALTANTE => 'Incidencia: faltante',
            ResguardoPdvEvento::TIPO_INCIDENCIA_ENTREGA_AUTORIZADA => 'Entrega autorizada con incidencia',
            ResguardoPdvEvento::TIPO_ENTREGA_TITULAR => 'Entrega al titular',
            ResguardoPdvEvento::TIPO_ENTREGA_TERCERO => 'Entrega a tercero',
            ResguardoPdvEvento::TIPO_ENTREGA_MULTIPLE => 'Entrega múltiple',
            ResguardoPdvEvento::TIPO_ENTREGA_PARCIAL => 'Entrega parcial',
            ResguardoPdvEvento::TIPO_MARCADO_VENCIDO => 'Marcado como vencido',
            ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO => 'Vencido repuesto',
            ResguardoPdvEvento::TIPO_MARCADO_REZAGADO => 'Marcado como rezagado',
            ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA => 'Cancelación recibida',
            ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA => 'Devolución confirmada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function relacionesEntrega(): array
    {
        return [
            ResguardoPdvEntrega::RELACION_TITULAR => 'Titular del pedido',
            ResguardoPdvEntrega::RELACION_TERCERO => 'Tercero autorizado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposIncidencia(): array
    {
        return [
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => 'Folio no encontrado',
            ResguardoPdvIncidencia::TIPO_DANO => 'Daño',
            ResguardoPdvIncidencia::TIPO_FALTANTE => 'Faltante',
        ];
    }

    public static function etiquetaEvento(string $tipo): string
    {
        return self::eventos()[$tipo] ?? str_replace(['resguardo.', '_'], ['', ' '], $tipo);
    }

    public static function etiquetaEstado(string $estado): string
    {
        return self::estados()[$estado] ?? $estado;
    }

    /**
     * @return array<string, string>
     */
    public static function tiposBulto(): array
    {
        return [
            ResguardoPdvBulto::TIPO_CAJA => 'Caja',
            ResguardoPdvBulto::TIPO_BOLSA => 'Bolsa',
        ];
    }

    /**
     * Valores de auditoría en snapshot; no controlan transiciones.
     *
     * @return array<string, string>
     */
    public static function condicionesBulto(): array
    {
        return [
            'bueno' => 'Bueno',
            'danado' => 'Dañado',
            'humedad' => 'Humedad',
            'incompleto' => 'Incompleto',
        ];
    }
}
