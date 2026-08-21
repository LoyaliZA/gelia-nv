<?php

namespace App\Services\ControlPedidos;

use App\Events\EvidenciaCedisActualizada;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaSesionEvidencia;
use App\Models\ControlPedidos\PedidoBmaSesionEvidenciaFoto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\FormPublicUrl;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SesionEvidenciaCedisService
{
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    /**
     * @return array{sesion: PedidoBmaSesionEvidencia, token: string, url: string, qr_data_uri: string, expira_en: string}
     */
    public function generar(PedidoBma $pedido, int $usuarioId): array
    {
        $this->revocarAbiertas($pedido, $usuarioId, 'Se generó un nuevo QR de evidencias.');

        $codigo = $this->generarCodigoUnico();
        $sesion = PedidoBmaSesionEvidencia::query()->create([
            'pedido_bma_id' => $pedido->id,
            'token_hash' => hash('sha256', $codigo),
            'codigo_publico' => $codigo,
            'estado' => PedidoBmaSesionEvidencia::ESTADO_PENDIENTE,
            'expira_en' => now()->addMinutes(PedidoBmaSesionEvidencia::TTL_MINUTOS),
            'creado_por' => $usuarioId,
            'snapshot_json' => ['productos' => [], 'cajas' => []],
        ]);

        $this->historial($pedido, $usuarioId, 'QR de evidencias generado (celular).');

        $url = FormPublicUrl::cedisEvidenciaShow($codigo);

        return [
            'sesion' => $sesion,
            'token' => $codigo,
            'url' => $url,
            'qr_data_uri' => $this->qrDataUri($url),
            'expira_en' => $sesion->expira_en->toIso8601String(),
        ];
    }

    public function vigente(PedidoBma $pedido): ?PedidoBmaSesionEvidencia
    {
        $sesion = PedidoBmaSesionEvidencia::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereIn('estado', [PedidoBmaSesionEvidencia::ESTADO_PENDIENTE, PedidoBmaSesionEvidencia::ESTADO_ACTIVA])
            ->latest('id')
            ->first();

        if (! $sesion) {
            return null;
        }

        if ($sesion->expira_en?->isPast()) {
            $sesion->update(['estado' => PedidoBmaSesionEvidencia::ESTADO_EXPIRADA]);

            return null;
        }

        return $sesion;
    }

    /**
     * @param  array{productos?: list<array<string, mixed>>, cajas?: list<array<string, mixed>>}  $snapshot
     */
    public function guardarSnapshot(PedidoBma $pedido, array $snapshot, int $usuarioId): PedidoBmaSesionEvidencia
    {
        $sesion = $this->vigenteOFallo($pedido);
        $sesion->update([
            'snapshot_json' => [
                'productos' => array_values($snapshot['productos'] ?? []),
                'cajas' => array_values($snapshot['cajas'] ?? []),
            ],
        ]);

        return $sesion->fresh();
    }

    public function cancelar(PedidoBma $pedido, int $usuarioId, string $motivo = 'Sesión de evidencias cancelada desde la PC.'): void
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            return;
        }
        $this->cerrar($sesion, PedidoBmaSesionEvidencia::ESTADO_CANCELADA, $usuarioId, $motivo);
        event(EvidenciaCedisActualizada::deSesion($sesion, 'cancelada'));
    }

    public function reclamar(string $codigo, string $ip, string $ua): PedidoBmaSesionEvidencia
    {
        $sesion = $this->porCodigo($codigo);
        $this->asegurarNoExpirada($sesion);

        if ($sesion->estado === PedidoBmaSesionEvidencia::ESTADO_PENDIENTE) {
            $sesion->update([
                'estado' => PedidoBmaSesionEvidencia::ESTADO_ACTIVA,
                'reclamado_en' => now(),
                'claim_ip' => $ip,
                'claim_ua' => mb_substr($ua, 0, 500),
            ]);
            $this->historial(
                $sesion->pedido,
                (int) $sesion->creado_por,
                'Celular conectado a la sesión de evidencias.'
            );
            event(EvidenciaCedisActualizada::deSesion($sesion->fresh(), 'sesion_reclamada'));

            return $sesion->fresh(['fotos']);
        }

        if ($sesion->estado !== PedidoBmaSesionEvidencia::ESTADO_ACTIVA) {
            throw ValidationException::withMessages([
                'codigo' => 'Esta sesión ya no está disponible.',
            ]);
        }

        return $sesion->load('fotos');
    }

    /**
     * Tras el primer reclamo, otro dispositivo sin cookie no entra.
     */
    public function assertReclamo(PedidoBmaSesionEvidencia $sesion, ?string $cookieValor): void
    {
        $this->asegurarNoExpirada($sesion);
        if ($sesion->estado === PedidoBmaSesionEvidencia::ESTADO_PENDIENTE) {
            return;
        }
        if ($sesion->estado !== PedidoBmaSesionEvidencia::ESTADO_ACTIVA) {
            throw ValidationException::withMessages(['codigo' => 'Esta sesión ya no está disponible.']);
        }
        if ($cookieValor !== $this->claimCookieValor($sesion)) {
            throw ValidationException::withMessages([
                'codigo' => 'Este QR ya fue usado en otro teléfono.',
            ]);
        }
    }

    public function claimCookieValor(PedidoBmaSesionEvidencia $sesion): string
    {
        return hash_hmac('sha256', (string) $sesion->id, (string) config('app.key'));
    }

    public function claimCookieName(PedidoBmaSesionEvidencia $sesion): string
    {
        return 'cedis_ev_'.$sesion->id;
    }

    public function payloadPublico(PedidoBmaSesionEvidencia $sesion): array
    {
        $sesion->loadMissing(['pedido', 'fotos']);
        $snap = $sesion->snapshot_json ?? ['productos' => [], 'cajas' => []];

        return [
            'folio' => $sesion->pedido?->folio_remision ?: $sesion->pedido?->folio,
            'estado' => $sesion->estado,
            'expira_en' => $sesion->expira_en?->toIso8601String(),
            'productos' => $snap['productos'] ?? [],
            'cajas' => $snap['cajas'] ?? [],
            'fotos' => collect($sesion->fotos)->map(fn (PedidoBmaSesionEvidenciaFoto $f) => $this->fotoPublica($sesion, $f))->all(),
        ];
    }

    public function subirFoto(
        PedidoBmaSesionEvidencia $sesion,
        UploadedFile $file,
        string $objetivoTipo,
        string $objetivoUuid,
        ?int $indiceCaja,
        string $ip,
        string $ua,
    ): PedidoBmaSesionEvidenciaFoto {
        $this->asegurarNoExpirada($sesion);
        if ($sesion->estado !== PedidoBmaSesionEvidencia::ESTADO_ACTIVA) {
            throw ValidationException::withMessages(['codigo' => 'La sesión no está activa.']);
        }
        if ($sesion->fotos()->count() >= PedidoBmaSesionEvidencia::MAX_FOTOS) {
            throw ValidationException::withMessages(['foto' => 'Se alcanzó el máximo de fotos de esta sesión.']);
        }
        if (! in_array($objetivoTipo, [PedidoBmaSesionEvidenciaFoto::OBJETIVO_PRODUCTO, PedidoBmaSesionEvidenciaFoto::OBJETIVO_CAJA], true)) {
            throw ValidationException::withMessages(['objetivo_tipo' => 'Destino inválido.']);
        }

        $ruta = $file->store('pedidos_bma/'.$sesion->pedido_bma_id.'/sesion_evidencia', 'public');
        $foto = PedidoBmaSesionEvidenciaFoto::query()->create([
            'sesion_id' => $sesion->id,
            'objetivo_tipo' => $objetivoTipo,
            'objetivo_uuid' => $objetivoUuid,
            'indice_caja' => $indiceCaja,
            'ruta_archivo' => $ruta,
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'ip' => $ip,
            'user_agent' => mb_substr($ua, 0, 500),
            'subido_en' => now(),
        ]);
        $foto->setRelation('sesion', $sesion);

        $this->historial(
            $sesion->pedido,
            (int) $sesion->creado_por,
            sprintf('Foto de evidencia subida desde celular (%s).', $objetivoTipo)
        );

        event(EvidenciaCedisActualizada::deSesion($sesion, 'foto', $this->fotoParaPc($foto)));

        return $foto;
    }

    /**
     * @param  array<string, int>  $productoUuidARevisionId
     * @param  array<string, int>  $cajaUuidACajaId
     */
    public function promover(PedidoBma $pedido, array $productoUuidARevisionId, array $cajaUuidACajaId, int $ordenDocInicio): int
    {
        $sesion = PedidoBmaSesionEvidencia::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereIn('estado', [PedidoBmaSesionEvidencia::ESTADO_PENDIENTE, PedidoBmaSesionEvidencia::ESTADO_ACTIVA])
            ->latest('id')
            ->first();

        if (! $sesion) {
            return $ordenDocInicio;
        }

        $orden = $ordenDocInicio;
        foreach ($sesion->fotos as $foto) {
            $relacionTipo = null;
            $relacionId = null;
            if ($foto->objetivo_tipo === PedidoBmaSesionEvidenciaFoto::OBJETIVO_PRODUCTO) {
                $relacionTipo = PedidoBmaDocumento::RELACION_REVISION_PRODUCTO;
                $relacionId = $productoUuidARevisionId[$foto->objetivo_uuid] ?? null;
            } elseif ($foto->objetivo_tipo === PedidoBmaSesionEvidenciaFoto::OBJETIVO_CAJA) {
                $relacionId = $cajaUuidACajaId[$foto->objetivo_uuid] ?? null;
                if (! $relacionId && $foto->indice_caja !== null) {
                    $relacionId = $cajaUuidACajaId['idx:'.$foto->indice_caja] ?? null;
                }
                if ($relacionId) {
                    $relacionTipo = PedidoBmaDocumento::RELACION_ENVIO_CAJA;
                } else {
                    // Tienda: snapshot manda “caja” sintética = evidencia final del lote.
                    $relacionTipo = PedidoBmaDocumento::RELACION_REVISION_GENERAL;
                    $relacionId = null;
                }
            }
            if ($relacionTipo === null) {
                continue;
            }
            if ($relacionTipo !== PedidoBmaDocumento::RELACION_REVISION_GENERAL && ! $relacionId) {
                continue;
            }

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                'ruta_archivo' => $foto->ruta_archivo,
                'nombre_original' => $foto->nombre_original,
                'mime_type' => $foto->mime_type,
                'tamano_bytes' => $foto->tamano_bytes,
                'orden' => $orden++,
                'comentario' => $relacionTipo === PedidoBmaDocumento::RELACION_REVISION_GENERAL
                    ? 'Evidencia final del lote (celular)'
                    : 'Evidencia desde celular',
                'relacion_tipo' => $relacionTipo,
                'relacion_id' => $relacionId,
            ]);
        }

        $sesion->update(['estado' => PedidoBmaSesionEvidencia::ESTADO_CERRADA]);

        return $orden;
    }

    /**
     * @return list<PedidoBmaSesionEvidenciaFoto>
     */
    public function fotosDeObjetivo(PedidoBma $pedido, string $tipo, string $uuid): array
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            return [];
        }

        return $sesion->fotos()
            ->where('objetivo_tipo', $tipo)
            ->where('objetivo_uuid', $uuid)
            ->get()
            ->all();
    }

    public function tieneFotoCaja(PedidoBma $pedido, string $uuid, int $indice): bool
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            return false;
        }

        return $sesion->fotos()
            ->where('objetivo_tipo', PedidoBmaSesionEvidenciaFoto::OBJETIVO_CAJA)
            ->where(function ($q) use ($uuid, $indice) {
                $q->where('objetivo_uuid', $uuid)
                    ->orWhere('indice_caja', $indice);
            })
            ->exists();
    }

    public function tieneAlgunaFotoCaja(PedidoBma $pedido): bool
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            return false;
        }

        return $sesion->fotos()
            ->where('objetivo_tipo', PedidoBmaSesionEvidenciaFoto::OBJETIVO_CAJA)
            ->exists();
    }

    public function tieneFotoProducto(PedidoBma $pedido, string $uuid): bool
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            return false;
        }

        return $sesion->fotos()
            ->where('objetivo_tipo', PedidoBmaSesionEvidenciaFoto::OBJETIVO_PRODUCTO)
            ->where('objetivo_uuid', $uuid)
            ->exists();
    }

    public function porCodigo(string $codigo): PedidoBmaSesionEvidencia
    {
        $hash = hash('sha256', $codigo);
        $sesion = PedidoBmaSesionEvidencia::query()
            ->where(function ($q) use ($hash, $codigo) {
                $q->where('token_hash', $hash)->orWhere('codigo_publico', $codigo);
            })
            ->first();

        if (! $sesion) {
            throw ValidationException::withMessages(['codigo' => 'El código no es válido.']);
        }

        return $sesion;
    }

    public function fotoParaPc(PedidoBmaSesionEvidenciaFoto $foto): array
    {
        return [
            'id' => $foto->id,
            'objetivo_tipo' => $foto->objetivo_tipo,
            'objetivo_uuid' => $foto->objetivo_uuid,
            'indice_caja' => $foto->indice_caja,
            'nombre' => $foto->nombre_original,
            'mime' => $foto->mime_type,
            'url' => route('control_pedidos.cedis.sesion_evidencia.foto', [
                'pedidoBma' => $foto->sesion?->pedido_bma_id ?? $foto->sesion()->value('pedido_bma_id'),
                'foto' => $foto->id,
            ]),
        ];
    }

    public function fotoPublica(PedidoBmaSesionEvidencia $sesion, PedidoBmaSesionEvidenciaFoto $foto): array
    {
        return [
            'id' => $foto->id,
            'objetivo_tipo' => $foto->objetivo_tipo,
            'objetivo_uuid' => $foto->objetivo_uuid,
            'nombre' => $foto->nombre_original,
            'mime' => $foto->mime_type,
            'url' => FormPublicUrl::cedisEvidenciaShow($sesion->codigo_publico).'/fotos/'.$foto->id,
        ];
    }

    private function vigenteOFallo(PedidoBma $pedido): PedidoBmaSesionEvidencia
    {
        $sesion = $this->vigente($pedido);
        if (! $sesion) {
            throw ValidationException::withMessages(['sesion' => 'No hay una sesión de evidencias activa.']);
        }

        return $sesion;
    }

    private function asegurarNoExpirada(PedidoBmaSesionEvidencia $sesion): void
    {
        if (in_array($sesion->estado, [PedidoBmaSesionEvidencia::ESTADO_CANCELADA, PedidoBmaSesionEvidencia::ESTADO_CERRADA, PedidoBmaSesionEvidencia::ESTADO_EXPIRADA], true)
            || ($sesion->expira_en && $sesion->expira_en->isPast())) {
            if ($sesion->expira_en?->isPast() && $sesion->estaAbierta()) {
                $sesion->update(['estado' => PedidoBmaSesionEvidencia::ESTADO_EXPIRADA]);
            }
            throw ValidationException::withMessages(['codigo' => 'La sesión expiró o fue cancelada.']);
        }
    }

    private function revocarAbiertas(PedidoBma $pedido, int $usuarioId, string $motivo): void
    {
        $abiertas = PedidoBmaSesionEvidencia::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereIn('estado', [PedidoBmaSesionEvidencia::ESTADO_PENDIENTE, PedidoBmaSesionEvidencia::ESTADO_ACTIVA])
            ->get();

        foreach ($abiertas as $sesion) {
            $this->cerrar($sesion, PedidoBmaSesionEvidencia::ESTADO_CANCELADA, $usuarioId, $motivo, borrarArchivos: true);
        }
    }

    private function cerrar(
        PedidoBmaSesionEvidencia $sesion,
        string $estado,
        ?int $usuarioId,
        string $motivo,
        bool $borrarArchivos = true,
    ): void {
        if ($borrarArchivos) {
            foreach ($sesion->fotos as $foto) {
                Storage::disk('public')->delete($foto->ruta_archivo);
                $foto->delete();
            }
        }
        $sesion->update([
            'estado' => $estado,
            'cancelado_por' => $usuarioId,
            'cancelado_en' => now(),
        ]);
        $this->historial($sesion->pedido, $usuarioId, $motivo);
    }

    private function historial(PedidoBma $pedido, ?int $usuarioId, string $comentario): void
    {
        $estatusId = (int) ($pedido->catalogo_estatus_pedido_id ?: $pedido->estatus?->id);
        if ($estatusId < 1) {
            return;
        }
        $this->historialService->ejecutar(
            $pedido->id,
            $usuarioId,
            $estatusId,
            $estatusId,
            $comentario,
            AccionesHistorialPedidoBma::SESION_EVIDENCIA
        );
    }

    private function qrDataUri(string $url): string
    {
        $qr = new QrCode(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 8,
        );
        $png = (new PngWriter())->write($qr);

        return 'data:image/png;base64,'.base64_encode($png->getString());
    }

    private function generarCodigoUnico(int $longitud = 16): string
    {
        $max = strlen(self::ALFABETO) - 1;
        for ($intento = 0; $intento < 20; $intento++) {
            $codigo = '';
            for ($i = 0; $i < $longitud; $i++) {
                $codigo .= self::ALFABETO[random_int(0, $max)];
            }
            $existe = PedidoBmaSesionEvidencia::query()
                ->where(function ($q) use ($codigo) {
                    $q->where('codigo_publico', $codigo)
                        ->orWhere('token_hash', hash('sha256', $codigo));
                })
                ->exists();
            if (! $existe) {
                return $codigo;
            }
        }

        return Str::lower(Str::random(24));
    }
}
