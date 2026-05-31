<?php

namespace App\Services\Mensajeria;

use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeAdjunto;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class BuscarMensajeriaService
{
    private const MIN_QUERY = 2;

    private const LIMITE_POR_SECCION = 20;

    public function __construct(
        private ListarConversacionesService $listarConversaciones,
    ) {}

    public function ejecutar(User $user, ?string $q, ?int $conversacionId = null): array
    {
        $termino = trim((string) $q);
        if (mb_strlen($termino) < self::MIN_QUERY) {
            return $this->vacio();
        }

        $like = '%' . $this->escaparLike($termino) . '%';
        $idsConversaciones = $this->idsConversacionesUsuario($user, $conversacionId);

        if ($idsConversaciones === []) {
            return $this->vacio();
        }

        return [
            'q' => $termino,
            'personas' => $this->buscarPersonas($user, $like, $idsConversaciones, $conversacionId),
            'conversaciones' => $conversacionId ? [] : $this->buscarConversaciones($user, $termino, $idsConversaciones),
            'mensajes' => $this->buscarMensajes($user, $like, $idsConversaciones, $termino),
            'archivos' => $this->buscarArchivos($user, $like, $idsConversaciones, $termino),
        ];
    }

    private function vacio(): array
    {
        return [
            'q' => '',
            'personas' => [],
            'conversaciones' => [],
            'mensajes' => [],
            'archivos' => [],
        ];
    }

    private function idsConversacionesUsuario(User $user, ?int $conversacionId): array
    {
        $query = ConversacionParticipante::query()
            ->where('user_id', $user->id);

        if ($conversacionId) {
            $query->where('conversacion_id', $conversacionId);
        }

        return $query->pluck('conversacion_id')->all();
    }

    private function buscarPersonas(
        User $user,
        string $like,
        array $idsConversaciones,
        ?int $conversacionId
    ): array {
        $query = User::query()
            ->select('users.id', 'users.name', 'users.username', 'users.foto_perfil')
            ->join('conversacion_participantes as cp', 'cp.user_id', '=', 'users.id')
            ->whereIn('cp.conversacion_id', $idsConversaciones)
            ->where('users.id', '!=', $user->id)
            ->where(function ($sub) use ($like) {
                $sub->where('users.name', 'like', $like)
                    ->orWhere('users.username', 'like', $like);
            })
            ->distinct()
            ->limit(self::LIMITE_POR_SECCION);

        if ($conversacionId) {
            $query->where('cp.conversacion_id', $conversacionId);
        }

        return $query->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'foto_perfil' => $u->foto_perfil,
            'tipo' => 'persona',
        ])->all();
    }

    private function buscarConversaciones(User $user, string $termino, array $idsConversaciones): array
    {
        $lista = $this->listarConversaciones->ejecutar($user);

        return $lista
            ->filter(function ($c) use ($termino, $idsConversaciones) {
                if (!in_array($c['id'], $idsConversaciones, true)) {
                    return false;
                }

                if (mb_stripos($c['nombre'] ?? '', $termino) !== false) {
                    return true;
                }

                foreach ($c['participantes'] ?? [] as $p) {
                    if (mb_stripos($p['name'] ?? '', $termino) !== false
                        || mb_stripos($p['username'] ?? '', $termino) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->take(self::LIMITE_POR_SECCION)
            ->map(fn ($c) => [
                'id' => $c['id'],
                'nombre' => $c['nombre'],
                'foto' => $c['foto'],
                'tipo' => $c['tipo'],
                'ultimo_mensaje_preview' => $c['ultimo_mensaje_preview'],
                'tipo_resultado' => 'conversacion',
            ])
            ->values()
            ->all();
    }

    private function buscarMensajes(User $user, string $like, array $idsConversaciones, string $termino): array
    {
        $filas = Mensaje::query()
            ->select([
                'mensajes.id',
                'mensajes.conversacion_id',
                'mensajes.contenido',
                'mensajes.tipo',
                'mensajes.created_at',
                'users.name as user_name',
                'users.username as user_username',
            ])
            ->join('users', 'users.id', '=', 'mensajes.user_id')
            ->whereIn('mensajes.conversacion_id', $idsConversaciones)
            ->whereNull('mensajes.deleted_at')
            ->where(function ($sub) use ($like) {
                $sub->where('mensajes.contenido', 'like', $like)
                    ->orWhere('users.name', 'like', $like)
                    ->orWhere('users.username', 'like', $like);
            })
            ->orderByDesc('mensajes.created_at')
            ->limit(self::LIMITE_POR_SECCION)
            ->get();

        $nombres = $this->nombresConversaciones($user, $filas->pluck('conversacion_id')->unique()->all());

        return $filas->map(function ($row) use ($termino, $nombres) {
            $texto = $row->contenido ?: '[' . $row->tipo . ']';

            return [
                'mensaje_id' => $row->id,
                'conversacion_id' => $row->conversacion_id,
                'conversacion_nombre' => $nombres[$row->conversacion_id] ?? 'Chat',
                'fragmento' => $this->fragmento($texto, $termino),
                'user_name' => $row->user_name,
                'user_username' => $row->user_username,
                'tipo_mensaje' => $row->tipo,
                'created_at' => $row->created_at?->toIso8601String(),
                'tipo_resultado' => 'mensaje',
            ];
        })->all();
    }

    private function buscarArchivos(User $user, string $like, array $idsConversaciones, string $termino): array
    {
        $tieneIndice = Schema::hasColumn('mensaje_adjuntos', 'contenido_indexado');

        $filas = MensajeAdjunto::query()
            ->select([
                'mensaje_adjuntos.id as adjunto_id',
                'mensaje_adjuntos.nombre_original',
                'mensaje_adjuntos.ruta',
                'mensaje_adjuntos.mime',
                'mensajes.id as mensaje_id',
                'mensajes.conversacion_id',
                'mensajes.contenido as mensaje_contenido',
                'mensajes.tipo as tipo_mensaje',
                'mensajes.created_at',
                'users.name as user_name',
            ])
            ->when($tieneIndice, fn ($q) => $q->addSelect('mensaje_adjuntos.contenido_indexado'))
            ->join('mensajes', 'mensajes.id', '=', 'mensaje_adjuntos.mensaje_id')
            ->join('users', 'users.id', '=', 'mensajes.user_id')
            ->whereIn('mensajes.conversacion_id', $idsConversaciones)
            ->whereNull('mensajes.deleted_at')
            ->where(function ($sub) use ($like, $tieneIndice, $termino) {
                $sub->where('mensaje_adjuntos.nombre_original', 'like', $like)
                    ->orWhere('mensaje_adjuntos.ruta', 'like', $like)
                    ->orWhere('mensaje_adjuntos.mime', 'like', $like)
                    ->orWhere('mensajes.contenido', 'like', $like);

                if ($tieneIndice) {
                    $sub->orWhere('mensaje_adjuntos.contenido_indexado', 'like', $like);
                }

                if ($this->coincideTipoMedio($termino)) {
                    $sub->orWhereIn('mensajes.tipo', $this->tiposPorTermino($termino));
                }
            })
            ->orderByDesc('mensajes.created_at')
            ->limit(self::LIMITE_POR_SECCION)
            ->get();

        $nombres = $this->nombresConversaciones($user, $filas->pluck('conversacion_id')->unique()->all());

        return $filas->map(function ($row) use ($termino, $nombres, $tieneIndice) {
            $nombre = $row->nombre_original ?: basename((string) $row->ruta);
            $fuente = $nombre;
            $origen = 'nombre';

            if ($tieneIndice && !empty($row->contenido_indexado) && mb_stripos($row->contenido_indexado, $termino) !== false) {
                $fuente = $row->contenido_indexado;
                $origen = 'contenido';
            } elseif ($row->mensaje_contenido && mb_stripos($row->mensaje_contenido, $termino) !== false) {
                $fuente = $row->mensaje_contenido;
                $origen = 'leyenda';
            }

            return [
                'adjunto_id' => $row->adjunto_id,
                'mensaje_id' => $row->mensaje_id,
                'conversacion_id' => $row->conversacion_id,
                'conversacion_nombre' => $nombres[$row->conversacion_id] ?? 'Chat',
                'nombre_original' => $nombre,
                'mime' => $row->mime,
                'tipo_mensaje' => $row->tipo_mensaje,
                'fragmento' => $this->fragmento((string) $fuente, $termino),
                'coincidencia_en' => $origen,
                'user_name' => $row->user_name,
                'created_at' => $row->created_at?->toIso8601String(),
                'tipo_resultado' => 'archivo',
            ];
        })->all();
    }

    private function coincideTipoMedio(string $termino): bool
    {
        $t = mb_strtolower($termino);

        return in_array($t, [
            'imagen', 'imagenes', 'foto', 'fotos', 'picture', 'image', 'images',
            'video', 'videos', 'audio', 'archivo', 'archivos', 'pdf', 'excel', 'sql',
        ], true);
    }

    private function tiposPorTermino(string $termino): array
    {
        $t = mb_strtolower($termino);

        return match (true) {
            in_array($t, ['imagen', 'imagenes', 'foto', 'fotos', 'picture', 'image', 'images'], true) => [
                Mensaje::TIPO_IMAGEN,
            ],
            in_array($t, ['video', 'videos'], true) => [Mensaje::TIPO_VIDEO],
            in_array($t, ['audio'], true) => [Mensaje::TIPO_AUDIO],
            in_array($t, ['archivo', 'archivos', 'pdf', 'excel', 'sql'], true) => [Mensaje::TIPO_ARCHIVO],
            default => [],
        };
    }

    private function nombresConversaciones(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $lista = $this->listarConversaciones->ejecutar($user);

        return $lista
            ->filter(fn ($c) => in_array($c['id'], $ids, true))
            ->mapWithKeys(fn ($c) => [$c['id'] => $c['nombre']])
            ->all();
    }

    private function fragmento(string $texto, string $termino, int $radio = 55): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return '';
        }

        $pos = mb_stripos($texto, $termino);
        if ($pos === false) {
            return mb_strlen($texto) > 140 ? mb_substr($texto, 0, 140) . '…' : $texto;
        }

        $inicio = max(0, $pos - $radio);
        $trozo = mb_substr($texto, $inicio, $radio * 2 + mb_strlen($termino) + 10);
        $prefijo = $inicio > 0 ? '…' : '';
        $sufijo = ($inicio + mb_strlen($trozo)) < mb_strlen($texto) ? '…' : '';

        return $prefijo . $trozo . $sufijo;
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}
