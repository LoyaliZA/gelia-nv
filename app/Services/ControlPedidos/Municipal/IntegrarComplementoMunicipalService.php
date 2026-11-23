<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\Municipal\ComplementosMunicipales;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Validation\ValidationException;

/**
 * M-03b: confirmación física de las piezas adicionales. La confirma el área que tiene el paquete: CEDIS si el principal
 * sigue allí, PDV si está en su custodia. Nunca en tránsito ni después de la salida a terminal. Ventas no la confirma.
 * Usa su propia comprobación de salida (`envioYaSalioParaAjustes`), no `envioYaSalio()`: el traslado CEDIS→PDV no la cierra.
 */
final class IntegrarComplementoMunicipalService
{
    public const UBICACION_CEDIS = 'cedis';

    public const UBICACION_PDV = 'pdv';

    public const UBICACION_TRANSITO = 'transito';

    public function __construct(private RegistrarHistorialPedidoService $historial) {}

    /** Dónde está hoy el paquete del principal, por hechos físicos (resguardo PDV), no por el estatus. */
    public static function ubicacion(PedidoBma $principal): string
    {
        $estados = ResguardoPdv::query()->where('pedido_bma_id', $principal->id)->pluck('estado')->all();
        if (in_array(ResguardoPdv::ESTADO_EN_CUSTODIA, $estados, true)) {
            return self::UBICACION_PDV;
        }
        if (array_intersect($estados, [ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, ResguardoPdv::ESTADO_EN_RECEPCION]) !== []) {
            return self::UBICACION_TRANSITO;
        }

        return self::UBICACION_CEDIS;
    }

    public function ejecutar(PedidoBma $principal, PedidoBma $complemento, User $usuario): PedidoBma
    {
        return ExclusividadRemisionEnvio::conLockEnvio($principal, $complemento, function ($bloqueados) use ($principal, $complemento, $usuario) {
            $principal = $bloqueados->get($principal->id);
            $complemento = $bloqueados->get($complemento->id);
            if (! ComplementosMunicipales::es($complemento) || (int) $complemento->pedido_principal_id !== (int) $principal->id) {
                throw ValidationException::withMessages(['complemento' => 'Ese pedido no es una pieza adicional de este envío.']);
            }
            if (ComplementosMunicipales::estado($complemento) === ComplementosMunicipales::ESTADO_INTEGRADO) {
                return $complemento->fresh();
            }
            if ($complemento->cancelado_at || $principal->cancelado_at) {
                throw ValidationException::withMessages(['complemento' => 'El pedido está cancelado.']);
            }
            if (RecorridoFisicoMunicipal::envioYaSalioParaAjustes($principal)) {
                throw ValidationException::withMessages(['complemento' => RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO]);
            }
            if ((int) $complemento->cliente_id !== (int) $principal->cliente_id) {
                throw ValidationException::withMessages(['cliente' => 'Las piezas adicionales no pertenecen al cliente del envío.']);
            }
            if ($complemento->pago_validado_at === null || PoliticaResguardoEnvio::incidenciaPagoMiembro($complemento)) {
                throw ValidationException::withMessages(['pago' => 'Las piezas adicionales necesitan su propio pago verificado antes de integrarse.']);
            }
            if (! $complemento->tieneRemision() && ! filled($complemento->numero_remision)) {
                throw ValidationException::withMessages(['remision' => 'Las piezas adicionales necesitan su propia remisión antes de integrarse.']);
            }

            $ubicacion = self::ubicacion($principal);
            if ($ubicacion === self::UBICACION_TRANSITO) {
                throw ValidationException::withMessages(['ubicacion' => 'El paquete va en traslado a la sucursal. Se integra cuando se reciba.']);
            }
            $mecanismo = $ubicacion === self::UBICACION_PDV ? PoliticaResguardoEnvio::MECANISMO_LOCAL : PoliticaResguardoEnvio::MECANISMO_RESGUARDO;
            if (! PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $principal, $mecanismo)) {
                throw ValidationException::withMessages(['permiso' => $ubicacion === self::UBICACION_PDV
                    ? 'Solo el personal de la sucursal donde está el paquete confirma la integración.'
                    : 'Solo el área CEDIS confirma la integración de la mercancía.']);
            }

            $json = is_array($complemento->solicitud_preparacion_json) ? $complemento->solicitud_preparacion_json : [];
            $json[ComplementosMunicipales::CLAVE]['estado'] = ComplementosMunicipales::ESTADO_INTEGRADO;
            $json[ComplementosMunicipales::CLAVE]['integracion'] = [
                'ubicacion' => $ubicacion,
                'integrado_por_id' => $usuario->id,
                'integrado_at' => now()->toIso8601String(),
            ];
            $complemento->update(['solicitud_preparacion_json' => $json]);

            foreach ([$complemento, $principal] as $pedido) {
                if ($pedido->catalogo_estatus_pedido_id) {
                    $this->historial->ejecutar(
                        $pedido->id,
                        $usuario->id,
                        $pedido->catalogo_estatus_pedido_id,
                        $pedido->catalogo_estatus_pedido_id,
                        "Piezas adicionales {$complemento->folio} integradas en ".($ubicacion === self::UBICACION_PDV ? 'sucursal' : 'CEDIS').". No se duplica mercancía ni remisión.",
                        'complemento_municipal_integrado'
                    );
                }
            }

            return $complemento->fresh();
        });
    }
}
