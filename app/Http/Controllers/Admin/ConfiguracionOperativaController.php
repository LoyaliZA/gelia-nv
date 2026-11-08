<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\ActualizarPlazosRetrasoPedidoBmaRequest;
use App\Models\ConfiguracionSistema;
use App\Models\Departamento;
use App\Services\ControlPedidos\BancosGestionPedidosConfig;
use App\Services\ControlPedidos\PrioridadAtencionPedidosConfig;
use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;
use App\Services\ControlPedidos\ConfiguracionOperativaTienda;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class ConfiguracionOperativaController extends Controller
{
    public function index(ConfiguracionOperativaTienda $tienda, PlazosRetrasoPedidoBmaConfig $plazos, PreparacionTiendaConfig $preparacion)
    {
        return Inertia::render('Admin/ConfiguracionOperativa/Index', [
            'tienda' => $tienda->obtener(), 'plazos' => $plazos->obtener(),
            'general' => $this->general($preparacion),
            'bancos' => app(BancosGestionPedidosConfig::class)->configurables(),
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'prioridades' => app(PrioridadAtencionPedidosConfig::class)->obtener(),
            'tipos_prioridad' => PrioridadAtencionPedidosConfig::TIPOS,
        ]);
    }

    private function general(PreparacionTiendaConfig $config): array
    {
        return ['zona_horaria' => $config->zonaHoraria(), 'recordatorio_hora' => $config->recordatorioHoraLocal(),
            'cierre_operativo' => $config->cierreOperativoHora(), 'dias_resguardo' => $config->diasResguardo(),
            'dias_prorroga_vip' => $config->diasProrrogaVip()];
    }

    public function update(Request $request, ConfiguracionOperativaTienda $tienda, PlazosRetrasoPedidoBmaConfig $plazos, PreparacionTiendaConfig $preparacion)
    {
        $hora = ['required', 'date_format:H:i'];
        $reglas = [
            'tienda.md_corte' => $hora, 'tienda.apartado_cierre' => $hora,
            'tienda.sabado_apertura' => $hora, 'tienda.sabado_cierre' => [...$hora, 'after:tienda.sabado_apertura'],
            'tienda.horarios_activos' => ['sometimes', 'boolean'],
            'tienda.seguimiento_entrega_pdv' => ['sometimes', 'boolean'],
            'tienda.recoleccion_dias' => ['required', 'integer', 'between:1,365'],
            'general.zona_horaria' => ['required', 'timezone'],
            'general.recordatorio_hora' => $hora, 'general.cierre_operativo' => $hora,
            'general.dias_resguardo' => ['required', 'integer', 'between:1,90'],
            'general.dias_prorroga_vip' => ['required', 'integer', 'between:1,5'],
        ];
        foreach (['md_dias', 'apartado_dias'] as $campo) {
            $reglas['tienda.'.$campo] = ['required', 'array', 'min:1', 'max:7'];
            $reglas['tienda.'.$campo.'.*'] = ['integer', 'distinct', 'between:1,7'];
        }
        foreach ((new ActualizarPlazosRetrasoPedidoBmaRequest)->rules() as $campo => $validacion) {
            $reglas['plazos.'.$campo] = $validacion;
        }
        $reglas['plazos.hora_corte'] = $hora;
        $reglas += [
            'bancos' => ['sometimes', 'array'],
            'bancos.*' => ['array:id,habilitado,compartido,departamento_ids'],
            'bancos.*.id' => ['required', 'integer', 'distinct', 'exists:catalogo_bancos,id'],
            'bancos.*.habilitado' => ['required', 'boolean'],
            'bancos.*.compartido' => ['required', 'boolean'],
            'bancos.*.departamento_ids' => ['present', 'array'],
            'bancos.*.departamento_ids.*' => ['integer', 'exists:departamentos,id'],
            'prioridades' => ['sometimes', 'array', 'size:'.count(PrioridadAtencionPedidosConfig::TIPOS)],
            'prioridades.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PrioridadAtencionPedidosConfig::TIPOS))],
        ];
        $validator = Validator::make($request->all(), $reglas);
        $validator->after(function ($validator) use ($request) {
            foreach ((array) $request->input('bancos', []) as $i => $banco) {
                if (is_array($banco) && is_array($banco['departamento_ids'] ?? null)
                    && count(array_unique($banco['departamento_ids'], SORT_REGULAR)) !== count($banco['departamento_ids'])) {
                    $validator->errors()->add("bancos.$i.departamento_ids", 'No repita departamentos en el mismo banco.');
                }
                if (is_array($banco) && filter_var($banco['habilitado'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    && ! filter_var($banco['compartido'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    && empty($banco['departamento_ids'])) {
                    $validator->errors()->add("bancos.$i.departamento_ids", 'Seleccione al menos un departamento autorizado.');
                }
            }
        });
        $datos = $validator->validate();
        DB::transaction(function () use ($datos, $tienda, $plazos, $preparacion) {
            $antes = ['tienda' => $tienda->obtener(), 'plazos' => $plazos->obtener(), 'general' => $this->general($preparacion),
                'bancos' => app(BancosGestionPedidosConfig::class)->configurables()->all(),
                'prioridades' => app(PrioridadAtencionPedidosConfig::class)->obtener()];
            ConfiguracionSistema::updateOrCreate(['clave' => ConfiguracionOperativaTienda::CLAVE], [
                'valor' => json_encode(array_replace($antes['tienda'], $datos['tienda'])), 'tipo' => 'json', 'grupo' => 'ControlPedidos', 'descripcion' => 'Horarios, plazos y pasos opcionales de Tienda',
            ]);
            $claves = ['zona_horaria' => PreparacionTiendaConfig::CLAVE_ZONA_HORARIA, 'recordatorio_hora' => PreparacionTiendaConfig::CLAVE_RECORDATORIO_HORA,
                'cierre_operativo' => PreparacionTiendaConfig::CLAVE_CIERRE_OPERATIVO, 'dias_resguardo' => PreparacionTiendaConfig::CLAVE_DIAS_RESGUARDO,
                'dias_prorroga_vip' => PreparacionTiendaConfig::CLAVE_DIAS_PRORROGA_VIP];
            foreach ($claves as $campo => $clave) {
                ConfiguracionSistema::updateOrCreate(['clave' => $clave], [
                    'valor' => (string) $datos['general'][$campo], 'tipo' => str_starts_with($campo, 'dias_') ? 'integer' : 'string',
                    'grupo' => 'ControlPedidos', 'descripcion' => 'Preparación Tienda: '.str_replace('_', ' ', $campo),
                ]);
            }
            $plazos->guardar($datos['plazos']);
            if (array_key_exists('bancos', $datos)) {
                app(BancosGestionPedidosConfig::class)->guardar($datos['bancos']);
            }
            if (array_key_exists('prioridades', $datos)) {
                app(PrioridadAtencionPedidosConfig::class)->guardar($datos['prioridades']);
            }
            RegistrarAuditoriaConfiguracionService::ejecutar('Configuración operativa', 'Actualización de configuración operativa', ['anterior' => $antes, 'nuevo' => $datos]);
        });
        $preparacion->olvidarCache();
        Cache::forget(PlazosRetrasoPedidoBmaConfig::CACHE_KEY);

        return back()->with('success', 'Configuración operativa guardada.');
    }
}
