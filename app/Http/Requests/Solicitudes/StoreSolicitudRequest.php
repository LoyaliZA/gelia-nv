<?php

namespace App\Http\Requests\Solicitudes;

use App\Models\Cliente;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('solicitudes.crear'); 
    }

    public function rules(): array
    {
        return [
            'numero_cliente' => ['nullable', 'string', 'max:255'],
            'nombre_cliente' => ['nullable', 'string', 'max:255'],
            'catalogo_proceso_id' => ['required', 'exists:catalogo_procesos,id'],
            'catalogo_tipo_cliente_id' => ['nullable', 'exists:catalogo_tipo_clientes,id'],
            'catalogo_lista_descuento_id' => ['nullable', 'exists:catalogo_listas_descuento,id'],
            'monto_cotizado' => ['required', 'numeric', 'min:0'],
            'observaciones_vendedor' => ['nullable', 'string'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $numeroCliente = $this->input('numero_cliente');
                $procesoId = $this->input('catalogo_proceso_id');
                $listaSolicitadaId = $this->input('catalogo_lista_descuento_id');
                $tipoClienteId = $this->input('catalogo_tipo_cliente_id');
                $montoCotizado = (float) $this->input('monto_cotizado', 0);

                if (!$numeroCliente) return;

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
                if (!$cliente) return;
                
                // 1. SEGURO DE PROCESOS HEREDADOS
                if ($procesoId && $cliente->es_heredado) {
                    $proceso = CatalogoProceso::find($procesoId);
                    if ($proceso && in_array($proceso->nombre, ['ASIGNAR CLIENTE REACTIVADO', 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'])) {
                        $validator->errors()->add('catalogo_proceso_id', 'ALERTA: Este es un cliente heredado. Selecciona el proceso específico para clientes heredados.');
                    }
                }

                // 2. SEGURO DE TIPOS DE CLIENTE (CANDADO DE COMISIONES)
                if ($tipoClienteId && $cliente->es_heredado) {
                    $tipoCliente = CatalogoTipoCliente::find($tipoClienteId);
                    // Si el tipo seleccionado NO tiene la palabra "heredado" en su nombre, bloqueamos.
                    if ($tipoCliente && stripos($tipoCliente->nombre, 'HEREDADO') === false) {
                        $validator->errors()->add('catalogo_tipo_cliente_id', 'OPERACIÓN DENEGADA: Este es un cliente protegido (Heredado). Solo puedes asignarle tipos de cliente de categoría "Heredado".');
                    }
                }

                // 3. SEGUROS DE ASIGNACIÓN DE LISTAS
                if ($listaSolicitadaId) {
                    $listaSolicitada = CatalogoListaDescuento::find($listaSolicitadaId);
                    $montoVentaActual = (float) ($cliente->monto_venta_actual ?? 0);
                    $montoProyectado = $montoVentaActual + $montoCotizado;

                    $esListaEspecial = str_contains(strtoupper($listaSolicitada->nombre), 'COLABORADOR');
                    $esClienteColaborador = str_contains(strtoupper($cliente->lista_actual ?? ''), 'COLABORADOR');

                    if ($listaSolicitada && !$esListaEspecial && !$esClienteColaborador) {
                        if ($cliente->lista_actual_id == $listaSolicitadaId || $cliente->lista_actual == $listaSolicitada->nombre) {
                            $validator->errors()->add('catalogo_lista_descuento_id', 'El cliente ya se encuentra en la lista ' . $listaSolicitada->nombre . '.');
                        }
                        if ($montoProyectado < (float) $listaSolicitada->monto_minimo) {
                            $validator->errors()->add('catalogo_lista_descuento_id', 'Advertencia: El monto proyectado es inferior al mínimo requerido para la lista ' . $listaSolicitada->nombre . '.');
                        }
                        if ($listaSolicitada->monto_maximo > 0 && $montoProyectado > (float) $listaSolicitada->monto_maximo) {
                            $validator->errors()->add('catalogo_lista_descuento_id', 'Advertencia: El monto proyectado supera el límite de la lista ' . $listaSolicitada->nombre . '. Debes promover al cliente a una lista superior.');
                        }
                    }
                }
            }
        ];
    }
}