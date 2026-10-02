<?php

use App\Models\ControlPedidos\PedidoBmaReferencia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_bma', 'origen_solicitud')) {
                $table->string('origen_solicitud', 32)->nullable()->after('folio_remision');
            }
            if (! Schema::hasColumn('pedidos_bma', 'documento_inicial')) {
                $table->string('documento_inicial', 32)->nullable()->after('origen_solicitud');
            }
            if (! Schema::hasColumn('pedidos_bma', 'contacto_nombre_snapshot')) {
                $table->string('contacto_nombre_snapshot')->nullable()->after('documento_inicial');
            }
            if (! Schema::hasColumn('pedidos_bma', 'contacto_telefono_snapshot')) {
                $table->string('contacto_telefono_snapshot', 40)->nullable()->after('contacto_nombre_snapshot');
            }
            if (! Schema::hasColumn('pedidos_bma', 'prioridad_md')) {
                $table->boolean('prioridad_md')->default(false)->after('contacto_telefono_snapshot');
            }
        });

        if (! Schema::hasTable('pedido_bma_referencias')) {
            Schema::create('pedido_bma_referencias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
                $table->string('tipo', 32);
                $table->string('folio', 64);
                $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('registrado_at');
                $table->foreignId('documento_id')->nullable()->constrained('pedido_bma_documentos')->nullOnDelete();
                $table->string('observaciones', 500)->nullable();
                $table->boolean('vigente')->default(true);
                $table->timestamps();

                $table->index(['pedido_bma_id', 'vigente']);
                $table->index(['tipo', 'folio']);
            });
        }

        DB::table('pedidos_bma')
            ->whereNotNull('folio_remision')
            ->where('folio_remision', '!=', '')
            ->orderBy('id')
            ->select(['id', 'folio_remision'])
            ->chunkById(200, function ($pedidos) {
                $ahora = now();
                foreach ($pedidos as $pedido) {
                    $existe = DB::table('pedido_bma_referencias')
                        ->where('pedido_bma_id', $pedido->id)
                        ->where('tipo', PedidoBmaReferencia::TIPO_SIN_CLASIFICAR)
                        ->where('folio', $pedido->folio_remision)
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    DB::table('pedido_bma_referencias')->insert([
                        'pedido_bma_id' => $pedido->id,
                        'tipo' => PedidoBmaReferencia::TIPO_SIN_CLASIFICAR,
                        'folio' => $pedido->folio_remision,
                        'registrado_por_id' => null,
                        'registrado_at' => $ahora,
                        'documento_id' => null,
                        'observaciones' => 'Backfill de folio documental previo, sin clasificar.',
                        'vigente' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_referencias');

        Schema::table('pedidos_bma', function (Blueprint $table) {
            foreach ([
                'prioridad_md',
                'contacto_telefono_snapshot',
                'contacto_nombre_snapshot',
                'documento_inicial',
                'origen_solicitud',
            ] as $columna) {
                if (Schema::hasColumn('pedidos_bma', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
