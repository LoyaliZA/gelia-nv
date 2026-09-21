<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Exceptions\PuntoVenta\PantallaSalaInactivaException;
use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Support\FormPublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ResolverTokenPantallaSalaPdvService
{
    public function __construct(
        private readonly RegistrarAuditoriaPantallaSalaPdvService $auditoria,
    ) {}

    public function resolver(string $tokenPlano, CarbonInterface $ahora, bool $auditarAcceso = false): PdvPantallaSalaToken
    {
        $tokenPlano = trim($tokenPlano);
        if ($tokenPlano === '') {
            throw new NotFoundHttpException();
        }

        $registro = PdvPantallaSalaToken::query()
            ->where('token_publico', $tokenPlano)
            ->first();

        if (! $registro instanceof PdvPantallaSalaToken) {
            throw new NotFoundHttpException();
        }

        if ($registro->estado !== PdvPantallaSalaToken::ESTADO_ACTIVA) {
            throw new PantallaSalaInactivaException($registro);
        }

        $registro->update(['ultimo_acceso_at' => $ahora]);

        if ($auditarAcceso) {
            $this->auditoria->registrar(
                (int) $registro->sucursal_id,
                null,
                'acceso_publico',
                $ahora,
                [],
                $registro->id,
            );
        }

        return $registro->fresh();
    }

    public function urlPublicaDesdeToken(string $tokenPlano): string
    {
        return FormPublicUrl::salaTurnosShow($tokenPlano);
    }

    /**
     * @return array{token: string, hash: string}
     */
    public function generarTokenPlano(): array
    {
        $alfabeto = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $longitud = max(16, (int) config('pdv_pantalla_sala.token.longitud', 32));
        $max = strlen($alfabeto) - 1;

        for ($intento = 0; $intento < 20; $intento++) {
            $token = '';
            for ($i = 0; $i < $longitud; $i++) {
                $token .= $alfabeto[random_int(0, $max)];
            }

            $hash = hash('sha256', $token);
            $existe = PdvPantallaSalaToken::query()
                ->where(function ($q) use ($hash, $token): void {
                    $q->where('token_hash', $hash)
                        ->orWhere('token_publico', $token);
                })
                ->exists();

            if (! $existe) {
                return ['token' => $token, 'hash' => $hash];
            }
        }

        $token = Str::lower(Str::random($longitud));

        return ['token' => $token, 'hash' => hash('sha256', $token)];
    }
}
