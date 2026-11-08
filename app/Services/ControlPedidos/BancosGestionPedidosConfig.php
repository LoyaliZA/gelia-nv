<?php

namespace App\Services\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Política exclusiva de pedidos; no modifica el catálogo compartido con otros módulos. */
final class BancosGestionPedidosConfig
{
    public const CLAVE = 'control_pedidos.bancos';

    public function obtener(): ?array
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return null;
        }
        $valor = ConfiguracionSistema::where('clave', self::CLAVE)->value('valor');

        return $valor === null ? null : (json_decode($valor, true) ?? []);
    }

    public function catalogo(?array $departamentoIds = null): Collection
    {
        $reglas = $this->obtener();

        return $this->configurables($reglas)->filter(fn ($banco) => $banco['activo'] && $banco['habilitado']
            && ($departamentoIds === null || $banco['compartido'] || array_intersect($banco['departamento_ids'], $departamentoIds)))
            ->values()->map(fn ($banco) => collect($banco)->only(['id', 'nombre', 'compartido', 'departamento_ids'])->all());
    }

    public function configurables(?array $reglas = null): Collection
    {
        $reglas ??= $this->obtener();
        $porId = collect($reglas ?? [])->keyBy('id');

        return CatalogoBanco::with('departamentos')->orderBy('nombre')->get()->map(function ($banco) use ($reglas, $porId) {
            $regla = $porId->get($banco->id);

            return [
                'id' => $banco->id, 'nombre' => $banco->nombre, 'activo' => (bool) $banco->activo,
                'habilitado' => $reglas === null ? (bool) $banco->activo : (bool) ($regla['habilitado'] ?? false),
                'compartido' => $reglas === null ? ($banco->compartido ?? true) : (bool) ($regla['compartido'] ?? false),
                'departamento_ids' => $reglas === null
                    ? $banco->departamentos->where('activo', true)->pluck('departamento_id')->map(fn ($id) => (int) $id)->values()->all()
                    : array_map('intval', $regla['departamento_ids'] ?? []),
            ];
        });
    }

    public function validar(PedidoBma $pedido, ?int $bancoId): void
    {
        if (! $bancoId) {
            return;
        }
        if (! $this->catalogo([ContextoDepartamentoPedido::dePedido($pedido)])->contains('id', $bancoId)) {
            throw ValidationException::withMessages(['catalogo_banco_id' => 'Este banco no está habilitado en Gestión de pedidos para el departamento del pedido.']);
        }
    }

    public function guardar(array $bancos): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => self::CLAVE], [
            'valor' => json_encode(array_values($bancos)), 'tipo' => 'json', 'grupo' => 'ControlPedidos',
            'descripcion' => 'Bancos habilitados y departamentos de Gestión de pedidos',
        ]);
    }
}
