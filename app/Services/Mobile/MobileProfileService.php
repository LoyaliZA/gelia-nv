<?php

namespace App\Services\Mobile;

use App\Models\ConfiguracionUsuario;
use App\Models\User;
use App\Services\PersonalizacionCatalogoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MobileProfileService
{
    private const ACCENT_COLORS = [
        'rosa' => '#ec4899',
        'azul' => '#3b82f6',
        'verde' => '#10b981',
        'amarillo' => '#f59e0b',
    ];

    /**
     * @param  array<string, mixed>  $temaVisual
     * @return array<string, mixed>
     */
    public function actualizarTemaVisual(User $user, array $temaVisual): array
    {
        $temaVisual = $this->normalizarTemaVisual($temaVisual);

        if ($temaVisual === []) {
            return app(MobileAuthService::class)->resolverTemaVisual($user);
        }

        $configActual = ConfiguracionUsuario::query()
            ->where('user_id', $user->id)
            ->value('tema_visual');

        $temaActual = [];
        if (is_string($configActual)) {
            $temaActual = json_decode($configActual, true) ?: [];
        } elseif (is_array($configActual)) {
            $temaActual = $configActual;
        }

        $temaFinal = array_merge($temaActual, $temaVisual);

        if (isset($temaFinal['color_nombre'])) {
            $temaFinal['color_hex'] = $this->resolverColorHex((string) $temaFinal['color_nombre']);
        }

        DB::table('configuraciones_usuarios')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'tema_visual' => json_encode($temaFinal),
                'updated_at' => now(),
            ]
        );

        return $temaFinal;
    }

    public function actualizarFotoPerfil(User $user, ?UploadedFile $archivo, bool $eliminar): void
    {
        if ($archivo) {
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }

            $user->update([
                'foto_perfil' => $archivo->store('perfiles', 'public'),
            ]);

            return;
        }

        if ($eliminar) {
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }

            $user->update(['foto_perfil' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $temaVisual
     * @return array<string, mixed>
     */
    private function normalizarTemaVisual(array $temaVisual): array
    {
        $permitidos = [
            'modo',
            'color_nombre',
            'fondo_base',
            'fuente_principal',
            'escala_fuente',
            'layout_sidebar',
            'layout_sidebar_mobile',
            'sidebar_modo',
            'sidebar_posicion_fija',
            'efecto_cristal',
            'densidad_contenido',
            'contenido_max_rem',
            'contenido_padding_rem',
            'alertas_prefs',
        ];

        $normalizado = array_intersect_key($temaVisual, array_flip($permitidos));

        if (isset($normalizado['modo']) && ! in_array($normalizado['modo'], ['dark', 'light'], true)) {
            unset($normalizado['modo']);
        }

        if (isset($normalizado['escala_fuente'])) {
            $escala = (float) $normalizado['escala_fuente'];
            $escala = round($escala / 0.0625) * 0.0625;
            $normalizado['escala_fuente'] = max(0.875, min(1.5, $escala));
        }

        if (isset($normalizado['densidad_contenido'])) {
            $modos = ['compacto', 'completo', 'personalizado'];
            if (! in_array($normalizado['densidad_contenido'], $modos, true)) {
                unset($normalizado['densidad_contenido']);
            }
        }

        if (isset($normalizado['contenido_max_rem'])) {
            $maxRem = round(((float) $normalizado['contenido_max_rem']) / 2.5) * 2.5;
            $normalizado['contenido_max_rem'] = max(60, min(120, $maxRem));
        }

        if (isset($normalizado['contenido_padding_rem'])) {
            $padRem = round(((float) $normalizado['contenido_padding_rem']) / 0.125) * 0.125;
            $normalizado['contenido_padding_rem'] = max(0.5, min(2, $padRem));
        }

        if (isset($normalizado['alertas_prefs']) && is_array($normalizado['alertas_prefs'])) {
            $normalizado['alertas_prefs'] = $this->normalizarAlertasPrefs($normalizado['alertas_prefs']);
        }

        return $normalizado;
    }

    /**
     * @param  array<string, mixed>  $prefs
     * @return array<string, mixed>
     */
    private function normalizarAlertasPrefs(array $prefs): array
    {
        $defaults = config('alertas.defaults', []);
        $tonosValidos = PersonalizacionCatalogoService::tonoIdsValidos();
        $tiposValidos = array_keys($defaults['tipos'] ?? []);

        $canales = array_merge(
            $defaults['canales'] ?? [],
            array_intersect_key($prefs['canales'] ?? [], array_flip(['sonido', 'voz', 'escritorio', 'app']))
        );

        foreach (['sonido', 'voz', 'escritorio', 'app'] as $canal) {
            $canales[$canal] = filter_var($canales[$canal] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        $tonoId = $prefs['tono_id'] ?? ($defaults['tono_id'] ?? 'default');
        if (! in_array($tonoId, $tonosValidos, true)) {
            $tonoId = $defaults['tono_id'] ?? 'default';
        }

        $tipos = array_merge($defaults['tipos'] ?? [], $prefs['tipos'] ?? []);
        $tipos = array_intersect_key($tipos, array_flip($tiposValidos));
        foreach ($tipos as $tipo => $valor) {
            $tipos[$tipo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        }

        $modosMensajeria = ['desactivado', 'solo_aviso', 'leer_mensaje'];
        $mensajeriaVoz = $prefs['mensajeria_voz'] ?? ($defaults['mensajeria_voz'] ?? 'solo_aviso');
        if (! in_array($mensajeriaVoz, $modosMensajeria, true)) {
            $mensajeriaVoz = $defaults['mensajeria_voz'] ?? 'solo_aviso';
        }

        if ($canales['voz'] === false) {
            $mensajeriaVoz = 'desactivado';
        }

        return [
            'canales' => $canales,
            'tono_id' => $tonoId,
            'mensajeria_voz' => $mensajeriaVoz,
            'tipos' => $tipos,
        ];
    }

    private function resolverColorHex(string $colorNombre): string
    {
        $colorNombre = strtolower(trim($colorNombre));

        if (str_starts_with($colorNombre, '#')) {
            return $colorNombre;
        }

        return self::ACCENT_COLORS[$colorNombre] ?? self::ACCENT_COLORS['rosa'];
    }
}
