<?php

namespace App\Services\SaldosAFavor;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\BancosDepartamentoService;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use InvalidArgumentException;

/** Destino de un cobro hecho en tienda. Conserva el medio y no altera otros módulos. */
final class ResolverBancoCajaPdv
{
    public const NOMBRE = 'Caja PDV';

    public const ERROR_CONFIGURACION = 'No hay una Caja PDV activa y permitida para este departamento. El cobro en tienda no se guardó.';

    public const ERROR_TRANSFERENCIA = 'El cobro en tienda no se registra como transferencia. Indique el medio real; el destino es Caja PDV.';

    public function esCobroEnTienda(PedidoBma $pedido, ?string $forma): bool
    {
        if ($forma === null || $forma === '' || $forma === 'credito') {
            return false;
        }
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return ($json['cobertura_tienda']['tipo'] ?? null) === 'apartado';
    }

    public function destino(?int $bancoId, PedidoBma $pedido, ?string $forma): ?int
    {
        if (! $this->esCobroEnTienda($pedido, $forma)) {
            return $bancoId;
        }
        if ($forma === 'transferencia') {
            throw new InvalidArgumentException(self::ERROR_TRANSFERENCIA);
        }

        $banco = $this->unico($pedido);
        if (! $banco) {
            throw new InvalidArgumentException(self::ERROR_CONFIGURACION);
        }

        return (int) $banco->id;
    }

    /** @return array{nombre: string, id: int|null, disponible: bool} */
    public function estado(PedidoBma $pedido): array
    {
        $banco = $this->unico($pedido);

        return [
            'nombre' => self::NOMBRE,
            'id' => $banco ? (int) $banco->id : null,
            'disponible' => $banco !== null,
        ];
    }

    private function unico(PedidoBma $pedido): ?CatalogoBanco
    {
        $coincidencias = app(BancosDepartamentoService::class)
            ->disponibles(ContextoDepartamentoPedido::dePedido($pedido))
            ->filter(fn ($banco) => mb_strtolower(trim((string) $banco->nombre)) === mb_strtolower(self::NOMBRE))
            ->values();

        return $coincidencias->count() === 1 ? $coincidencias->first() : null;
    }
}
