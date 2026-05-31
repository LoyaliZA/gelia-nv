<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\MensajeAdjunto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListarMediosConversacionService
{
    private const CATEGORIAS = ['documentos', 'imagenes', 'enlaces'];

    public function ejecutar(Conversacion $conversacion, array $filtros = []): array
    {
        $categoria = in_array($filtros['categoria'] ?? '', self::CATEGORIAS, true)
            ? $filtros['categoria']
            : null;
        $userId = isset($filtros['user_id']) && $filtros['user_id'] !== ''
            ? (int) $filtros['user_id']
            : null;
        $desde = $this->parseFecha($filtros['desde'] ?? null, true);
        $hasta = $this->parseFecha($filtros['hasta'] ?? null, false);

        $items = collect()
            ->merge($this->itemsAdjuntos($conversacion->id, $userId, $desde, $hasta))
            ->merge($this->itemsEnlaces($conversacion->id, $userId, $desde, $hasta));

        if ($categoria) {
            $items = $items->where('categoria', $categoria)->values();
        }

        $items = $items
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->values();

        $remitentes = $items
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'items' => $items->all(),
            'remitentes' => $remitentes,
        ];
    }

    private function itemsAdjuntos(int $conversacionId, ?int $userId, ?Carbon $desde, ?Carbon $hasta): Collection
    {
        $query = MensajeAdjunto::query()
            ->whereHas('mensaje', function ($q) use ($conversacionId, $userId, $desde, $hasta) {
                $q->where('conversacion_id', $conversacionId)
                    ->when($userId, fn ($q2) => $q2->where('user_id', $userId))
                    ->when($desde, fn ($q2) => $q2->where('created_at', '>=', $desde))
                    ->when($hasta, fn ($q2) => $q2->where('created_at', '<=', $hasta));
            })
            ->with(['mensaje.user:id,name,username,foto_perfil']);

        return $query->get()->map(function (MensajeAdjunto $adjunto) {
            $mensaje = $adjunto->mensaje;

            return [
                'id' => 'adjunto-' . $adjunto->id,
                'tipo_item' => 'adjunto',
                'categoria' => $this->categorizarAdjunto($adjunto, $mensaje->tipo),
                'mensaje_id' => $mensaje->id,
                'created_at' => $mensaje->created_at?->toIso8601String(),
                'user' => $this->formatearUser($mensaje->user),
                'adjunto' => [
                    'id' => $adjunto->id,
                    'nombre_original' => $adjunto->nombre_original,
                    'mime' => $adjunto->mime,
                    'tamano' => $adjunto->tamano,
                    'url' => $adjunto->url(),
                    'thumbnail_url' => $adjunto->thumbnailUrl(),
                    'tipo_mensaje' => $mensaje->tipo,
                ],
            ];
        });
    }

    private function itemsEnlaces(int $conversacionId, ?int $userId, ?Carbon $desde, ?Carbon $hasta): Collection
    {
        $query = Mensaje::query()
            ->where('conversacion_id', $conversacionId)
            ->where('tipo', Mensaje::TIPO_TEXTO)
            ->whereNotNull('contenido')
            ->where('contenido', 'like', '%http%')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('created_at', '<=', $hasta))
            ->with('user:id,name,username,foto_perfil');

        $items = collect();

        foreach ($query->get() as $mensaje) {
            foreach ($this->extraerEnlaces($mensaje->contenido) as $index => $url) {
                $items->push([
                    'id' => 'enlace-' . $mensaje->id . '-' . $index,
                    'tipo_item' => 'enlace',
                    'categoria' => 'enlaces',
                    'mensaje_id' => $mensaje->id,
                    'created_at' => $mensaje->created_at?->toIso8601String(),
                    'user' => $this->formatearUser($mensaje->user),
                    'enlace' => [
                        'url' => $url,
                        'titulo' => $this->tituloEnlace($url),
                    ],
                ]);
            }
        }

        return $items;
    }

    private function categorizarAdjunto(MensajeAdjunto $adjunto, string $tipoMensaje): string
    {
        if ($tipoMensaje === Mensaje::TIPO_IMAGEN || str_starts_with($adjunto->mime, 'image/')) {
            return 'imagenes';
        }

        if ($tipoMensaje === Mensaje::TIPO_VIDEO || str_starts_with($adjunto->mime, 'video/')) {
            return 'imagenes';
        }

        return 'documentos';
    }

    private function extraerEnlaces(string $texto): array
    {
        if (!preg_match_all('#https?://[^\s<>"{}|\\^`\[\]]+#iu', $texto, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[0]));
    }

    private function tituloEnlace(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? (string) $host : mb_substr($url, 0, 80);
    }

    private function formatearUser($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'foto_perfil' => $user->foto_perfil,
        ];
    }

    private function parseFecha(?string $fecha, bool $inicioDia): ?Carbon
    {
        if (!$fecha) {
            return null;
        }

        try {
            $carbon = Carbon::parse($fecha);

            return $inicioDia ? $carbon->startOfDay() : $carbon->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
