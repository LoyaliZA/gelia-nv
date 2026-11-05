<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\ActualizarPlazosRetrasoPedidoBmaRequest;
use App\Models\ConfiguracionSistema;
use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;
use App\Services\ControlPedidos\ConfiguracionOperativaTienda;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

final class ConfiguracionOperativaController extends Controller
{
    public function index(ConfiguracionOperativaTienda $tienda, PlazosRetrasoPedidoBmaConfig $plazos, PreparacionTiendaConfig $preparacion)
    {
        return Inertia::render('Admin/ConfiguracionOperativa/Index', [
            'tienda' => $tienda->obtener(), 'plazos' => $plazos->obtener(),
            'general' => $this->general($preparacion),
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
        $datos = $request->validate($reglas);
        DB::transaction(function () use ($datos, $tienda, $plazos, $preparacion) {
            $antes = ['tienda' => $tienda->obtener(), 'plazos' => $plazos->obtener(), 'general' => $this->general($preparacion)];
            ConfiguracionSistema::updateOrCreate(['clave' => ConfiguracionOperativaTienda::CLAVE], [
                'valor' => json_encode($datos['tienda']), 'tipo' => 'json', 'grupo' => 'ControlPedidos', 'descripcion' => 'Horarios y plazos operativos de Tienda',
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
            RegistrarAuditoriaConfiguracionService::ejecutar('Configuración operativa', 'Actualización de horarios y plazos', ['anterior' => $antes, 'nuevo' => $datos]);
        });
        $preparacion->olvidarCache();
        Cache::forget(PlazosRetrasoPedidoBmaConfig::CACHE_KEY);

        return back()->with('success', 'Configuración operativa guardada.');
    }
}
