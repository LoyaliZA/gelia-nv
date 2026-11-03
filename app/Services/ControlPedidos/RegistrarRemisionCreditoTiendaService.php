<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** La remisión a crédito es posterior a la autorización. No entrega ni traslada. */
final class RegistrarRemisionCreditoTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, string $numero, UploadedFile $archivo): PedidoBma
    {
        $numero = trim($numero);
        if ($numero === '') {
            throw ValidationException::withMessages(['numero' => 'Escriba el número de remisión.']);
        }
        if ($archivo->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw ValidationException::withMessages(['archivo' => 'La remisión debe ser un PDF.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $numero, $archivo) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $credito = ($json['credito_wizerp']['resultado'] ?? null) === 'aprobado';
            $anexo = $pedido->pedido_principal_id !== null && is_array($json['cobertura_tienda'] ?? null);
            if (! $credito && ! $anexo) {
                throw ValidationException::withMessages([
                    'credito' => 'Solo se remisiona un crédito aprobado o un anexo con cobertura. El bloqueado no sigue.',
                ]);
            }
            if (($json['credito_wizerp']['resultado'] ?? null) === 'bloqueado') {
                throw ValidationException::withMessages(['credito' => 'El crédito bloqueado no tiene remisión.']);
            }
            if (filled($pedido->numero_remision) && $pedido->numero_remision !== $numero) {
                throw ValidationException::withMessages(['numero' => 'La remisión ya existe y no se reescribe.']);
            }

            if (! filled($pedido->numero_remision)) {
                $ruta = $archivo->store('pedidos_bma/remisiones/'.$pedido->id, 'public');
                $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_REMISION,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType() ?: 'application/pdf',
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => 0,
                    'activo' => true,
                ]);
                $pedido->update(['numero_remision' => $numero]);
                $estatusId = $pedido->catalogo_estatus_pedido_id;
                if ($estatusId) {
                    $this->historial->ejecutar(
                        $pedido->id,
                        $usuario->id,
                        $estatusId,
                        $estatusId,
                        "Remisión {$numero} registrada. Falta preparación y, si aplica, el traslado.",
                        AccionesHistorialPedidoBma::CARGA_REMISION
                    );
                }
                $this->notificar->ejecutar(
                    $pedido->fresh(['cliente', 'vendedor']),
                    'pedido_remision_credito',
                    "Remisión {$numero} lista. Siguiente: preparar y entregar.",
                    ['control_pedidos.tienda.ver'],
                    $usuario->id,
                    true
                );
            }

            return $pedido->fresh();
        });
    }
}
