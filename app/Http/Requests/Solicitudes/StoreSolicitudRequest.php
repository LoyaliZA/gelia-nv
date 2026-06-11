<?php

namespace App\Http\Requests\Solicitudes;

use App\Models\Cliente;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use App\Services\Solicitudes\EscalonamientoService;
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
        $proceso = $this->resolverProceso();
        $esOperativo = $proceso?->esOperativo() ?? false;
        $compraEnTienda = $this->aplicaFlujoCompraEnTienda();

        $reglas = [
            'numero_cliente' => [$esOperativo ? 'required' : 'nullable', 'string', 'max:255'],
            'nombre_cliente' => ['nullable', 'string', 'max:255'],
            'catalogo_proceso_id' => ['required', 'exists:catalogo_procesos,id'],
            'observaciones_vendedor' => ['nullable', 'string'],
            'compra_en_tienda' => ['nullable', 'boolean'],
        ];

        if ($esOperativo) {
            $reglas = array_merge($reglas, $this->reglasOperativas($proceso));
        } else {
            $reglas = array_merge($reglas, [
                'catalogo_tipo_cliente_id' => ['nullable', 'exists:catalogo_tipo_clientes,id'],
                'catalogo_lista_descuento_id' => ['nullable', 'exists:catalogo_listas_descuento,id'],
                'monto_cotizado' => [$compraEnTienda ? 'nullable' : 'required', 'numeric', 'min:0'],
                'confirmo_informacion_escalonamiento' => ['nullable', 'boolean'],
                'monto_final_tentativo' => ['nullable', 'numeric', 'min:0'],
                'total_proyectado_neto' => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        return $reglas;
    }

    private function reglasOperativas(?CatalogoProceso $proceso): array
    {
        $nombre = strtoupper($proceso?->nombre ?? '');

        $reglas = [
            'monto_cotizado' => ['nullable', 'numeric', 'min:0'],
            'numero_remision' => ['nullable', 'string', 'max:255'],
            'numero_pedido' => ['nullable', 'string', 'max:255'],
            'fecha_operacion' => ['nullable', 'date'],
            'motivo_operacion' => ['nullable', 'string'],
            'catalogo_banco_id' => ['nullable', 'exists:catalogo_bancos,id'],
            'solicitar_cotizacion' => ['nullable', 'boolean'],
        ];

        if (str_contains($nombre, 'REMISIÓN') || str_contains($nombre, 'REMISION')) {
            $reglas['numero_remision'] = ['required', 'string', 'max:255'];
            $reglas['fecha_operacion'] = ['required', 'date'];
            $reglas['motivo_operacion'] = ['required', 'string', 'min:5'];
            $reglas['catalogo_banco_id'] = ['required', 'exists:catalogo_bancos,id'];
        } elseif (str_contains($nombre, 'PEDIDO')) {
            $reglas['numero_pedido'] = ['required', 'string', 'max:255'];
            if (str_contains($nombre, 'CANCEL')) {
                $reglas['motivo_operacion'] = ['required', 'string', 'min:5'];
            }
        } elseif (str_contains($nombre, 'COTIZACIÓN') || str_contains($nombre, 'COTIZACION')) {
            $reglas['numero_pedido'] = ['required', 'string', 'max:255'];
        }

        return $reglas;
    }

    private function resolverProceso(): ?CatalogoProceso
    {
        $procesoId = $this->input('catalogo_proceso_id');
        if (!$procesoId) {
            return null;
        }

        return CatalogoProceso::find($procesoId);
    }

    private function aplicaFlujoCompraEnTienda(): bool
    {
        $proceso = $this->resolverProceso();
        if (!$proceso || $proceso->esOperativo()) {
            return false;
        }

        return str_contains(strtoupper($proceso->nombre), 'ASIGNAR CLIENTE NUEVO')
            && filter_var($this->input('compra_en_tienda'), FILTER_VALIDATE_BOOLEAN);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $proceso = $this->resolverProceso();

                if ($proceso?->esOperativo()) {
                    $validator->errors()->add(
                        'catalogo_proceso_id',
                        'Las solicitudes operativas deben crearse desde el módulo Cancelaciones y Cotizaciones.'
                    );
                    return;
                }

                $usuario = $this->user()->loadMissing(['departamentos', 'area']);
                $tieneDepartamento = $usuario->departamentos->isNotEmpty() || $usuario->area?->departamento_id;
                if (!$tieneDepartamento) {
                    $validator->errors()->add(
                        'catalogo_proceso_id',
                        'No puedes crear solicitudes: tu usuario no tiene departamento asignado. Contacta a administración.'
                    );
                    return;
                }

                if ($this->aplicaFlujoCompraEnTienda()) {
                    if (!str_contains(strtoupper($proceso->nombre), 'ASIGNAR CLIENTE NUEVO')) {
                        $validator->errors()->add('compra_en_tienda', 'La compra en tienda solo aplica a solicitudes de asignar cliente nuevo.');
                    }

                    return;
                }

                $numeroCliente = $this->input('numero_cliente');
                $procesoId = $this->input('catalogo_proceso_id');
                $listaSolicitadaId = $this->input('catalogo_lista_descuento_id');
                $tipoClienteId = $this->input('catalogo_tipo_cliente_id');
                $montoCotizado = (float) $this->input('monto_cotizado', 0);

                if (!$numeroCliente) return;

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
                if (!$cliente) return;

                if ($procesoId && $cliente->es_heredado) {
                    $procesoObj = CatalogoProceso::find($procesoId);
                    if ($procesoObj && in_array($procesoObj->nombre, ['ASIGNAR CLIENTE REACTIVADO', 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'])) {
                        $validator->errors()->add('catalogo_proceso_id', 'ALERTA: Este es un cliente heredado. Selecciona el proceso específico para clientes heredados.');
                    }
                }

                if ($tipoClienteId && $cliente->es_heredado) {
                    $tipoCliente = CatalogoTipoCliente::find($tipoClienteId);
                    if ($tipoCliente && stripos($tipoCliente->nombre, 'HEREDADO') === false) {
                        $validator->errors()->add('catalogo_tipo_cliente_id', 'OPERACIÓN DENEGADA: Este es un cliente protegido (Heredado). Solo puedes asignarle tipos de cliente de categoría "Heredado".');
                    }
                }

                $montoVentaActual = (float) ($cliente->monto_venta_actual ?? 0);
                $montoProyectado = $montoVentaActual + $montoCotizado;

                if ($listaSolicitadaId) {
                    $listaSolicitada = CatalogoListaDescuento::with('porcentajeEscalonamiento')->find($listaSolicitadaId);

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

                if ($montoCotizado > 0) {
                    $listas = CatalogoListaDescuento::with('porcentajeEscalonamiento')->where('activo', true)->get();
                    $listaActual = $cliente->lista_actual_id
                        ? $listas->firstWhere('id', $cliente->lista_actual_id)
                        : null;
                    $requisitoActual = $listaActual ? (float) $listaActual->monto_requerido : 0;

                    $escalonamiento = app(EscalonamientoService::class)->evaluar(
                        $montoVentaActual,
                        $montoCotizado,
                        $listaSolicitadaId ? (int) $listaSolicitadaId : null,
                        $listas,
                        $requisitoActual
                    );

                    if ($escalonamiento['bruto_califica_neto_no']
                        && !filter_var($this->input('confirmo_informacion_escalonamiento'), FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add(
                            'confirmo_informacion_escalonamiento',
                            'Debes confirmar que informaste al cliente el monto bruto necesario para mantener la lista con el descuento aplicado.'
                        );
                    }
                }
            }
        ];
    }
}
