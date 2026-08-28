<?php

namespace App\Services\Facturas;

use App\Models\Cliente;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;

class ActualizarDatosFiscalesSolicitudService
{
    public function __construct(
        private GestionarDatosFiscalesClienteService $gestionarDatosFiscales,
        private GestionarReceptorFiscalService $gestionarReceptor,
    ) {}

    /**
     * @param  array<string, mixed>  $parcial
     * @return list<string> claves efectivamente actualizadas
     */
    public function mergeEnSolicitud(SolicitudFactura $solicitud, array $parcial): array
    {
        $permitidos = EnlaceDatosFiscales::CAMPOS;
        $actualizados = [];
        $snapshot = is_array($solicitud->datos_fiscales) ? $solicitud->datos_fiscales : [];

        foreach ($parcial as $clave => $valor) {
            if (! in_array($clave, $permitidos, true)) {
                continue;
            }
            $texto = trim((string) $valor);
            if ($texto === '') {
                continue;
            }
            $snapshot[$clave] = $texto;
            $actualizados[] = $clave;
        }

        if ($actualizados === []) {
            return [];
        }

        $updates = ['datos_fiscales' => $snapshot];
        if (! empty($snapshot['nombre_razon_social'])) {
            $updates['razon_social'] = $snapshot['nombre_razon_social'];
        }

        $solicitud->update($updates);

        if ($solicitud->destinatario_tipo === SolicitudFactura::DESTINATARIO_CLIENTE && $solicitud->cliente_id) {
            $cliente = Cliente::query()->whereKey($solicitud->cliente_id)->first();
            if ($cliente) {
                $merge = [
                    'rfc' => $cliente->rfc,
                    'codigo_postal' => $cliente->codigo_postal,
                    'regimen_fiscal' => $cliente->regimen_fiscal,
                    'correo_electronico' => $cliente->correo_electronico,
                    'uso_factura' => $cliente->uso_factura,
                    'nombre_razon_social' => $cliente->nombre_razon_social,
                    'telefono' => $cliente->telefono,
                ];
                foreach ($actualizados as $clave) {
                    $merge[$clave] = $snapshot[$clave];
                }
                $this->gestionarDatosFiscales->actualizar($cliente, $merge);
            }
        } elseif ($solicitud->destinatario_tipo === SolicitudFactura::DESTINATARIO_TERCERO) {
            $receptor = $this->gestionarReceptor->upsertDesdeFormulario(
                $solicitud->receptor_fiscal_id ? (int) $solicitud->receptor_fiscal_id : null,
                $snapshot,
            );
            $solicitud->update(['receptor_fiscal_id' => $receptor->id]);
        }

        return $actualizados;
    }
}
