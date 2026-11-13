<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConflictoImportacionCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverConflictoImportacion
{
    public function __construct(private ResolucionListaWizerp $listas) {}

    public function resolver(User $actor, ConflictoImportacionCliente $conflicto, string $decision): void
    {
        if (! in_array($decision, ['conservar', 'aplicar'], true)) {
            throw ValidationException::withMessages(['decision' => 'La decisión no es válida.']);
        }

        DB::transaction(function () use ($actor, $conflicto, $decision) {
            $fila = ConflictoImportacionCliente::query()->whereKey($conflicto->id)->lockForUpdate()->first();
            if (! $fila || $fila->estado === ConflictoImportacionCliente::RESUELTO) {
                return;
            }
            $cliente = $fila->cliente_id
                ? Cliente::query()->whereKey($fila->cliente_id)->lockForUpdate()->first()
                : null;
            if (! $this->vigenteCoincide($cliente, $fila->valor_vigente ?? [])) {
                throw ValidationException::withMessages([
                    'conflicto' => 'El cliente cambió después de la revisión. Hay que volver a armar el conflicto.',
                ]);
            }
            if ($decision === 'aplicar') {
                $this->aplicar($cliente, $fila);
            }
            $fila->forceFill([
                'estado' => ConflictoImportacionCliente::RESUELTO,
                'decision' => $decision,
                'resuelto_por' => $actor->id,
                'resuelto_at' => now(),
            ])->save();
        });
    }

    private function vigenteCoincide(?Cliente $cliente, array $vigente): bool
    {
        if (! $cliente) {
            return $vigente === [];
        }

        return (int) ($vigente['lista_actual_id'] ?? 0) === (int) $cliente->lista_actual_id
            && (bool) ($vigente['es_menudeo'] ?? false) === (bool) $cliente->es_menudeo
            && (bool) ($vigente['es_inactivo'] ?? false) === (bool) $cliente->es_inactivo;
    }

    private function aplicar(?Cliente $cliente, ConflictoImportacionCliente $fila): void
    {
        if (! $cliente) {
            throw ValidationException::withMessages(['conflicto' => 'No hay cliente para aplicar el cambio.']);
        }
        if ($cliente->lista_bloqueada && in_array($fila->alcance, ['lista', 'conversion', 'fila'], true)) {
            throw ValidationException::withMessages(['conflicto' => 'La lista protegida no se reescribe.']);
        }
        $listas = CatalogoListaDescuento::query()->where('activo', true)->get();
        $codigo = (string) ($fila->valor_propuesto['codigo_lista'] ?? '');
        $listaId = $codigo === ''
            ? $this->listas->resolverListaId($listas, 'PG')
            : $this->listas->resolverListaId($listas, $codigo);
        if (in_array($fila->alcance, ['lista', 'conversion', 'fila'], true)) {
            if (! $listaId) {
                throw ValidationException::withMessages(['conflicto' => 'El código propuesto no existe en el catálogo.']);
            }
            $cliente->lista_actual_id = $listaId;
        }
        if ($fila->alcance === 'conversion') {
            app(ClasificacionComercialCliente::class)->asignar($cliente, false, ClasificacionComercialCliente::ORIGEN_IMPORTACION);
        }
        if ($fila->alcance === 'reactivacion') {
            $cliente->es_inactivo = false;
        }
        $cliente->save();
    }
}
