<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\FormPublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

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

        $registro = $this->registroCanonico($sucursalId);

        return $this->serializarRegistro($registro, $sucursalId);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerEnlace(User $actor, int $sucursalId, CarbonInterface $ahora): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            $registro = $this->registroCanonico($sucursalId, true);

            if ($registro instanceof PdvPantallaSalaToken) {
                if ($registro->estado !== PdvPantallaSalaToken::ESTADO_ACTIVA) {
                    $registro->update(['estado' => PdvPantallaSalaToken::ESTADO_ACTIVA]);
                    $this->auditoria->registrar(
                        $sucursalId,
                        $actor->id,
                        'activacion',
                        $ahora,
                        ['token_id' => $registro->id],
                        $registro->id,
                    );
                    $registro = $registro->fresh();
                }

                return $this->serializarRegistro($registro, $sucursalId);
            }

            $generado = $this->resolver->generarTokenPlano();

            $registro = PdvPantallaSalaToken::query()->create([
                'sucursal_id' => $sucursalId,
                'token_publico' => $generado['token'],
                'codigo_corto' => $this->resolver->generarCodigoCorto(),
                'token_hash' => $generado['hash'],
                'estado' => PdvPantallaSalaToken::ESTADO_ACTIVA,
                'expira_en' => null,
                'creado_por' => $actor->id,
            ]);

            $this->auditoria->registrar(
                $sucursalId,
                $actor->id,
                'generacion',
                $ahora,
                ['token_id' => $registro->id],
                $registro->id,
            );

            return $this->serializarRegistro($registro, $sucursalId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function activar(User $actor, int $sucursalId, CarbonInterface $ahora): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            $registro = $this->registroCanonico($sucursalId, true);

            if (! $registro instanceof PdvPantallaSalaToken) {
                return $this->obtenerEnlace($actor, $sucursalId, $ahora);
            }

            if ($registro->estado !== PdvPantallaSalaToken::ESTADO_ACTIVA) {
                $registro->update(['estado' => PdvPantallaSalaToken::ESTADO_ACTIVA]);
                $this->auditoria->registrar(
                    $sucursalId,
                    $actor->id,
                    'activacion',
                    $ahora,
                    ['token_id' => $registro->id],
                    $registro->id,
                );
                $registro = $registro->fresh();
            }

            return $this->serializarRegistro($registro, $sucursalId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function desactivar(User $actor, int $sucursalId, CarbonInterface $ahora): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            $registro = $this->registroCanonico($sucursalId, true);

            if (! $registro instanceof PdvPantallaSalaToken) {
                return $this->serializarRegistro(null, $sucursalId);
            }

            if ($registro->estado === PdvPantallaSalaToken::ESTADO_ACTIVA) {
                $registro->update(['estado' => PdvPantallaSalaToken::ESTADO_INACTIVA]);
                $this->auditoria->registrar(
                    $sucursalId,
                    $actor->id,
                    'desactivacion',
                    $ahora,
                    ['token_id' => $registro->id],
                    $registro->id,
                );
                $registro = $registro->fresh();
            }

            return $this->serializarRegistro($registro, $sucursalId);
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

    private function registroCanonico(int $sucursalId, bool $bloquear = false): ?PdvPantallaSalaToken
    {
        $query = PdvPantallaSalaToken::query()
            ->where('sucursal_id', $sucursalId);

        if ($bloquear) {
            $query->lockForUpdate();
        }

        $registro = $query->first();

        return $registro instanceof PdvPantallaSalaToken ? $registro : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarRegistro(?PdvPantallaSalaToken $registro, int $sucursalId): array
    {
        if (! $registro instanceof PdvPantallaSalaToken) {
            return [
                'sucursal_id' => $sucursalId,
                'enlace_activo' => false,
                'url' => null,
                'url_kiosko' => null,
                'url_corta' => null,
                'url_kiosko_corta' => null,
                'codigo_corto' => null,
                'ultimo_acceso_at' => null,
            ];
        }

        $registro = $this->asegurarCodigoCorto($registro);
        $codigo = (string) $registro->codigo_corto;

        return [
            'sucursal_id' => (int) $registro->sucursal_id,
            'enlace_activo' => $registro->estado === PdvPantallaSalaToken::ESTADO_ACTIVA,
            'url' => $this->resolver->urlPublicaDesdeToken((string) $registro->token_publico),
            'url_kiosko' => FormPublicUrl::salaPublicidadShow((string) $registro->token_publico),
            'url_corta' => $codigo !== '' ? FormPublicUrl::salaTurnosCorta($codigo) : null,
            'url_kiosko_corta' => $codigo !== '' ? FormPublicUrl::salaPublicidadCorta($codigo) : null,
            'codigo_corto' => $codigo !== '' ? $codigo : null,
            'ultimo_acceso_at' => $registro->ultimo_acceso_at?->toIso8601String(),
        ];
    }

    private function asegurarCodigoCorto(PdvPantallaSalaToken $registro): PdvPantallaSalaToken
    {
        if (is_string($registro->codigo_corto) && $registro->codigo_corto !== '') {
            return $registro;
        }

        $registro->update(['codigo_corto' => $this->resolver->generarCodigoCorto()]);

        return $registro->fresh() ?? $registro;
    }
}
