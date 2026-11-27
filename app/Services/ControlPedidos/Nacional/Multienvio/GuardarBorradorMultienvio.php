<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Guarda (reemplazando) el borrador de envíos de un grupo en configuración.
 * No crea pedidos; la completitud y la suma se validan al confirmar.
 */
class GuardarBorradorMultienvio
{
    /** @param  array<int, array{uuid_operativo: string, folio_remision?: ?string, total_mercancia: float|int|string, contenido?: ?string}>  $envios */
    public function ejecutar(MultienvioNacional $grupo, int $usuarioId, array $envios): MultienvioNacional
    {
        $usuario = User::query()->findOrFail($usuarioId);

        return DB::transaction(function () use ($grupo, $usuario, $envios) {
            $origen = PedidoBma::query()->lockForUpdate()->findOrFail($grupo->pedido_origen_id);

            if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $origen)) {
                throw new AuthorizationException('No tienes autorización para configurar este pedido.');
            }

            $bloqueado = MultienvioNacional::query()->lockForUpdate()->findOrFail($grupo->id);

            if ($bloqueado->estado !== MultienvioNacional::ESTADO_CONFIGURANDO) {
                throw MultienvioNacionalException::noElegible('el multienvío no está en configuración.');
            }

            $activas = $origen->cajas()->activas()->pluck('uuid_operativo')->all();
            $vistos = [];
            $folios = [];
            $borrador = [];

            foreach ($envios as $envio) {
                $uuid = (string) ($envio['uuid_operativo'] ?? '');
                if (! in_array($uuid, $activas, true)) {
                    throw MultienvioNacionalException::cajaNoPertenece($uuid);
                }
                if (isset($vistos[$uuid])) {
                    throw new InvalidArgumentException('La caja '.$uuid.' aparece más de una vez en el borrador.');
                }
                $vistos[$uuid] = true;

                $folio = trim((string) ($envio['folio_remision'] ?? ''));
                if ($folio !== '') {
                    FolioWizerpMultienvio::asegurarDisponible($folio, $origen->id, $folios);
                }

                $contenido = trim((string) ($envio['contenido'] ?? ''));
                $borrador[] = [
                    'uuid_operativo' => $uuid,
                    'folio_remision' => $folio !== '' ? $folio : null,
                    'total_mercancia' => $this->monto($envio['total_mercancia'] ?? null),
                    'contenido' => $contenido !== '' ? $contenido : null,
                ];
            }

            $bloqueado->update(['borrador_json' => $borrador]);

            return $bloqueado->refresh();
        });
    }

    private function monto(mixed $valor): float
    {
        if (! is_numeric($valor) || (float) $valor < 0) {
            throw new InvalidArgumentException('total_mercancia debe ser un número mayor o igual a cero.');
        }

        return round((float) $valor, 2);
    }
}
