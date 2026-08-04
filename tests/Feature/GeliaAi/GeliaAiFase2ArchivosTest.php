<?php

namespace Tests\Feature\GeliaAi;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacen;
use App\Models\User;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\InspeccionarArchivoGeliaAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiFase2ArchivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        Role::findOrCreate('Super Admin', 'web');
        foreach ([
            'almacenes.costos.importar',
            'almacenes.inventarios.importar',
            'listados.ver',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'gelia_ai.acceso_modo' => 'super_admin',
            'gelia_ai.model' => 'deepseek-chat',
        ]);
        Storage::fake('local');
    }

    public function test_upload_rechaza_mas_de_10_archivos(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->create("f{$i}.csv", 10, 'text/csv');
        }

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('gelia_ai.archivos.store'), ['archivos' => $files])
            ->assertStatus(422);
    }

    public function test_upload_inspecciona_y_devuelve_file_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $csv = UploadedFile::fake()->createWithContent(
            'costos.csv',
            "sku,costo\nABC123,10.5\nDEF456,20\n"
        );

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('gelia_ai.archivos.store'), ['archivos' => [$csv]])
            ->assertOk()
            ->assertJsonStructure(['files' => [['file_id', 'original_name', 'kind', 'headers', 'rows']]]);

        $file = $response->json('files.0');
        $this->assertSame('costos', $file['kind']);
        $this->assertGreaterThanOrEqual(2, $file['rows']);
        $this->assertContains('sku', array_map('strtolower', $file['headers']));
    }

    public function test_ejecutar_import_sin_permiso_403(): void
    {
        config(['gelia_ai.acceso_modo' => 'general']);
        $user = User::factory()->create();
        // Acceso al chat (modo general) pero sin permiso de importar.
        Permission::findOrCreate('gelia_ai.usar', 'web');

        $almacen = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'CEDIS', 'activo' => true]);
        $meta = $this->subirCsvComoUsuario($user, "sku,costo\nX1,1\n");

        $this->actingAs($user)
            ->postJson(route('gelia_ai.acciones.ejecutar'), [
                'accion' => 'importar_costos',
                'confirmado' => true,
                'payload' => [
                    'file_id' => $meta['file_id'],
                    'almacen_id' => $almacen->id,
                    'mapping' => ['sku' => 'sku', 'costo' => 'costo'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_ejecutar_import_costos_ok_con_reporte(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $admin->givePermissionTo('almacenes.costos.importar');

        $almacen = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'CEDIS Central', 'activo' => true]);
        $meta = $this->subirCsvComoUsuario($admin, "sku,costo\nABC,9.9\n");

        $this->actingAs($admin)
            ->postJson(route('gelia_ai.acciones.ejecutar'), [
                'accion' => 'importar_costos',
                'confirmado' => true,
                'payload' => [
                    'file_id' => $meta['file_id'],
                    'almacen_codigo' => 'CEDIS',
                    'mapping' => ['sku' => 'sku', 'costo' => 'costo'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('accion', 'importar_costos')
            ->assertJsonStructure(['reporte' => ['resumen', 'log_id', 'conteos']]);

        Queue::assertPushed(ImportarAlmacenCatalogoJob::class);
        $this->assertNotNull($almacen->id);
    }

    public function test_chat_con_file_ids_no_envia_filas_a_deepseek(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Propongo importar costos a CEDIS.',
                    ],
                ]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $celdaSecreta = 'VALOR_CELDA_SECRETA_999';
        $meta = $this->subirCsvComoUsuario($admin, "sku,costo\nSKU1,{$celdaSecreta}\n");

        $this->actingAs($admin)
            ->postJson(route('gelia_ai.chat'), [
                'message' => 'Importa estos costos a CEDIS',
                'messages' => [],
                'file_ids' => [$meta['file_id']],
            ])
            ->assertOk();

        Http::assertSent(function ($request) use ($celdaSecreta, $meta) {
            $body = $request->body();
            $this->assertStringNotContainsString($celdaSecreta, $body);
            $this->assertStringNotContainsString('SKU1', $body);
            $this->assertStringContainsString($meta['file_id'], $body);
            $this->assertStringContainsString('headers', $body);

            return true;
        });
    }

    /**
     * @return array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}
     */
    private function subirCsvComoUsuario(User $user, string $contenido): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gelia');
        file_put_contents($tmp, $contenido);
        $upload = new UploadedFile($tmp, 'datos.csv', 'text/csv', null, true);

        return app(GeliaAiArchivoService::class)->guardarUno(
            $user,
            $upload,
            app(InspeccionarArchivoGeliaAiService::class),
        );
    }
}
