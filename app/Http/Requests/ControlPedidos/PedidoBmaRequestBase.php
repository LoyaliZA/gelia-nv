<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

abstract class PedidoBmaRequestBase extends FormRequest
{
    /** Casillas que el formulario envía como texto en multipart/form-data. */
    private const CAMPOS_BOOLEANOS = [
        'aplica_saldo_favor',
        'direccion_manual_excepcion',
        'envia_a_otra_persona',
        'es_resguardo',
        'anexar_remision',
        'aplica_seguro',
        'cliente_proporciona_guia',
        'envio_por_cobrar',
        'reabrir_pago_costos',
        'confirmar_anular_envio',
        'prioridad_md',
        'enviar',
        'direccion.domicilio_irregular',
        'solicitud_preparacion.entrega_municipal.destinatario_es_cliente',
    ];

    protected function prepareForValidation(): void
    {
        // La interfaz está en español; APP_LOCALE sigue en inglés y, sin esto, Laravel responde en inglés.
        app()->setLocale('es');

        $datos = $this->all();
        $cambios = [];

        foreach (self::CAMPOS_BOOLEANOS as $campo) {
            if (! Arr::has($datos, $campo)) {
                continue;
            }

            $actual = Arr::get($datos, $campo);
            $normalizado = self::booleanoDeFormulario($actual);
            if ($normalizado === $actual) {
                continue;
            }

            Arr::set($datos, $campo, $normalizado);
            $raiz = strstr($campo, '.', true) ?: $campo;
            $cambios[$raiz] = $datos[$raiz];
        }

        if ($cambios !== []) {
            $this->merge($cambios);
        }
    }

    /**
     * Laravel solo acepta true, false, 0, 1, "0" y "1".
     * Axios, al usar multipart, manda las cadenas "true" y "false".
     */
    public static function booleanoDeFormulario(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        return match (strtolower(trim($valor))) {
            '1', 'true', 'on', 'yes', 'si', 'sí' => 1,
            '0', 'false', 'off', 'no' => 0,
            default => $valor,
        };
    }

