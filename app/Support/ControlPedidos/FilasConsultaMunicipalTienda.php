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

    private const TABS = ['TODOS', 'PENDIENTES', 'ATENDIDOS'];

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
        $coincide = function (array $fila) use ($q, $almacenId): bool {
            if ($almacenId && (int) $fila['almacen_id'] !== $almacenId) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($fila['folio'].' '.$fila['folio_visible'].' '.$fila['folio_remision'].' '.$fila['cliente']), $q);
        };

        $filas = [];
        if (in_array($tab, ['TODOS', 'PENDIENTES'], true)) {
            foreach (AlcanceConsultaMunicipal::pendientesTienda($usuario, 200) as $consulta) {
                if (! $coincide($consulta)) {
                    continue;
                }
                $filas[] = self::filaConsulta($consulta);
            }
            foreach (AlcanceConsultaMunicipal::porEmpacarTienda($usuario, 200) as $pedido) {
                if (! $coincide($pedido)) {
                    continue;
                }
                $filas[] = self::filaEmpaque($pedido, 'PENDIENTE', 'Aprobado: por empacar', $pedido['aprobado_at']);
            }
        }
        if (in_array($tab, ['TODOS', 'ATENDIDOS'], true)) {
            foreach (AlcanceConsultaMunicipal::atendidasTienda($usuario, 200) as $consulta) {
                if (! $coincide($consulta)) {
                    continue;
                }
                $filas[] = self::filaConsulta($consulta, 'RESPONDIDA', $consulta['cerrada'] ? 'Consulta cerrada' : 'Consulta respondida');
            }
            foreach (AlcanceConsultaMunicipal::empacadosTienda($usuario, 200) as $pedido) {
                if (! $coincide($pedido)) {
                    continue;
                }
                $filas[] = self::filaEmpaque($pedido, 'RESPONDIDA', 'Empacado', $pedido['empacado_at'] ?? $pedido['aprobado_at']);
            }
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
        $pendientes = count(self::filas($usuario, array_merge($filtros, ['tab' => 'PENDIENTES'])));
        $atendidos = count(self::filas($usuario, array_merge($filtros, ['tab' => 'ATENDIDOS'])));
        $metricas['todos'] = (int) ($metricas['todos'] ?? 0) + $pendientes + $atendidos;
        $metricas['pendientes'] = (int) ($metricas['pendientes'] ?? 0) + $pendientes;
        $metricas['atendidos'] = (int) ($metricas['atendidos'] ?? 0) + $atendidos;

        return $metricas;
    }

    /** @param array<string, mixed> $consulta */
    private static function filaConsulta(array $consulta, string $estado = 'PENDIENTE', string $estadoLabel = 'Consulta pendiente'): array
    {
        return [
            'id' => 'consulta-'.$consulta['id'],
            'tipo_fila' => self::TIPO,
            'estado' => $estado,
            'estado_label' => $estadoLabel,
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

    /** @param array<string, mixed> $pedido */
    private static function filaEmpaque(array $pedido, string $estado, string $estadoLabel, ?string $solicitadaAt): array
    {
        return [
            'id' => 'empaque-'.$pedido['id'],
            'tipo_fila' => self::TIPO_EMPAQUE,
            'estado' => $estado,
            'estado_label' => $estadoLabel,
            'solicitada_at' => $solicitadaAt,
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
}
