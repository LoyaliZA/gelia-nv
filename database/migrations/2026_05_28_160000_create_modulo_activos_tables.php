<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_tipos_activo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->enum('categoria', ['fisico', 'tecnologico', 'intangible', 'vestimenta']);
            $table->string('icono')->nullable();
            $table->json('esquema_atributos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('catalogo_tipo_activo_id')->constrained('catalogo_tipos_activo');
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['disponible', 'asignado', 'mantenimiento', 'baja'])->default('disponible');
            $table->json('atributos')->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registrado_por_id')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['departamento_id', 'estado']);
            $table->index('responsable_user_id');
            $table->index('fecha_vencimiento');
        });

        Schema::create('activo_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('asignado_por_id')->constrained('users');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['activo_id', 'activa']);
            $table->index('user_id');
        });

        Schema::create('activo_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('tipo', [
                'creacion', 'edicion', 'asignacion', 'reasignacion',
                'devolucion', 'transferencia', 'cambio_estado', 'baja',
            ]);
            $table->foreignId('departamento_origen_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('departamento_destino_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('user_destino_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->string('motivo')->nullable();
            $table->text('notas')->nullable();
            $table->json('datos_snapshot')->nullable();
            $table->timestamps();

            $table->index(['activo_id', 'created_at']);
        });

        $this->seedTiposActivo();
        $this->seedPermisos();
    }

    private function seedTiposActivo(): void
    {
        $tipos = [
            [
                'nombre' => 'Mueble',
                'slug' => 'mueble',
                'categoria' => 'fisico',
                'icono' => 'Armchair',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        ['key' => 'marca', 'label' => 'Marca', 'type' => 'text'],
                        ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
                        ['key' => 'ubicacion_fisica', 'label' => 'Ubicación física', 'type' => 'text'],
                    ],
                ]),
            ],
            [
                'nombre' => 'Equipo TI',
                'slug' => 'equipo-ti',
                'categoria' => 'tecnologico',
                'icono' => 'Monitor',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        ['key' => 'serial', 'label' => 'Número de serie', 'type' => 'text', 'required' => true],
                        ['key' => 'marca', 'label' => 'Marca', 'type' => 'text'],
                        ['key' => 'modelo', 'label' => 'Modelo', 'type' => 'text'],
                        ['key' => 'ip', 'label' => 'Dirección IP', 'type' => 'text'],
                        ['key' => 'mac', 'label' => 'MAC', 'type' => 'text'],
                        ['key' => 'garantia_hasta', 'label' => 'Garantía hasta', 'type' => 'date'],
                        ['key' => 'proximo_mantenimiento', 'label' => 'Próximo mantenimiento', 'type' => 'date', 'alerta_dias' => 14],
                    ],
                ]),
            ],
            [
                'nombre' => 'Licencia de software',
                'slug' => 'licencia-software',
                'categoria' => 'intangible',
                'icono' => 'Key',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        ['key' => 'proveedor', 'label' => 'Proveedor', 'type' => 'text', 'required' => true],
                        ['key' => 'clave_licencia', 'label' => 'Clave de licencia', 'type' => 'text', 'sensitive' => true],
                        ['key' => 'usuarios_permitidos', 'label' => 'Usuarios permitidos', 'type' => 'number'],
                        ['key' => 'fecha_vencimiento', 'label' => 'Vencimiento', 'type' => 'date', 'required' => true, 'alerta_dias' => 30],
                    ],
                ]),
            ],
            [
                'nombre' => 'Uniforme',
                'slug' => 'uniforme',
                'categoria' => 'vestimenta',
                'icono' => 'Shirt',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        ['key' => 'tipo_prenda', 'label' => 'Tipo de prenda', 'type' => 'select', 'options' => ['Camisa', 'Pantalón', 'Chaleco', 'Gorra', 'Otro'], 'required' => true],
                        ['key' => 'talla', 'label' => 'Talla', 'type' => 'select', 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'required' => true],
                        ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
                    ],
                ]),
            ],
            [
                'nombre' => 'Herramienta / Mantenimiento',
                'slug' => 'herramienta-mantenimiento',
                'categoria' => 'fisico',
                'icono' => 'Wrench',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        ['key' => 'marca', 'label' => 'Marca', 'type' => 'text'],
                        ['key' => 'modelo', 'label' => 'Modelo', 'type' => 'text'],
                        ['key' => 'proximo_mantenimiento', 'label' => 'Próximo mantenimiento', 'type' => 'date', 'alerta_dias' => 14],
                    ],
                ]),
            ],
        ];

        foreach ($tipos as $tipo) {
            DB::table('catalogo_tipos_activo')->insert(array_merge($tipo, [
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedPermisos(): void
    {
        $permisos = [
            'activos.ver',
            'activos.crear',
            'activos.editar',
            'activos.asignar',
            'activos.transferir',
            'activos.cambiar_estado',
            'activos.ver_todos',
            'activos.configurar_tipos',
            'activos.exportar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_movimientos');
        Schema::dropIfExists('activo_asignaciones');
        Schema::dropIfExists('activos');
        Schema::dropIfExists('catalogo_tipos_activo');
    }
};
