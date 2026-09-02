<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;

class EstimarExportacionResguardoPdvService
{
    public function __construct(
        private readonly ConsultaBandejasResguardoPdvService $bandejas,
        private readonly ConsultaAuditoriaResguardoPdvService $auditoria,
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function contarRegistros(User $usuario, array $filtros): int
    {
        $tipo = (string) ($filtros['tipo'] ?? ResguardoPdvExportacionTipo::LISTADO);

        if ($tipo === ResguardoPdvExportacionTipo::AUDITORIA) {
            $resguardo = $this->resolverResguardoAuditoria($usuario, $filtros);

            return $this->auditoria->obtener($usuario, $resguardo, $this->filtrosAuditoria($filtros))['total'];
        }

        return $this->bandejas->contarParaExportacion($usuario, $filtros);
    }

    public function esPesado(int $registros): bool
    {
        $umbral = (int) config('punto_venta.resguardos.exportacion.pesado_registros', 200);

        return $registros > $umbral;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function titulo(User $usuario, array $filtros): string
    {
        $tipo = (string) ($filtros['tipo'] ?? ResguardoPdvExportacionTipo::LISTADO);

        if ($tipo === ResguardoPdvExportacionTipo::AUDITORIA) {
            $resguardo = $this->resolverResguardoAuditoria($usuario, $filtros);
            $folio = $resguardo->snapshot_folio ?: ('#'.$resguardo->id);

            return "Auditoría resguardo {$folio}";
        }

        $bandeja = (string) ($filtros['bandeja'] ?? 'por_recibir');
        $etiqueta = EtiquetasResguardoPdv::bandejas()[$bandeja] ?? $bandeja;
        $sucursalId = $this->alcance->sucursalActivaId($usuario);

        return "Resguardos — {$etiqueta}".($sucursalId ? " — sucursal {$sucursalId}" : '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function resolverResguardoAuditoria(User $usuario, array $filtros): ResguardoPdv
    {
        $this->alcance->asegurarConsultaPiso($usuario, PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR);

        return ResguardoPdv::query()->findOrFail((int) ($filtros['resguardo_id'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosAuditoria(array $filtros): array
    {
        return array_filter([
            'tipo_evento' => $filtros['tipo_evento'] ?? null,
            'categoria' => $filtros['categoria'] ?? null,
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    }
}
