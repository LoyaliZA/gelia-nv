<?php

namespace App\Services\ApiExterna;

use App\Models\ApiAplicacion;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiAplicacionService
{
    public function __construct(
        protected ApiPermisoService $permisoService
    ) {}

    public function crear(array $datos, int $creadoPor): array
    {
        $clientId = 'gel_' . Str::lower(Str::random(24));
        $clientSecret = Str::random(48);

        $aplicacion = ApiAplicacion::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'activa' => $datos['activa'] ?? true,
            'ips_permitidas' => $this->normalizarIps($datos['ips_permitidas'] ?? null),
            'limite_por_minuto' => $datos['limite_por_minuto'] ?? 60,
            'creado_por' => $creadoPor,
        ]);

        $this->permisoService->sincronizarPermisosAplicacion($aplicacion);

        return [
            'aplicacion' => $aplicacion,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    public function regenerarSecret(ApiAplicacion $aplicacion): string
    {
        $clientSecret = Str::random(48);
        $aplicacion->tokens()->delete();
        $aplicacion->update(['client_secret' => $clientSecret]);

        return $clientSecret;
    }

    public function revocarTokens(ApiAplicacion $aplicacion): void
    {
        $aplicacion->tokens()->delete();
    }

    public function validarCredenciales(string $clientId, string $clientSecret): ?ApiAplicacion
    {
        $aplicacion = ApiAplicacion::where('client_id', $clientId)->first();

        if (!$aplicacion || !$aplicacion->activa) {
            return null;
        }

        if (!Hash::check($clientSecret, $aplicacion->client_secret)) {
            return null;
        }

        return $aplicacion;
    }

    public function emitirToken(ApiAplicacion $aplicacion): array
    {
        $aplicacion->tokens()->delete();

        $tokenResult = $aplicacion->createToken(
            'api-externa',
            ['*'],
            now()->addHours(24)
        );

        return [
            'access_token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ];
    }

    private function normalizarIps(?array $ips): ?array
    {
        if (empty($ips)) {
            return null;
        }

        $filtradas = array_values(array_filter(array_map('trim', $ips)));

        return empty($filtradas) ? null : $filtradas;
    }
}
