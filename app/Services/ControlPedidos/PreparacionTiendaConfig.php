<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PreparacionTiendaConfig
{
    public const CLAVE_FLAG = 'control_pedidos.preparacion_tienda';

    public const CLAVE_DIAS_RESGUARDO = 'control_pedidos.preparacion.dias_resguardo';

    public const CLAVE_RECORDATORIO_HORA = 'control_pedidos.preparacion.recordatorio_hora_local';

    public const CLAVE_ZONA_HORARIA = 'control_pedidos.preparacion.zona_horaria';

    public const CACHE_KEY = 'control_pedidos.preparacion_tienda.config';

    /**
     * @return array{activo: bool, modalidades_habilitadas: list<string>, departamentos_habilitados: list<int>, almacenes_habilitados: list<int>, usuarios_piloto: list<int>}
     */
    public static function flagPorDefecto(): array
    {
        return [
            'activo' => false,
            'modalidades_habilitadas' => [],
            'departamentos_habilitados' => [],
            'almacenes_habilitados' => [],
            'usuarios_piloto' => [],
        ];
    }

    public function activo(): bool
    {
        return (bool) ($this->flag()['activo'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function modalidadesHabilitadas(): array
    {
        $lista = $this->flag()['modalidades_habilitadas'] ?? [];

        return array_values(array_filter(array_map('strval', is_array($lista) ? $lista : [])));
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
    public function almacenesHabilitados(): array
    {
        return $this->enteros($this->flag()['almacenes_habilitados'] ?? []);
    }

    /**
     * @return list<int>
     */
    public function usuariosPiloto(): array
    {
        return $this->enteros($this->flag()['usuarios_piloto'] ?? []);
    }

    public function diasResguardo(): int
    {
        $n = (int) $this->valorEscalar(self::CLAVE_DIAS_RESGUARDO, '3');

        return max(1, min(90, $n));
    }

    public function recordatorioHoraLocal(): string
    {
        $raw = trim((string) $this->valorEscalar(self::CLAVE_RECORDATORIO_HORA, '11:00'));

        return preg_match('/^\d{2}:\d{2}$/', $raw) ? $raw : '11:00';
    }

    public function zonaHoraria(): string
    {
        $raw = trim((string) $this->valorEscalar(self::CLAVE_ZONA_HORARIA, 'America/Mexico_City'));

        return $raw !== '' ? $raw : 'America/Mexico_City';
    }

    public function modalidadPermitida(string $codigo): bool
    {
        if (! $this->activo()) {
            return false;
        }

        $habilitadas = $this->modalidadesHabilitadas();
        if ($habilitadas === []) {
            return false;
        }

        return in_array($codigo, $habilitadas, true);
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

    public function almacenPermitido(int $almacenId): bool
    {
        $habilitados = $this->almacenesHabilitados();
        if ($habilitados === []) {
            return true;
        }

        return in_array($almacenId, $habilitados, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function todas(): array
    {
        return [
            'activo' => $this->activo(),
            'modalidades_habilitadas' => $this->modalidadesHabilitadas(),
            'departamentos_habilitados' => $this->departamentosHabilitados(),
            'almacenes_habilitados' => $this->almacenesHabilitados(),
            'usuarios_piloto' => $this->usuariosPiloto(),
            'dias_resguardo' => $this->diasResguardo(),
            'recordatorio_hora_local' => $this->recordatorioHoraLocal(),
            'zona_horaria' => $this->zonaHoraria(),
            'modalidades_catalogo' => CatalogoModalidadPreparacionPedido::query()
                ->where('activo', true)
                ->whereIn('codigo', CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES)
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre', 'descripcion']),
        ];
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
                    self::CLAVE_DIAS_RESGUARDO,
                    self::CLAVE_RECORDATORIO_HORA,
                    self::CLAVE_ZONA_HORARIA,
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
