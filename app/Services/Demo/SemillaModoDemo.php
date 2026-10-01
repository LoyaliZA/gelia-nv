<?php

namespace App\Services\Demo;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Producto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Scopes\EsDemoScope;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SemillaModoDemo
{
    public const CODIGO_SUCURSAL = 'DEMO';

    public const USERNAME = 'revision.play';

    public const ROL = 'Revisión Play';

    public function __construct(
        private readonly AlcanceDemo $alcance,
    ) {}

    public function sembrar(): void
    {
        $this->alcance->sinFiltro(function (): void {
            $sucursal = $this->sucursal();
            $this->origen();
            $cliente = $this->clientes();
            $this->productos();
            $usuario = $this->cuenta($sucursal);
            $this->sembrarOperacion($sucursal, $cliente, $usuario);
        });
    }

    public function reiniciarOperacion(): void
    {
        $this->alcance->sinFiltro(function (): void {
            $this->borrarOperacion();
            $sucursal = $this->sucursal();
            $cliente = $this->clientes();
            $usuario = $this->cuenta($sucursal);
            $this->sembrarOperacion($sucursal, $cliente, $usuario);
        });
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::withoutGlobalScope(EsDemoScope::class)->updateOrCreate(
            ['codigo' => self::CODIGO_SUCURSAL],
            [
                'nombre' => 'Sucursal demostración',
                'activo' => true,
                'es_demo' => true,
            ]
        );
    }

    private function origen(): Departamento
    {
        return Departamento::withoutGlobalScope(EsDemoScope::class)->updateOrCreate(
            ['codigo' => 'DEMO'],
            [
                'nombre' => 'Mostrador demostración',
                'activo' => true,
                'visible_origen_resguardo_pdv' => true,
                'es_demo' => true,
            ]
        );
    }

    private function clientes(): Cliente
    {
        $lista = CatalogoListaDescuento::query()->firstOrCreate(
            ['nombre' => 'Lista demostración'],
            [
                'monto_requerido' => 0,
                'activo' => true,
            ]
        );

        $primero = null;
        foreach ([
            ['DEMO-001', 'Cliente demostración Uno', '5551000001'],
            ['DEMO-002', 'Cliente demostración Dos', '5551000002'],
            ['DEMO-003', 'Cliente demostración Tres', '5551000003'],
        ] as [$numero, $nombre, $telefono]) {
            $cliente = Cliente::withoutGlobalScope(EsDemoScope::class)->updateOrCreate(
                ['numero_cliente' => $numero],
                [
                    'nombre' => $nombre,
                    'nombre_razon_social' => $nombre,
                    'telefono' => $telefono,
                    'correo_electronico' => strtolower($numero).'@demo.invalid',
                    'lista_actual_id' => $lista->id,
                    'es_demo' => true,
                    'es_inactivo' => false,
                ]
            );
            $primero ??= $cliente;
        }

        return $primero;
    }

    private function productos(): void
    {
        foreach ([
            [900001, 'DEMO-MUESTRA-1', 'Muestra demostración uno'],
            [900002, 'DEMO-MUESTRA-2', 'Muestra demostración dos'],
            [900003, 'DEMO-MUESTRA-3', 'Muestra demostración tres'],
        ] as [$folio, $sku, $descripcion]) {
            Producto::withoutGlobalScope(EsDemoScope::class)->updateOrCreate(
                ['sku' => $sku],
                [
                    'folio' => $folio,
                    'descripcion' => $descripcion,
                    'activo' => true,
                    'es_demo' => true,
                ]
            );
        }
    }

    private function cuenta(Sucursal $sucursal): ?User
    {
        $permisos = $this->permisosPiso();
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $rol = Role::findOrCreate(self::ROL, 'web');
        $rol->syncPermissions($permisos);

        $password = config('demo.review_password');
        $existente = User::query()->where('username', self::USERNAME)->first();

        if (! is_string($password) || $password === '') {
            if ($existente instanceof User) {
                $this->vincularCuenta($existente, $sucursal, $rol, $permisos);
            }

            return $existente;
        }

        $usuario = User::query()->updateOrCreate(
            ['username' => self::USERNAME],
            [
                'name' => 'Revisión Play',
                'email' => 'revision.play@demo.invalid',
                'password' => $password,
                'es_demo' => true,
            ]
        );

        $this->vincularCuenta($usuario, $sucursal, $rol, $permisos);

        return $usuario;
    }

