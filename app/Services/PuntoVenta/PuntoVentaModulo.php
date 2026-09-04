<?php

namespace App\Services\PuntoVenta;

use App\Models\ConfiguracionSistema;

final class PuntoVentaModulo
{
    public const CLAVE_FLAG = 'punto_venta.habilitado';

    public const PERMISO_ACCEDER = 'punto_venta.acceder';

    public const PERMISO_RESGUARDOS_VER = 'pdv.resguardos.ver';

    public const PERMISO_RESGUARDOS_RECIBIR = 'pdv.resguardos.recibir';

    public const PERMISO_RESGUARDOS_INCIDENCIA_FOLIO = 'pdv.resguardos.incidencia_folio';

    public const PERMISO_RESGUARDOS_INCIDENCIA_DANO = 'pdv.resguardos.incidencia_dano';

    public const PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE = 'pdv.resguardos.incidencia_faltante';

    public const PERMISO_RESGUARDOS_ENTREGAR = 'pdv.resguardos.entregar';

    public const PERMISO_RESGUARDOS_VER_VENCIDOS = 'pdv.resguardos.ver_vencidos';

    public const PERMISO_RESGUARDOS_REPONER_VENCIDO = 'pdv.resguardos.reponer_vencido';

    public const PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA = 'pdv.resguardos.autorizar_entrega_incidencia';

    public const PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION = 'pdv.resguardos.confirmar_devolucion';

    public const PERMISO_RESGUARDOS_CORREGIR = 'pdv.resguardos.corregir';

    public const PERMISO_REPORTES_EXPORTAR = 'pdv.reportes.exportar';

    public const PERMISO_TURNOS_VER = 'pdv.turnos.ver';

    public const PERMISO_TURNOS_ALTA = 'pdv.turnos.alta';

    public const PERMISO_TURNOS_MARCAR_PRIORIDAD = 'pdv.turnos.marcar_prioridad';

    public const PERMISO_TURNOS_BAJA_COLA = 'pdv.turnos.baja_cola';

    public const PERMISO_TURNOS_CERRAR_ATENCION = 'pdv.turnos.cerrar_atencion';

    public const PERMISO_TURNOS_TRANSFERIR = 'pdv.turnos.transferir';

    /**
     * @return list<string>
     */
    public static function permisosIniciales(): array
    {
        return [
            self::PERMISO_ACCEDER,
            self::PERMISO_RESGUARDOS_VER,
            self::PERMISO_RESGUARDOS_RECIBIR,
            self::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            self::PERMISO_RESGUARDOS_INCIDENCIA_DANO,
            self::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE,
            self::PERMISO_RESGUARDOS_ENTREGAR,
            self::PERMISO_RESGUARDOS_VER_VENCIDOS,
            self::PERMISO_RESGUARDOS_REPONER_VENCIDO,
            self::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA,
            self::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION,
            self::PERMISO_RESGUARDOS_CORREGIR,
            self::PERMISO_TURNOS_VER,
            self::PERMISO_TURNOS_ALTA,
            self::PERMISO_TURNOS_MARCAR_PRIORIDAD,
            self::PERMISO_TURNOS_BAJA_COLA,
            self::PERMISO_TURNOS_CERRAR_ATENCION,
            self::PERMISO_TURNOS_TRANSFERIR,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
            self::PERMISO_REPORTES_EXPORTAR,
        ];
    }

    public function habilitado(): bool
    {
        $row = ConfiguracionSistema::query()->where('clave', self::CLAVE_FLAG)->first();
        if (! $row) {
            return false;
        }

        $valor = $row->valor;
        if (is_bool($valor)) {
            return $valor;
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
