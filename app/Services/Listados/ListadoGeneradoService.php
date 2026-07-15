<?php

namespace App\Services\Listados;

use App\Models\CustomList;
use App\Models\ListadoDestinatario;
use App\Models\ListadoGenerado;
use App\Models\User;
use App\Notifications\ListadoGeneradoNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ListadoGeneradoService
{
    public const TIPOS_PREDETERMINADOS = [
        'resurtido',
        'costos',
        'actualizada',
        'inventario',
        'venta_especial',
    ];

    public function obtenerDestinatariosDefault(string $tipoLista): array
    {
        if (is_numeric($tipoLista)) {
            $lista = CustomList::find($tipoLista);
            if (!$lista) {
                return ['user_ids' => [], 'externos' => [], 'users' => []];
            }

            $userIds = array_values(array_filter($lista->destinatarios_user_ids ?? []));
            $externos = array_values($lista->destinatarios_externos ?? []);
            $users = User::select('id', 'name', 'email')
                ->whereIn('id', $userIds)
                ->get();

            return [
                'user_ids' => $userIds,
                'externos' => $externos,
                'users' => $users,
            ];
        }

        $rows = ListadoDestinatario::with('user:id,name,email')
            ->where('tipo_lista', $tipoLista)
            ->get();

        $userIds = [];
        $users = collect();
        $externos = [];

        foreach ($rows as $row) {
            if ($row->user_id && $row->user) {
                $userIds[] = $row->user_id;
                $users->push($row->user);
            } elseif ($row->email) {
                $externos[] = [
                    'nombre' => $row->nombre ?? '',
                    'email' => $row->email,
                ];
            }
        }

        return [
            'user_ids' => $userIds,
            'externos' => $externos,
            'users' => $users->unique('id')->values(),
        ];
    }

    public function destinatariosPorTipo(): array
    {
        $result = [];
        foreach (self::TIPOS_PREDETERMINADOS as $tipo) {
            $result[$tipo] = $this->obtenerDestinatariosDefault($tipo);
        }

        return $result;
    }

    public function guardarDestinatariosPredeterminados(string $tipoLista, array $userIds, array $externos): void
    {
        if (!in_array($tipoLista, self::TIPOS_PREDETERMINADOS, true)) {
            throw new \InvalidArgumentException('Tipo de lista no válido.');
        }

        ListadoDestinatario::where('tipo_lista', $tipoLista)->delete();

        foreach (array_unique(array_filter($userIds)) as $userId) {
            ListadoDestinatario::create([
                'tipo_lista' => $tipoLista,
                'user_id' => (int) $userId,
            ]);
        }

        $emailsVistos = [];
        foreach ($externos as $externo) {
            $email = strtolower(trim($externo['email'] ?? ''));
            $nombre = trim($externo['nombre'] ?? '');
            if ($email === '' || isset($emailsVistos[$email])) {
                continue;
            }
            $emailsVistos[$email] = true;
            ListadoDestinatario::create([
                'tipo_lista' => $tipoLista,
                'nombre' => $nombre !== '' ? $nombre : $email,
                'email' => $email,
            ]);
        }
    }

    /**
     * Persiste el Excel temporal y opcionalmente notifica destinatarios.
     */
    public function confirmar(
        string $tempFilename,
        string $nombreDescarga,
        string $tipoLista,
        int $userId,
        bool $guardar,
        bool $enviar,
        array $userIds = [],
        array $externos = []
    ): array {
        $tempPath = storage_path('app/temp/' . $tempFilename);

        if (!file_exists($tempPath)) {
            throw new \RuntimeException('El archivo temporal ha expirado o ya fue procesado.');
        }

        $debePersistir = $guardar || $enviar;
        $listado = null;

        if ($debePersistir) {
            $hash = uniqid();
            $extension = pathinfo($nombreDescarga, PATHINFO_EXTENSION) ?: 'xlsx';
            $baseName = pathinfo($nombreDescarga, PATHINFO_FILENAME);
            $nombreFisico = $baseName . '_' . $hash . '.' . $extension;
            $rutaStorage = 'listados/' . $nombreFisico;

            Storage::disk('public')->put($rutaStorage, file_get_contents($tempPath));
            $tamanoKb = round(filesize($tempPath) / 1024, 2) . ' KB';

            $customListId = is_numeric($tipoLista) ? (int) $tipoLista : null;

            $listado = ListadoGenerado::create([
                'user_id' => $userId,
                'tipo_lista' => (string) $tipoLista,
                'custom_list_id' => $customListId,
                'nombre_archivo' => $nombreDescarga,
                'ruta_fisica' => $rutaStorage,
                'tamano_kb' => $tamanoKb,
                'enviado_correo' => false,
            ]);

            if ($enviar) {
                $this->notificar($listado, $userIds, $externos);
                $listado->update(['enviado_correo' => true]);
            }
        }

        return [
            'listado' => $listado,
            'temp_path' => $tempPath,
            'nombre_descarga' => $nombreDescarga,
        ];
    }

    public function notificar(ListadoGenerado $listado, array $userIds, array $externos): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (count($userIds) > 0) {
            $usuarios = User::whereIn('id', $userIds)->get();
            foreach ($usuarios as $usuario) {
                // Envío inmediato: evita depender de cola para el adjunto recién guardado
                $usuario->notifyNow(new ListadoGeneradoNotification($listado));
            }
        }

        $emailsVistos = [];
        foreach ($externos as $externo) {
            $email = strtolower(trim($externo['email'] ?? ''));
            $nombre = trim($externo['nombre'] ?? '');
            if ($email === '' || isset($emailsVistos[$email])) {
                continue;
            }
            $emailsVistos[$email] = true;

            Notification::route('mail', $email)
                ->notifyNow(new ListadoGeneradoNotification(
                    $listado,
                    $nombre !== '' ? $nombre : $email
                ));
        }
    }

    public function eliminar(ListadoGenerado $listado): void
    {
        if (Storage::disk('public')->exists($listado->ruta_fisica)) {
            Storage::disk('public')->delete($listado->ruta_fisica);
        }
        $listado->delete();
    }
}
