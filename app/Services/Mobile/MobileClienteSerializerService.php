<?php

namespace App\Services\Mobile;

use App\Models\Cliente;
use App\Models\User;

class MobileClienteSerializerService
{
    public function __construct(
        protected MobileClienteAlcanceService $alcance
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serializar(Cliente $cliente, User $user): array
    {
        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        $mapa = [
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'nombre_razon_social' => $cliente->nombre_razon_social,
            'rfc' => $cliente->rfc,
            'lista_actual_id' => $cliente->lista_actual_id,
            'lista_descuento' => $cliente->listaDescuento?->nombre,
            'vendedor_id' => $cliente->vendedor_id,
            'vendedor_original_id' => $cliente->vendedor_original_id,
            'vendedor' => $cliente->vendedor?->name,
            'catalogo_tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente' => $cliente->tipo?->nombre,
            'es_inactivo' => (bool) $cliente->es_inactivo,
            'es_heredado' => (bool) $cliente->es_heredado,
            'lista_bloqueada' => (bool) $cliente->lista_bloqueada,
            'created_at' => optional($cliente->created_at)?->toIso8601String(),
            'updated_at' => optional($cliente->updated_at)?->toIso8601String(),
            'monto_credito_autorizado' => $cliente->monto_credito_autorizado !== null
                ? (float) $cliente->monto_credito_autorizado
                : null,
            'dias_credito' => $cliente->dias_credito,
            'fecha_inicio_credito' => optional($cliente->fecha_inicio_credito)?->toDateString(),
            'codigo_postal' => $cliente->codigo_postal,
            'regimen_fiscal' => $cliente->regimen_fiscal,
            'correo_electronico' => $cliente->correo_electronico,
            'uso_factura' => $cliente->uso_factura,
            'direccion_fiscal' => $cliente->direccion_fiscal,
            'colonia_fiscal' => $cliente->colonia_fiscal,
            'municipio_fiscal' => $cliente->municipio_fiscal,
            'estado_fiscal' => $cliente->estado_fiscal,
            'pais_fiscal' => $cliente->pais_fiscal,
        ];

        $resultado = [];
        foreach ($this->camposVisibles($user) as $campo) {
            if (array_key_exists($campo, $mapa)) {
                $resultado[$campo] = $mapa[$campo];
            }
        }

        $resultado['id'] = $cliente->id;
        $resultado['numero_cliente'] = $cliente->numero_cliente;
        $resultado['nombre'] = $cliente->nombre;
        $resultado['alcance'] = $this->alcance->tipoAlcance($user);

        return $resultado;
    }

    /**
     * @return array<int, string>
     */
    public function camposVisibles(User $user): array
    {
        $campos = [];
        $grupos = config('mobile.campos');
        if (! is_array($grupos) || $grupos === []) {
            $grupos = [
                'base' => [
                    'id', 'numero_cliente', 'nombre', 'nombre_razon_social', 'rfc',
                    'lista_actual_id', 'lista_descuento', 'vendedor_id', 'vendedor_original_id',
                    'vendedor', 'catalogo_tipo_cliente_id', 'tipo_cliente', 'es_inactivo',
                    'es_heredado', 'lista_bloqueada', 'created_at', 'updated_at',
                ],
                'credito' => ['monto_credito_autorizado', 'dias_credito', 'fecha_inicio_credito'],
                'fiscal' => [
                    'codigo_postal', 'regimen_fiscal', 'correo_electronico', 'uso_factura',
                    'direccion_fiscal', 'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal', 'pais_fiscal',
                ],
            ];
        }
        $permisos = config('mobile.permisos_campos', []);

        foreach ($grupos as $grupo => $slugs) {
            if ($this->grupoVisible($user, $permisos[$grupo] ?? [])) {
                $campos = array_merge($campos, $slugs);
            }
        }

        return array_values(array_unique($campos));
    }

    /**
     * @param  array<int, string>  $permisosRequeridos
     */
    private function grupoVisible(User $user, array $permisosRequeridos): bool
    {
        if ($permisosRequeridos === []) {
            return true;
        }

        foreach ($permisosRequeridos as $permiso) {
            if ($this->alcance->puede($user, $permiso)) {
                return true;
            }
        }

        return false;
    }
}
