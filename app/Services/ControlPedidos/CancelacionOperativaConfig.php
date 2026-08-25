<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CancelacionOperativaConfig
{
    public const CLAVE_FLAG = 'control_pedidos.cancelacion_operativa';

    public const CLAVE_DIAS_TIPO = 'control_pedidos.preparacion.dias_tipo';

    public const CLAVE_ANTICIPACION_AVISO_HORAS = 'control_pedidos.preparacion.anticipacion_aviso_horas';

    public const CLAVE_ROL_RESOLUTOR = 'control_pedidos.preparacion.rol_resolutor_financiero';

    public const CLAVE_EVIDENCIA_LIBERAR = 'control_pedidos.preparacion.evidencia_liberar';

    public const CACHE_KEY = 'control_pedidos.cancelacion_operativa.config';

    public const DIAS_NATURALES = 'naturales';

    public const DIAS_HABILES = 'habiles';

    /**
     * @return array{activo: bool, departamentos_habilitados: list<int>, usuarios_piloto: list<int>}
     */
    public static function flagPorDefecto(): array
    {
        return [
            'activo' => false,
            'departamentos_habilitados' => [],
            'usuarios_piloto' => [],
        ];
    }

    public function __construct(
        private PreparacionTiendaConfig $preparacionConfig,
    ) {}

    public function activo(): bool
    {
        return (bool) ($this->flag()['activo'] ?? false);
    }

    /**
     * @return list<int>
     */
    public function departamentosHabilitados(): array
    {
        return $this->enteros($this->flag()['departamentos_habilitados'] ?? []);
    }

    /**
     * @return list<int>
     */
    public function usuariosPiloto(): array
    {
        return $this->enteros($this->flag()['usuarios_piloto'] ?? []);
    }

    public function usuarioHabilitado(User $usuario): bool
    {
        if (! $this->activo()) {
            return false;
        }

        $pilotos = $this->usuariosPiloto();
        if ($pilotos !== [] && ! in_array((int) $usuario->id, $pilotos, true)) {
            return false;
        }

        $deptos = $this->departamentosHabilitados();
        if ($deptos === []) {
            return true;
        }

        $idsDept = [];
        if ($usuario->departamento_id) {
            $idsDept[] = (int) $usuario->departamento_id;
        }
        $idsDept = array_merge(
            $idsDept,
            $usuario->departamentos()->pluck('departamentos.id')->map(fn ($id) => (int) $id)->all()
        );

        return count(array_intersect($deptos, array_unique($idsDept))) > 0;
    }

    public function diasTipo(): string
    {
        $raw = strtolower(trim((string) $this->valorEscalar(self::CLAVE_DIAS_TIPO, self::DIAS_NATURALES)));

        return in_array($raw, [self::DIAS_NATURALES, self::DIAS_HABILES], true)
            ? $raw
            : self::DIAS_NATURALES;
    }

    public function anticipacionAvisoHoras(): int
    {
        $n = (int) $this->valorEscalar(self::CLAVE_ANTICIPACION_AVISO_HORAS, '24');

        return max(1, min(168, $n));
    }

    public function rolResolutorFinanciero(): string
    {
        $raw = trim((string) $this->valorEscalar(
            self::CLAVE_ROL_RESOLUTOR,
            'control_pedidos.cancelacion_operativa.resolver_financiera'
        ));

        return $raw !== '' ? $raw : 'control_pedidos.cancelacion_operativa.resolver_financiera';
    }

    public function evidenciaLiberarObligatoria(): bool
    {
        $raw = strtolower(trim((string) $this->valorEscalar(self::CLAVE_EVIDENCIA_LIBERAR, 'opcional')));

        return $raw === 'obligatoria';
    }

    public function diasResguardo(): int
    {
        return $this->preparacionConfig->diasResguardo();
    }

    public function recordatorioHoraLocal(): string
    {
        return $this->preparacionConfig->recordatorioHoraLocal();
    }

    public function zonaHoraria(): string
    {
        return $this->preparacionConfig->zonaHoraria();
    }

    /**
     * Snapshot de la regla efectiva al calcular un plazo (tareas existentes no se recalculan).
     *
     * @return array{dias: int, dias_tipo: string, zona_horaria: string, recordatorio_hora_local: string, calculado_at: string}
     */
    public function snapshotRegla(): array
    {
        return [
            'dias' => $this->diasResguardo(),
            'dias_tipo' => $this->diasTipo(),
            'zona_horaria' => $this->zonaHoraria(),
            'recordatorio_hora_local' => $this->recordatorioHoraLocal(),
            'calculado_at' => now($this->zonaHoraria())->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function todas(): array
    {
        return [
            'activo' => $this->activo(),
            'departamentos_habilitados' => $this->departamentosHabilitados(),
            'usuarios_piloto' => $this->usuariosPiloto(),
            'dias_resguardo' => $this->diasResguardo(),
            'dias_tipo' => $this->diasTipo(),
            'recordatorio_hora_local' => $this->recordatorioHoraLocal(),
            'zona_horaria' => $this->zonaHoraria(),
            'anticipacion_aviso_horas' => $this->anticipacionAvisoHoras(),
            'rol_resolutor_financiero' => $this->rolResolutorFinanciero(),
            'evidencia_liberar' => $this->evidenciaLiberarObligatoria() ? 'obligatoria' : 'opcional',
        ];
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'_esc');
        Cache::forget('configuraciones_sistema_globales');
    }

    /**
     * @return array<string, mixed>
     */
    private function flag(): array
    {
        $raw = Cache::remember(self::CACHE_KEY, 60, function () {
            $row = ConfiguracionSistema::query()->where('clave', self::CLAVE_FLAG)->first();
            if (! $row || $row->valor === null || $row->valor === '') {
                return self::flagPorDefecto();
            }

            $decoded = json_decode((string) $row->valor, true);

            return is_array($decoded) ? array_merge(self::flagPorDefecto(), $decoded) : self::flagPorDefecto();
        });

        return is_array($raw) ? $raw : self::flagPorDefecto();
    }

    private function valorEscalar(string $clave, mixed $default): mixed
    {
        $mapa = Cache::remember(self::CACHE_KEY.'_esc', 60, function () {
            return ConfiguracionSistema::query()
                ->whereIn('clave', [
                    self::CLAVE_DIAS_TIPO,
                    self::CLAVE_ANTICIPACION_AVISO_HORAS,
                    self::CLAVE_ROL_RESOLUTOR,
                    self::CLAVE_EVIDENCIA_LIBERAR,
                ])
                ->pluck('valor', 'clave')
                ->all();
        });

        if (! array_key_exists($clave, $mapa) || $mapa[$clave] === null || $mapa[$clave] === '') {
            return $default;
        }

        return $mapa[$clave];
    }

    /**
     * @return list<int>
     */
    private function enteros(mixed $lista): array
    {
        if (! is_array($lista)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($lista, fn ($v) => $v !== null && $v !== ''))));
    }
}