    /**
     * @param  list<string>  $permisos
     */
    private function vincularCuenta(User $usuario, Sucursal $sucursal, Role $rol, array $permisos): void
    {
        $usuario->forceFill(['es_demo' => true])->save();
        $usuario->syncRoles([$rol]);
        $usuario->syncPermissions($permisos);

        $ajenas = $usuario->sucursales()->newPivotQuery()
            ->where('user_id', $usuario->id)
            ->where('sucursal_id', '!=', $sucursal->id)
            ->pluck('sucursal_id');

        if ($ajenas->isNotEmpty()) {
            $usuario->sucursales()->detach($ajenas->all());
        }

        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);
    }

    /**
     * @return list<string>
     */
    private function permisosPiso(): array
    {
        return [
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS,
            PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD,
            'clientes.ver',
        ];
    }

    private function sembrarOperacion(Sucursal $sucursal, Cliente $cliente, ?User $usuario): void
    {
        $this->resguardo(
            'DEMO-PEND',
            $sucursal,
            $cliente,
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            now()->subHour(),
            null,
            null,
        );
        $this->resguardo(
            'DEMO-CUST',
            $sucursal,
            $cliente,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            now()->subDays(2),
            now()->subDays(2),
            now()->subDay(),
        );
        $vencido = $this->resguardo(
            'DEMO-VENC',
            $sucursal,
            $cliente,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            now()->subDays(40),
            now()->subDays(40),
            now()->subDays(39),
        );

        ResguardoPdvEvento::query()->firstOrCreate(
            [
                'resguardo_id' => $vencido->id,
                'tipo_evento' => ResguardoPdvEvento::TIPO_MARCADO_VENCIDO,
            ],
            [
                'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                'actor_id' => $usuario?->id,
                'ocurrido_at' => now()->subDays(5),
                'snapshot_json' => ['origen' => 'semilla_demo'],
            ]
        );

        TurnoPdv::withoutGlobalScope(EsDemoScope::class)->firstOrCreate(
            [
                'sucursal_id' => $sucursal->id,
                'folio' => 'DEMO-T1',
                'fecha_operativa' => now()->toDateString(),
            ],
            [
                'cliente_id' => $cliente->id,
                'servicio' => TurnoPdv::SERVICIO_VENTAS,
                'origen' => TurnoPdv::ORIGEN_RECEPCION,
                'estado' => TurnoPdv::ESTADO_EN_COLA,
                'prioridad' => TurnoPdv::PRIORIDAD_NORMAL,
                'snapshot_nombre_llamado' => $cliente->nombre,
                'snapshot_cliente_nombre' => $cliente->nombre,
                'snapshot_json' => ['folio' => 'DEMO-T1'],
                'alta_at' => now(),
                'alta_por_id' => $usuario?->id,
                'es_demo' => true,
                'version' => 1,
            ]
        );
    }

    private function resguardo(
        string $folio,
        Sucursal $sucursal,
        Cliente $cliente,
        string $estado,
        Carbon $salida,
        ?Carbon $recepcion,
        ?Carbon $custodia,
    ): ResguardoPdv {
        return ResguardoPdv::withoutGlobalScope(EsDemoScope::class)->firstOrCreate(
            [
                'sucursal_id' => $sucursal->id,
                'snapshot_folio' => $folio,
            ],
            [
                'cliente_id' => $cliente->id,
                'estado' => $estado,
                'cantidad_bultos_esperada' => 1,
                'salida_cedis_at' => $salida,
                'recepcion_fisica_at' => $recepcion,
                'custodia_confirmada_at' => $custodia,
                'entrega_bloqueada' => false,
                'snapshot_cliente_nombre' => $cliente->nombre,
                'snapshot_json' => ['folio' => $folio, 'handoff' => 'demo'],
                'es_demo' => true,
                'version' => 1,
            ]
        );
    }

    private function borrarOperacion(): void
    {
        $resguardoIds = DB::table('pdv_resguardos')->where('es_demo', true)->pluck('id');
        $turnoIds = DB::table('pdv_turnos')->where('es_demo', true)->pluck('id');

        if (DB::table('pdv_resguardos')->whereIn('id', $resguardoIds)->where('es_demo', false)->exists()
            || DB::table('pdv_turnos')->whereIn('id', $turnoIds)->where('es_demo', false)->exists()) {
            throw new RuntimeException('demo:reset se negó a borrar filas que no son de demostración.');
        }

        if ($resguardoIds->isNotEmpty()) {
            $entregaIds = DB::table('pdv_resguardo_entregas')->whereIn('resguardo_id', $resguardoIds)->pluck('id');
            DB::table('pdv_resguardo_evidencias')->whereIn('resguardo_id', $resguardoIds)->delete();
            if ($entregaIds->isNotEmpty()) {
                DB::table('pdv_resguardo_entrega_bultos')->whereIn('entrega_id', $entregaIds)->delete();
            }
            DB::table('pdv_resguardo_entregas')->whereIn('resguardo_id', $resguardoIds)->delete();
            DB::table('pdv_resguardo_incidencias')->whereIn('resguardo_id', $resguardoIds)->delete();
            DB::table('pdv_resguardo_eventos')->whereIn('resguardo_id', $resguardoIds)->delete();
            DB::table('pdv_resguardo_bultos')->whereIn('resguardo_id', $resguardoIds)->delete();
            DB::table('pdv_resguardos')->where('es_demo', true)->whereIn('id', $resguardoIds)->delete();
        }

        if ($turnoIds->isNotEmpty()) {
            DB::table('pdv_turnos')->whereIn('id', $turnoIds)->update(['atencion_actual_id' => null]);
            $atencionIds = DB::table('pdv_turno_atenciones')->whereIn('turno_id', $turnoIds)->pluck('id');
            if ($atencionIds->isNotEmpty()) {
                DB::table('pdv_turno_prorrogas')->whereIn('atencion_id', $atencionIds)->delete();
            }
            DB::table('pdv_turno_eventos')->whereIn('turno_id', $turnoIds)->delete();
            DB::table('pdv_turno_atenciones')->whereIn('turno_id', $turnoIds)->delete();
            DB::table('pdv_turnos')->where('es_demo', true)->whereIn('id', $turnoIds)->delete();
        }

        Storage::disk('local')->deleteDirectory('demo/resguardos');
    }
}
