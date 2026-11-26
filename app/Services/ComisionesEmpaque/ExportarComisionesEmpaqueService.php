<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarComisionesEmpaqueService
{
    public function __construct(private ConsultarComisionesEmpaqueService $consultar) {}

    public function reporte(Request $request, User $usuario): StreamedResponse
    {
        $conexion = DB::connection();
        if ($conexion->getDriverName() === 'mysql' && $conexion->transactionLevel() === 0) {
            $conexion->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
        $reporte = $conexion->transaction(function () use ($request, $usuario) {
            $zona = ConfiguracionComisionEmpaque::query()->value('zona_horaria') ?? config('app.timezone');

            return app(ConsultarReporteComisionesEmpaque::class)->consultar($request, $usuario, $zona, detalle: true);
        });
        abort_unless(hash_equals($reporte['version'], $request->input('version')), 409, 'La consulta cambió. Actualice el cálculo y vuelva a generar el reporte.');

        $resumen = [];
        foreach ($reporte['grupos'] as $grupo) {
            $cabecera = [
                'Área' => $grupo['area'], 'Desde' => $grupo['desde'], 'Hasta' => $grupo['hasta'],
                'Tipo' => $grupo['corte'] ? 'Corte histórico · Liquidación confirmada' : 'Reporte preliminar',
                'Corte' => $grupo['folio'],
            ];
            $columnas = [
                'Concepto' => null, 'Cajas válidas' => null, 'Tarifa por caja' => null, 'Comisión' => null,
                'Reparto vigente desde' => null, 'Colaborador' => null, 'Porcentaje' => null, 'Observaciones' => null,
            ];
            $fila = fn (array $datos) => $cabecera + array_replace($columnas, $datos);
            $resumen[] = $fila(['Concepto' => 'Comisión total', 'Cajas válidas' => $grupo['cajas'], 'Comisión' => (float) $grupo['importe']]);
            foreach ($grupo['tarifas'] as $tarifa) {
                $resumen[] = $fila(['Concepto' => 'Tarifa aplicada', 'Cajas válidas' => $tarifa['cajas'],
                    'Tarifa por caja' => (float) $tarifa['tarifa'], 'Comisión' => (float) $tarifa['importe']]);
            }
            foreach ($grupo['tramos'] as $tramo) {
                foreach ($tramo['participantes'] as $persona) {
                    $resumen[] = $fila([
                        'Concepto' => 'Distribución', 'Reparto vigente desde' => $tramo['desde'],
                        'Colaborador' => $persona['nombre'], 'Porcentaje' => $persona['porcentaje'].'%',
                        'Comisión' => (float) $persona['importe'],
                    ]);
                }
                $resumen[] = $fila(['Concepto' => 'Total del reparto', 'Reparto vigente desde' => $tramo['desde'],
                    'Porcentaje' => '100%', 'Comisión' => (float) $tramo['importe']]);
            }
            if (! $grupo['corte']) {
                foreach (['pendientes' => 'Empaques que requieren revisión', 'tardios' => 'Empaques de periodos cerrados', 'excluidos' => 'Empaques excluidos'] as $campo => $etiqueta) {
                    if ($grupo[$campo]) {
                        $resumen[] = $fila(['Concepto' => 'Exclusión', 'Observaciones' => $etiqueta.' ('.$grupo[$campo].')']);
                    }
                }
                foreach ($grupo['cortes_parciales'] as $corte) {
                    $resumen[] = $fila(['Concepto' => 'Exclusión', 'Observaciones' => 'El intervalo cubre solo parte del corte '.$corte['folio'].'.']);
                }
                foreach ($grupo['advertencias'] as $aviso) {
                    $resumen[] = $fila(['Concepto' => 'Advertencia', 'Observaciones' => $aviso]);
                }
                if ($grupo['reparto_incompleto']) {
                    $resumen[] = $fila(['Concepto' => 'Advertencia', 'Observaciones' => 'La distribución está incompleta.']);
                }
            }
        }

        return (new FastExcel(new SheetCollection([
            'Resumen de comisiones' => collect($resumen),
            'Detalle de empaques' => collect($reporte['detalle']),
        ])))->download('reporte-comisiones-empaque.xlsx');
    }

    public function acumulado(Request $request, ?User $usuario): StreamedResponse
    {
        $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
        $filas = $this->consultar->filtrar($request, $usuario)
            ->with(['area:id,nombre', 'lineas.tipo:id,nombre', 'corte:id,folio'])
            ->orderBy('empacado_at')
            ->get()
            ->map(function (UnidadComisionEmpaque $u) use ($zona) {
                $fila = $this->consultar->fila($u, $zona);

                return [
                    'Fecha de empaque' => $fila['empacado_at'],
                    'Fecha de incorporación' => $fila['incorporado_at'],
                    'Cliente' => $fila['cliente'],
                    'Pedido' => $fila['folio_pedido'],
                    'Remisión' => implode(', ', $fila['remisiones']),
                    'Área' => $fila['area'],
                    'Desglose' => implode('; ', $fila['tipos'] ?: ['Pendiente de clasificación']),
                    'Cantidad de origen' => $fila['cantidad_origen'],
                    'Cajas' => $fila['cajas'],
                    'Bolsas excluidas' => $fila['bolsas'],
                    'Persona que empacó' => $fila['empacador'],
                    'Paquetería' => $fila['logistica']['paqueteria'] ?? null,
                    'Logística' => match ($fila['logistica']['logistica'] ?? null) {
                        'con' => 'Con logística',
                        'sin' => 'Sin logística',
                        default => null,
                    },
                    'Liquidación' => $fila['corte'] ? 'En corte' : ($fila['liquidacion'] === 'pagado_fuera' ? 'Pagado fuera' : 'Pendiente'),
                    'Modalidad de envío' => $fila['logistica']['modalidad_envio'] ?? $fila['logistica']['modalidad_preparacion'] ?? null,
                    'Motivo comercial' => $fila['motivo_comercial'],
                    'Estado de conciliación' => match ($fila['conciliacion']['decision'] ?? null) {
                        'validar' => 'Validada', 'excluir' => 'Excluida', 'pendiente' => 'Pendiente',
                        default => $fila['motivo_comercial_codigo'] === 'sin_informacion' ? 'Sin información' : 'Informada por el pedido',
                    },
                    'Observaciones' => $fila['conciliacion']['observaciones'] ?? $fila['notas'],
                    'Referencia' => $fila['conciliacion']['referencia'] ?? $fila['referencia'],
                    'Tarifa por caja' => $fila['tarifa'],
                    'Categoría de paquetería' => $fila['logistica']['categoria_paqueteria'] ?? null,
                    'Modalidad de tarifa' => $fila['logistica']['modalidad_tarifa'] ?? null,
                    'Tipo de guía' => $fila['logistica']['tipo_guia'] ?? null,
                    'Envío tienda' => $fila['logistica']['envio_tienda'] ?? null,
                    'Origen del pedido' => $fila['logistica']['origen_solicitud'] ?? null,
                    'Importe' => $fila['importe_comisionable'] ?? ($fila['liquidacion'] === 'pagado_fuera' ? $fila['importe'] : null),
                    'Importe potencial' => $fila['importe_potencial'],
                    'Situación' => $fila['situacion_etiqueta'],
                    'Corte' => $fila['corte'],
                    'Incorporación tardía' => $fila['incorporacion_tardia'] ? 'Sí' : 'No',
                    'Excepción de resguardo' => $fila['excepcion_resguardo'] ? 'Sí' : 'No',
                    'Procedencia' => $u->procedencia,
                ];
            });

        return (new FastExcel($filas))->download('comisiones-empaque-acumulado.xlsx');
    }

    public function productividad(Request $request, ?User $usuario): StreamedResponse
    {
        $filas = collect($this->consultar->productividad($request, $usuario))->map(fn ($fila) => [
            'Empacador' => $fila['nombre'],
            'Área' => $fila['area'],
            'Desde' => $request->input('desde'),
            'Hasta' => $request->input('hasta'),
            'Cajas registradas' => $fila['cajas'],
            'Bolsas' => $fila['bolsas'],
            'Bultos sin clasificar' => $fila['sin_desglose'],
            'Cajas comisionables' => $fila['validas'],
            'Cajas pagadas fuera' => $fila['pagadas_fuera'],
            'Cajas pendientes' => $fila['pendientes'],
            'Cajas excluidas' => $fila['excluidas'],
            'Pedidos distintos' => $fila['pedidos'],
            'Participación física (%)' => $fila['porcentaje'],
        ]);

        return (new FastExcel($filas))->download('comisiones-empaque-productividad.xlsx');
    }

    public function tipos(Request $request, ?User $usuario): StreamedResponse
    {
        $filas = [];
        $this->consultar->filtrar($request, $usuario)->with(['area:id,nombre', 'lineas.tipo:id,nombre'])->orderBy('id')->chunk(200, function ($unidades) use (&$filas) {
            foreach ($unidades as $unidad) {
                if ($unidad->lineas->isEmpty()) {
                    $filas[] = [
                        'Pedido' => $unidad->folio_pedido,
                        'Área' => $unidad->area?->nombre,
                        'Tipo' => 'Pendiente de clasificación',
                        'Cantidad' => $unidad->cantidad_origen,
                        'Provisional' => 'No',
                    ];

                    continue;
                }
                foreach ($unidad->lineas as $linea) {
                    $filas[] = [
                        'Pedido' => $unidad->folio_pedido,
                        'Área' => $unidad->area?->nombre,
                        'Tipo' => $linea->es_bolsa ? 'Bolsa excluida' : ($linea->tipo?->nombre ?: 'Caja sin tipo'),
                        'Cantidad' => $linea->cantidad,
                        'Provisional' => 'No',
                    ];
                }
            }
        });

        return (new FastExcel(collect($filas)))->download('comisiones-empaque-tipos.xlsx');
    }

    public function corte(CorteComisionEmpaque $corte): StreamedResponse
    {
        $provisional = $corte->estado !== CorteComisionEmpaque::CONFIRMADO;
        $filas = [];
        if ($provisional) {
            foreach ($corte->simulacion['unidades'] ?? [] as $unidad) {
                $filas[] = [
                    'Provisional' => 'Sí',
                    'Folio' => 'Borrador',
                    'Pedido' => $unidad['folio_pedido'] ?? null,
                    'Remisión' => $unidad['folio_remision'] ?? null,
                    'Empaque' => $unidad['empacado_at'] ?? null,
                    'Cajas' => $unidad['cantidad'] ?? null,
                    'Importe' => $unidad['importe'] ?? null,
                ];
            }
        } else {
            $corte->load('unidades', 'area');
            foreach ($corte->unidades as $unidad) {
                $filas[] = [
                    'Provisional' => 'No',
                    'Folio' => $corte->folio,
                    'Área' => $corte->area?->nombre,
                    'Pedido' => $unidad->referencia['folio_pedido'] ?? null,
                    'Remisión' => $unidad->referencia['folio_remision'] ?? null,
                    'Empaque' => optional($unidad->empacado_at)->format('Y-m-d H:i'),
                    'Cajas' => $unidad->cantidad_comisionable,
                    'Importe' => DineroComisionEmpaque::texto((int) $unidad->importe_centavos),
                    'Desglose' => collect($unidad->desglose)->map(fn ($d) => $d['cantidad'].' '.$d['tipo'])->implode('; '),
                    'Excepción de resguardo' => $unidad->excepcion_resguardo ? 'Sí' : 'No',
                ];
            }
        }

        return (new FastExcel(collect($filas)))->download(($corte->folio ?: 'borrador-corte').'.xlsx');
    }

    public function reparto(CorteComisionEmpaque $corte): StreamedResponse
    {
        $provisional = $corte->estado !== CorteComisionEmpaque::CONFIRMADO;
        $filas = [];
        if ($provisional) {
            foreach ($corte->simulacion['tramos'] ?? [] as $tramo) {
                foreach ($tramo['participantes'] ?? [] as $participante) {
                    $filas[] = [
                        'Provisional' => 'Sí',
                        'Folio' => 'Borrador',
                        'Participante' => $participante['nombre'] ?? null,
                        'Porcentaje' => $participante['porcentaje'] ?? null,
                        'Importe' => $participante['importe'] ?? null,
                    ];
                }
            }
        } else {
            $corte->load('asignaciones.participante');
            foreach ($corte->asignaciones as $asignacion) {
                $filas[] = [
                    'Provisional' => 'No',
                    'Folio' => $corte->folio,
                    'Participante' => $asignacion->participante?->nombre,
                    'Porcentaje' => DineroComisionEmpaque::porcentaje((int) $asignacion->puntos_base),
                    'Reconocido' => DineroComisionEmpaque::texto($asignacion->reconocidoCentavos()),
                    'Pagado' => DineroComisionEmpaque::texto((int) $asignacion->pagado_centavos),
                    'Pendiente' => DineroComisionEmpaque::texto($asignacion->pendienteCentavos()),
                ];
            }
        }

        return (new FastExcel(collect($filas)))->download('reparto-'.($corte->folio ?: 'borrador').'.xlsx');
    }

    public function pagos(?User $usuario, ?int $areaId = null): StreamedResponse
    {
        $saldos = $this->consultar->saldos($usuario, $areaId);
        $filas = [];
        foreach ($saldos as $saldo) {
            foreach ($saldo['asignaciones'] as $asignacion) {
                $filas[] = [
                    'Participante' => $saldo['nombre'],
                    'Activo' => $saldo['activo'] ? 'Sí' : 'No',
                    'Corte' => $asignacion['folio'],
                    'Área' => $asignacion['area'],
                    'Porcentaje' => $asignacion['porcentaje'],
                    'Reconocido' => $asignacion['reconocido'],
                    'Pagado' => $asignacion['pagado'],
                    'Pendiente' => $asignacion['pendiente'],
                ];
            }
        }

        $alcance = app(AlcanceAreaComisionEmpaque::class);
        $permitido = function ($q) use ($areaId, $usuario, $alcance) {
            if ($areaId) {
                $q->where('area_id', $areaId);
            }
            $alcance->aplicar($q, $usuario);
        };
        $pagos = PagoComisionEmpaque::query()->with(['participante', 'aplicaciones' => fn ($q) => $q->whereHas('asignacion.corte', $permitido)])
            ->whereHas('aplicaciones.asignacion.corte', $permitido)->orderByDesc('id')->get();
        foreach ($pagos as $pago) {
            $filas[] = [
                'Participante' => $pago->participante?->nombre,
                'Activo' => null,
                'Corte' => 'Pago '.$pago->id,
                'Área' => $pago->estado,
                'Porcentaje' => null,
                'Reconocido' => null,
                'Pagado' => DineroComisionEmpaque::texto((int) $pago->aplicaciones->sum('importe_centavos')),
                'Pendiente' => $pago->fecha_pago?->format('Y-m-d'),
                'Referencia' => $pago->referencia,
                'Medio' => $pago->medio,
            ];
        }

        return (new FastExcel(collect($filas)))->download('comisiones-empaque-pagos.xlsx');
    }
}