    protected function reglasComunes(): array
    {
        return [
            'departamento_operacion_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'origen_id' => ['nullable', 'exists:origenes_pedido,id'],
            'tipo_operacion_envio_id' => ['nullable', 'exists:catalogo_tipos_operacion_envio,id'],
            'numero_cliente' => ['nullable', 'string', 'max:50'],
            'folio_remision' => ['nullable', 'string', 'max:100'],
            'fecha' => ['nullable', 'date'],
            'almacen_id' => ['nullable', 'exists:almacenes,id'],
            'sucursal_destino_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'catalogo_banco_id' => ['nullable', 'exists:catalogo_bancos,id'],
            'aplica_saldo_favor' => ['nullable', 'boolean'],
            'saldo_a_favor' => ['nullable', 'numeric', 'min:0'],
            'saf_aplicaciones' => ['nullable', 'array'],
            'saf_aplicaciones.*.saf_credito_id' => ['required_with:saf_aplicaciones', 'integer', 'exists:saf_creditos,id'],
            'saf_aplicaciones.*.monto' => ['required_with:saf_aplicaciones', 'numeric', 'min:0.01'],
            'catalogo_tipo_caja_id' => ['nullable', 'exists:catalogo_tipos_caja_pedido,id'],
            'numero_cajas' => ['nullable', 'integer', 'min:0', 'max:999'],
            'peso_real_kg' => ['nullable', 'numeric', 'min:0'],
            'peso_cobrado_guia_kg' => ['nullable', 'numeric', 'min:0'],
            'catalogo_paqueteria_id' => ['nullable', 'exists:catalogo_paqueterias_pedido,id'],
            'catalogo_tipo_guia_id' => ['nullable', 'exists:catalogo_tipos_guia_pedido,id'],
            'catalogo_zona_id' => ['nullable', 'exists:catalogo_zonas_pedido,id'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'domicilio_entrega' => ['nullable', 'string'],
            'cliente_direccion_id' => ['nullable', 'integer', 'exists:cliente_direcciones,id'],
            'direccion_manual_excepcion' => ['nullable', 'boolean'],
            'motivo_direccion_manual' => ['nullable', 'string', 'max:500'],
            'direccion' => ['nullable', 'array'],
            'direccion.nombre_destinatario' => ['nullable', 'string', 'max:255'],
            'direccion.telefono_destinatario' => ['nullable', 'string', 'max:30'],
            'direccion.calle' => ['nullable', 'string', 'max:255'],
            'direccion.numero_exterior' => ['nullable', 'string', 'max:30'],
            'direccion.numero_interior' => ['nullable', 'string', 'max:30'],
            'direccion.colonia' => ['nullable', 'string', 'max:255'],
            'direccion.codigo_postal' => ['nullable', 'string', 'max:10'],
            'direccion.municipio' => ['nullable', 'string', 'max:255'],
            'direccion.ciudad' => ['nullable', 'string', 'max:255'],
            'direccion.estado' => ['nullable', 'string', 'max:255'],
            'direccion.pais' => ['nullable', 'string', 'max:255'],
            'direccion.referencias' => ['nullable', 'string', 'max:2000'],
            'direccion.indicaciones_entrega' => ['nullable', 'string', 'max:2000'],
            'direccion.domicilio_irregular' => ['nullable', 'boolean'],
            'envia_a_otra_persona' => ['nullable', 'boolean'],
            'envia_otra_persona' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => filter_var($this->input('envia_a_otra_persona'), FILTER_VALIDATE_BOOLEAN)),
            ],
            'es_resguardo' => ['nullable', 'boolean'],
            'envio_solo_resguardos' => ['nullable', 'boolean'],
            'notas_resguardos_externos' => ['nullable', 'string', 'max:2000'],
            'resguardo_ids' => ['nullable', 'array'],
            'resguardo_ids.*' => ['integer', 'exists:pedidos_bma,id'],
            'modo_resguardo' => ['nullable', 'in:abierto,complementario'],
            'pedido_principal_id' => [
                'nullable',
                'integer',
                'exists:pedidos_bma,id',
                'required_if:modo_resguardo,complementario',
            ],
            'anexar_remision' => ['nullable', 'boolean'],
            'total_mercancia' => ['nullable', 'numeric', 'min:0'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'aplica_seguro' => ['nullable', 'boolean'],
            'cliente_proporciona_guia' => ['nullable', 'boolean'],
            'envio_por_cobrar' => ['nullable', 'boolean'],
            'costo_seguro' => ['nullable', 'numeric', 'min:0'],
            'cajas_costos' => ['nullable', 'array'],
            'cajas_costos.*.uuid_operativo' => ['required_with:cajas_costos', 'uuid'],
            'cajas_costos.*.costo_envio' => ['nullable', 'numeric', 'min:0'],
            'cajas_costos.*.costo_seguro' => ['nullable', 'numeric', 'min:0'],
            'cajas_costos.*.costo_adicional' => ['nullable', 'numeric', 'min:0'],
            'cajas_costos.*.concepto_adicional' => ['nullable', 'string', 'max:128'],
            'reabrir_pago_costos' => ['nullable', 'boolean'],
            'motivo_reapertura_pago' => ['nullable', 'string', 'min:5', 'max:2000'],
            'confirmar_anular_envio' => ['nullable', 'boolean'],
            'motivo_anular_envio' => ['nullable', 'string', 'min:5', 'max:2000'],
            'revision_esperada' => ['nullable', 'integer', 'min:0'],
            'base_captura' => ['nullable', 'string', 'size:64'],
            'captura_clave' => ['nullable', 'string', 'max:64'],
            'updated_at_esperado' => ['nullable', 'date'],
            'comentarios_drive' => ['nullable', 'string'],
            'prioridad_md' => ['sometimes', 'boolean'],
            'solicitud_preparacion' => ['sometimes', 'nullable', 'array'],
            'solicitud_preparacion.version' => ['nullable', 'integer', 'in:1'],
            'solicitud_preparacion.codigo_modalidad' => ['nullable', 'string', Rule::in(CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES)],
            'solicitud_preparacion.tipo_referencia' => ['nullable', 'string', Rule::in([
                PedidoBmaReferencia::TIPO_COTIZACION,
                PedidoBmaReferencia::TIPO_PEDIDO,
                PedidoBmaReferencia::TIPO_REMISION,
                PedidoBmaReferencia::TIPO_OTRO,
            ])],
            'solicitud_preparacion.folio_referencia' => ['nullable', 'string', 'max:64'],
            'solicitud_preparacion.traslado_interno_pdv' => ['nullable', 'boolean'],
            'solicitud_preparacion.entrega_municipal' => ['nullable', 'array'],
            'solicitud_preparacion.entrega_municipal.destinatario_es_cliente' => ['nullable', 'boolean'],
            'solicitud_preparacion.entrega_municipal.destinatario_nombre' => ['nullable', 'string', 'max:255'],
            'solicitud_preparacion.entrega_municipal.destinatario_telefono' => ['nullable', 'string', 'max:40'],
            'solicitud_preparacion.entrega_municipal.municipio_destino' => ['nullable', 'string', 'max:255'],
            'solicitud_preparacion.entrega_municipal.direccion_referencia' => ['nullable', 'string', 'max:2000'],
            'solicitud_preparacion.entrega_municipal.catalogo_paqueteria_id' => ['nullable', 'integer', 'exists:catalogo_paqueterias_pedido,id'],
            'solicitud_preparacion.entrega_municipal.modalidad_cobro' => ['nullable', 'string', Rule::in([
                PedidoBmaCaratula::COBRO_PAGADO,
                PedidoBmaCaratula::COBRO_POR_COBRAR,
            ])],
            'comprobantes' => ['nullable', 'array'],
            'comprobantes.*' => ['file', 'image', 'max:10240'],
            'enviar' => ['nullable', 'boolean'],
        ];
    }
}
