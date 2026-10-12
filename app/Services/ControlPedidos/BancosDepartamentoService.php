<?php

namespace App\Services\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\CatalogoBancoDepartamento;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BancosDepartamentoService
{
    public function disponibles(?int $departamentoId)
    {
        $query = CatalogoBanco::query()->where('activo', true)->orderBy('nombre');
        if (! Schema::hasColumn('catalogo_bancos', 'compartido')) {
            return $query->get();
        }

        return $query->where(fn ($q) => $q->where('compartido', true)
            ->when($departamentoId, fn ($q) => $q->orWhereHas('departamentos', fn ($r) => $r->where('departamento_id', $departamentoId)->where('activo', true))))
            ->with('departamentos')->get();
    }

    public function validar(PedidoBma $pedido, ?int $bancoId): void
    {
        if (! $bancoId) {
            return;
        }
        $departamentoId = ContextoDepartamentoPedido::dePedido($pedido);
        if (! $this->disponibles($departamentoId)->contains('id', $bancoId)) {
            throw ValidationException::withMessages(['catalogo_banco_id' => 'Este banco no está disponible para el departamento del pedido.']);
        }
    }

    public function guardar(CatalogoBanco $banco, array $datos): CatalogoBanco
    {
        return DB::transaction(function () use ($banco, $datos) {
            $banco->fill(collect($datos)->only(['nombre', 'activo', 'compartido'])->all())->save();
            if (array_key_exists('departamento_ids', $datos)) {
                $banco->departamentos()->delete();
                foreach (array_unique($datos['departamento_ids']) as $orden => $id) {
                    CatalogoBancoDepartamento::create(['catalogo_banco_id' => $banco->id, 'departamento_id' => $id, 'activo' => true, 'orden' => $orden]);
                }
            }

            return $banco;
        });
    }
}
