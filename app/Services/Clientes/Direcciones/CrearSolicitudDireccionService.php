<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\SolicitudDireccion;
use App\Models\SolicitudDireccion as Solicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearSolicitudDireccionService
{
    public function __construct(
        private ValidadorIdentidadCliente $identidad,
        private DetectorDireccionDuplicada $detector,
        private ValidarEnlaceDireccionService $validadorEnlace,
        private ServicioAuditoriaDireccion $auditoria,
        private NormalizadorDireccion $normalizador,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ejecutar(array $payload, ?string $ip = null): SolicitudDireccion
    {
        $claveRate = 'solicitud-direccion:'.($ip ?? 'unknown');
        if (RateLimiter::tooManyAttempts($claveRate, 10)) {
            throw ValidationException::withMessages([
                'token' => 'Demasiados intentos. Intente más tarde.',
            ]);
        }
        RateLimiter::hit($claveRate, 60);

        return DB::transaction(function () use ($payload) {
            $enlace = null;
            $clienteId = null;

            if (! empty($payload['token'])) {
                try {
                    $enlace = $this->validadorEnlace->ejecutar(
                        (string) $payload['token'],
                        $payload['accion_solicitada'] ?? null
                    );
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'accion_solicitada' => $e->getMessage(),
                    ]);
                }
                $clienteId = $enlace->cliente_id;
                $this->validadorEnlace->marcarUsado($enlace);
            }

            $estado = Solicitud::ESTADO_PENDING;
            $clienteCoincidente = null;
            $numeroDeclarado = isset($payload['numero_cliente'])
                ? trim((string) $payload['numero_cliente'])
                : null;

            if ($clienteId === null && $numeroDeclarado !== null && $numeroDeclarado !== '') {
                $clienteCoincidente = $this->identidad->buscarPorNumeroExacto($numeroDeclarado);
                if ($clienteCoincidente
                    && $this->identidad->coincideSegundoFactor(
                        $clienteCoincidente,
                        $payload['correo_declarado'] ?? null,
                        $payload['telefono_declarado'] ?? null,
                    )) {
                    $clienteId = $clienteCoincidente->id;
                    $estado = Solicitud::ESTADO_VERIFIED;
                } else {
                    $estado = Solicitud::ESTADO_IDENTITY_REVIEW;
                    $posibles = $this->identidad->posiblesCoincidencias($numeroDeclarado);
                    if ($clienteCoincidente === null && $posibles !== []) {
                        $clienteCoincidente = $posibles[0];
                    }
                }
            } elseif ($clienteId !== null) {
                $estado = Solicitud::ESTADO_VERIFIED;
                $clienteCoincidente = Cliente::query()->find($clienteId);
            }

            $datos = $this->normalizador->ejecutar($payload['datos_direccion'] ?? $payload);

            if ($clienteId && $this->detector->existe($clienteId, $datos, $payload['direccion_seleccionada_id'] ?? null)) {
                $estado = Solicitud::ESTADO_POSSIBLE_DUPLICATE;
            }

            $solicitud = SolicitudDireccion::query()->create([
                'folio' => $this->generarFolio(),
                'token_publico_id' => $enlace?->id,
                'numero_cliente_declarado' => $numeroDeclarado,
                'cliente_coincidente_id' => $clienteId ?? $clienteCoincidente?->id,
                'accion_solicitada' => $payload['accion_solicitada'] ?? Solicitud::ACCION_ADICIONAL,
                'direccion_seleccionada_id' => $payload['direccion_seleccionada_id'] ?? $enlace?->direccion_id,
                'nombre_declarado' => $payload['nombre_declarado'] ?? null,
                'telefono_declarado' => $payload['telefono_declarado'] ?? null,
                'correo_declarado' => $payload['correo_declarado'] ?? null,
                'datos_solicitados_json' => $datos,
                'anexa_remision' => (bool) ($payload['anexa_remision'] ?? false),
                'archivo_remision' => $payload['archivo_remision'] ?? null,
                'estado' => $estado,
                'estado_remision' => ! empty($payload['archivo_remision'])
                    ? Solicitud::REMISION_PENDING_ORDER_LINK
                    : null,
            ]);

            if ($solicitud->cliente_coincidente_id) {
                $this->auditoria->ejecutar(
                    $solicitud->cliente_coincidente_id,
                    'crear_solicitud',
                    null,
                    $solicitud->direccion_seleccionada_id,
                    $solicitud->id,
                    null,
                    ['folio' => $solicitud->folio, 'estado' => $solicitud->estado],
                    'public_form',
                );
            }

            return $solicitud;
        });
    }

    private function generarFolio(): string
    {
        do {
            $folio = 'SD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (SolicitudDireccion::query()->where('folio', $folio)->exists());

        return $folio;
    }
}
