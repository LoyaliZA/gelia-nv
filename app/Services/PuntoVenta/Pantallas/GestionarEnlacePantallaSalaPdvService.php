<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarEnlacePantallaSalaPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ResolverTokenPantallaSalaPdvService $resolver,
        private readonly RegistrarAuditoriaPantallaSalaPdvService $auditoria,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function consultar(User $actor, int $sucursalId, CarbonInterface $ahora): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        $activo = $this->tokenActivo($sucursalId);

        return [
            'sucursal_id' => $sucursalId,
            'enlace_activo' => $activo instanceof PdvPantallaSalaToken,
            'expira_en' => $activo?->expira_en?->toIso8601String(),
            'ultimo_acceso_at' => $activo?->ultimo_acceso_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerOGenerar(User $actor, int $sucursalId, CarbonInterface $ahora, bool $regenerar = false): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $ahora, $regenerar): array {
            $activo = $this->tokenActivo($sucursalId, true);

            if ($activo instanceof PdvPantallaSalaToken && ! $regenerar) {
                throw ValidationException::withMessages([
                    'enlace' => 'Ya existe un enlace activo para esta sucursal. Regenerar invalidará el acceso actual de la TV.',
                ]);
            }

            if ($activo instanceof PdvPantallaSalaToken) {
                $this->revocarRegistro($activo, $actor, $ahora, 'regeneracion');
            }

            $generado = $this->resolver->generarTokenPlano();
            $vigenciaDias = max(1, (int) config('pdv_pantalla_sala.token.vigencia_dias', 365));

            $registro = PdvPantallaSalaToken::query()->create([
                'sucursal_id' => $sucursalId,
                'token_hash' => $generado['hash'],
                'estado' => PdvPantallaSalaToken::ESTADO_ACTIVA,
                'expira_en' => $ahora->copy()->addDays($vigenciaDias),
                'creado_por' => $actor->id,
            ]);

            $tipo = $activo instanceof PdvPantallaSalaToken ? 'regeneracion' : 'generacion';
            $this->auditoria->registrar(
                $sucursalId,
                $actor->id,
                $tipo,
                $ahora,
                ['token_id' => $registro->id],
                $registro->id,
            );

            return [
                'sucursal_id' => $sucursalId,
                'url' => $this->resolver->urlPublicaDesdeToken($generado['token']),
                'expira_en' => $registro->expira_en?->toIso8601String(),
                'regenerado' => $activo instanceof PdvPantallaSalaToken,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function revocar(User $actor, int $sucursalId, CarbonInterface $ahora): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            $activo = $this->tokenActivo($sucursalId, true);

            if (! $activo instanceof PdvPantallaSalaToken) {
                return [
                    'sucursal_id' => $sucursalId,
                    'revocado' => false,
                ];
            }

            $this->revocarRegistro($activo, $actor, $ahora, 'revocacion_manual');

            return [
                'sucursal_id' => $sucursalId,
                'revocado' => true,
            ];
        });
    }

    private function asegurarAutorizado(User $actor, int $sucursalId): void
    {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
            $sucursalId,
        );
    }

    private function tokenActivo(int $sucursalId, bool $bloquear = false): ?PdvPantallaSalaToken
    {
        $query = PdvPantallaSalaToken::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', PdvPantallaSalaToken::ESTADO_ACTIVA)
            ->whereNull('revocado_en')
            ->where(function ($q): void {
                $q->whereNull('expira_en')->orWhere('expira_en', '>', now());
            })
            ->orderByDesc('id');

        if ($bloquear) {
            $query->lockForUpdate();
        }

        $registro = $query->first();

        return $registro instanceof PdvPantallaSalaToken && $registro->estaVigente()
            ? $registro
            : null;
    }

    private function revocarRegistro(
        PdvPantallaSalaToken $registro,
        User $actor,
        CarbonInterface $ahora,
        string $motivo,
    ): void {
        $registro->update([
            'estado' => PdvPantallaSalaToken::ESTADO_REVOCADA,
            'revocado_en' => $ahora,
        ]);

        $this->auditoria->registrar(
            (int) $registro->sucursal_id,
            $actor->id,
            'revocacion',
            $ahora,
            ['motivo' => $motivo],
            $registro->id,
        );
    }
}
