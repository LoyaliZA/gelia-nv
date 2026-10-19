<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarComisionesEmpaqueService
{
    public function __construct(private ConsultarComisionesEmpaqueService $consultar) {}

    public function acumulado(Request $request, ?User $usuario): StreamedResponse
    {
        $filas = $this->consultar->filtrar($request, $usuario)
            ->with(['area:id,nombre', 'lineas.tipo:id,nombre', 'corte:id,folio'])
            ->orderBy('empacado_at')
            ->get()
            ->map(function (UnidadComisionEmpaque $u) {
                $fila = $this->consultar->fila($u);

                return [
                    'Fecha de empaque' => $fila['empacado_at'],
                    'Fecha de incorporación' => $fila['incorporado_at'],
                    'Cliente' => $fila['cliente'],
                    'Pedido' => $fila['folio_pedido'],
                    'Remisión' => $fila['folio_remision'],
                    'Área' => $fila['area'],
                    'Desglose' => implode('; ', $fila['tipos'] ?: ['Pendiente de clasificación']),
                    'Cantidad de origen' => $fila['cantidad_origen'],
                    'Cajas comisionables' => $fila['cajas'],
                    'Bolsas excluidas' => $fila['bolsas'],
                    'Persona que empacó' => $fila['empacador'],
                    'Paquetería' => $fila['logistica']['paqueteria'] ?? null,
                    'Categoría de paquetería' => $fila['logistica']['categoria_paqueteria'] ?? null,
                    'Modalidad de tarifa' => $fila['logistica']['modalidad_tarifa'] ?? null,
                    'Tipo de guía' => $fila['logistica']['tipo_guia'] ?? null,
                    'Envío tienda' => $fila['logistica']['envio_tienda'] ?? null,
                    'Origen del pedido' => $fila['logistica']['origen_solicitud'] ?? null,
                    'Importe' => $fila['importe'],
                    'Situación' => $fila['situacion_etiqueta'],
                    'Corte' => $fila['corte'],
                    'Incorporación tardía' => $fila['incorporacion_tardia'] ? 'Sí' : 'No',
                    'Excepción de resguardo' => $fila['excepcion_resguardo'] ? 'Sí' : 'No',
                    'Procedencia' => $u->procedencia,
                ];
            });

        return (new FastExcel($filas))->download('comisiones-empaque-acumulado.xlsx');
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

    public function pagos(?User $usuario): StreamedResponse
    {
        $saldos = $this->consultar->saldos($usuario);
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

        $pagos = PagoComisionEmpaque::query()->with('participante')->orderByDesc('id')->get();
        foreach ($pagos as $pago) {
            $filas[] = [
                'Participante' => $pago->participante?->nombre,
                'Activo' => null,
                'Corte' => 'Pago '.$pago->id,
                'Área' => $pago->estado,
                'Porcentaje' => null,
                'Reconocido' => null,
                'Pagado' => DineroComisionEmpaque::texto((int) $pago->importe_centavos),
                'Pendiente' => $pago->fecha_pago?->format('Y-m-d'),
                'Referencia' => $pago->referencia,
                'Medio' => $pago->medio,
            ];
        }

        return (new FastExcel(collect($filas)))->download('comisiones-empaque-pagos.xlsx');
    }
}
