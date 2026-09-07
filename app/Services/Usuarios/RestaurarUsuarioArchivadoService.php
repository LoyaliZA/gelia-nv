<?php

namespace App\Services\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurarUsuarioArchivadoService
{
    private const SUFFIX_PATTERN = '/_archived_\d+$/';

    public function ejecutar(User $user): User
    {
        if (! $user->trashed()) {
            throw ValidationException::withMessages([
                'usuario' => 'Este colaborador no está archivado.',
            ]);
        }

        $email = $this->revertirSufijo($user->email);
        $username = $this->revertirSufijo($user->username);
        $telefono = $user->telefono !== null ? $this->revertirSufijo($user->telefono) : null;

        $this->validarUnicidad($user, $email, $username);

        return DB::transaction(function () use ($user, $email, $username, $telefono) {
            $user->update([
                'email' => $email,
                'username' => $username,
                'telefono' => $telefono,
            ]);

            $user->restore();

            RegistrarAuditoriaConfiguracionService::ejecutar(
                'Usuarios',
                'Restauración de cuenta',
                [
                    'descripcion' => 'Se restauró el usuario archivado, reactivando sus credenciales de acceso.',
                    'credenciales_restauradas' => [
                        'email' => $email,
                        'username' => $username,
                        'telefono' => $telefono,
                    ],
                    'usuario_afectado' => [
                        'id' => $user->id,
                        'nombre' => trim("{$user->name} {$user->apellido_paterno} {$user->apellido_materno}"),
                    ],
                ],
                $user->id
            );

            return $user->fresh();
        });
    }

    private function revertirSufijo(string $valor): string
    {
        return preg_replace(self::SUFFIX_PATTERN, '', $valor) ?? $valor;
    }

    private function validarUnicidad(User $user, string $email, string $username): void
    {
        $errores = [];

        $emailEnUso = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailEnUso) {
            $errores['email'] = 'El correo ya está asignado a otro usuario activo.';
        }

        $usernameEnUso = User::query()
            ->where('username', $username)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($usernameEnUso) {
            $errores['username'] = 'El nombre de usuario ya está en uso.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
