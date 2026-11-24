<?php

namespace App\Support\ControlPedidos;

use App\Models\User;

/**
 * Las consultas municipales pendientes de Tienda son pedidos de la bandeja Preparación Tienda aunque
 * aún no tengan tarea de preparación. Se proyectan como filas con la forma de una tarea; el almacén
 * consultado indica la sucursal que prepara.
 */
final class FilasConsultaMunicipalTienda
{
    public const TIPO = 'consulta_municipal';

    public const TIPO_EMPAQUE = 'empaque_municipal';

    private const TABS = ['TODOS', 'PENDIENTES'];

    /** @return array<int, array<string, mixed>> */
    public static function filas(User $usuario, array $filtros): array
    {
        $tab = strtoupper((string) ($filtros['tab'] ?? 'TODOS'));
        if (! in_array($tab, self::TABS, true)) {
            return [];
        }
        // Filtros propios de una tarea de preparación: la consulta aún no tiene modalidad, estado ni origen.
        foreach (['modalidad', 'estado', 'origen_solicitud', 'prioridad_md', 'tipo'] as $filtro) {
            if (isset($filtros[$filtro]) && $filtros[$filtro] !== '') {
                return [];
            }
        }
        $q = mb_strtolower(trim((string) ($filtros['q'] ?? '')));
        $almacenId = (int) ($filtros['almacen_id'] ?? 0);

        $filas = [];
        foreach (AlcanceConsultaMunicipal::pendientesTienda($usuario, 200) as $consulta) {
            if ($almacenId && (int) $consulta['almacen_id'] !== $almacenId) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($consulta['folio'].' '.$consulta['folio_visible'].' '.$consulta['folio_remision'].' '.$consulta['cliente']), $q)) {
                continue;
            }
            $filas[] = [
                'id' => 'consulta-'.$consulta['id'],
                'tipo_fila' => self::TIPO,
                'estado' => 'PENDIENTE',
                'estado_label' => 'Consulta pendiente',
                'solicitada_at' => $consulta['enviada_at'],
                'fecha_limite' => null,
                'piezas_solicitadas' => null,
                'almacen' => ['id' => $consulta['almacen_id'], 'nombre' => $consulta['almacen']],
                'modalidad' => ['codigo' => 'ENVIO_MUNICIPIO', 'nombre' => 'Envío municipal'],
                'terminal_destino' => $consulta['terminal_destino'],
                'pedido' => [
                    'id' => $consulta['id'],
                    'folio' => $consulta['folio'],
                    'folio_visible' => $consulta['folio_visible'],
                    'folio_etiqueta' => $consulta['folio_etiqueta'],
                    'folio_remision' => $consulta['folio_remision'],
                    'cliente_nombre' => $consulta['cliente'],
                ],
            ];
        }

        foreach (AlcanceConsultaMunicipal::porEmpacarTienda($usuario, 200) as $pedido) {
            if ($almacenId && (int) $pedido['almacen_id'] !== $almacenId) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($pedido['folio'].' '.$pedido['folio_visible'].' '.$pedido['folio_remision'].' '.$pedido['cliente']), $q)) {
                continue;
            }
            $filas[] = [
                'id' => 'empaque-'.$pedido['id'],
                'tipo_fila' => self::TIPO_EMPAQUE,
                'estado' => 'PENDIENTE',
                'estado_label' => 'Aprobado: por empacar',
                'solicitada_at' => $pedido['aprobado_at'],
                'fecha_limite' => null,
                'piezas_solicitadas' => null,
                'almacen' => ['id' => $pedido['almacen_id'], 'nombre' => $pedido['almacen']],
                'modalidad' => ['codigo' => 'ENVIO_MUNICIPIO', 'nombre' => 'Envío municipal'],
                'terminal_destino' => $pedido['terminal_destino'],
                'pedido' => [
                    'id' => $pedido['id'],
                    'folio' => $pedido['folio'],
                    'folio_visible' => $pedido['folio_visible'],
                    'folio_etiqueta' => $pedido['folio_etiqueta'],
                    'folio_remision' => $pedido['folio_remision'],
                    'cliente_nombre' => $pedido['cliente'],
                ],
            ];
        }

        return $filas;
    }

    /** @param array<string, mixed> $pagina */
    public static function mezclar(array $pagina, User $usuario, array $filtros): array
    {
        $filas = self::filas($usuario, $filtros);
        if ($filas === [] || (int) ($pagina['current_page'] ?? 1) > 1) {
            return $pagina;
        }
        $pagina['data'] = [...$filas, ...($pagina['data'] ?? [])];
        $pagina['total'] = (int) ($pagina['total'] ?? 0) + count($filas);

        return $pagina;
    }

    /** @param array<string, mixed> $metricas */
    public static function sumarMetricas(array $metricas, User $usuario, array $filtros): array
    {
        // Las métricas cuentan sobre todas las pestañas: se evalúa la consulta como visible en TODOS.
        $n = count(self::filas($usuario, array_merge($filtros, ['tab' => 'TODOS'])));
        $metricas['todos'] = (int) ($metricas['todos'] ?? 0) + $n;
        $metricas['pendientes'] = (int) ($metricas['pendientes'] ?? 0) + $n;

        return $metricas;
    }
}
