<?php

namespace App\Http\Controllers;

use App\Models\Atributo;
use App\Models\AtributoOpcion;
use App\Models\ExtensionProducto;
use App\Models\NotaOlfativa;
use App\Models\UnidadMedida;
use App\Services\Productos\ResolverExtensionesProductoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD de catálogos del maestro universal de productos (atributos, unidades, notas).
 */
class CatalogoProductoEspecificacionesController extends Controller
{
    public function storeAtributo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|unique:atributos,slug',
            'tipo_dato' => ['required', Rule::in(['texto', 'texto_largo', 'entero', 'decimal', 'booleano', 'fecha', 'opcion', 'medida'])],
            'permite_multiples' => 'boolean',
            'dimension_unidad' => 'nullable|string|max:40',
            'filtrable' => 'boolean',
            'visible_en_ficha' => 'boolean',
            'estado' => 'boolean',
            'opciones' => 'nullable|array',
            'opciones.*.nombre' => 'required_with:opciones|string|max:120',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['nombre']);
        if ($slug === '') {
            $slug = 'attr-'.Str::random(6);
        }

        DB::transaction(function () use ($data, $slug) {
            $attr = Atributo::create([
                'nombre' => $data['nombre'],
                'slug' => $slug,
                'tipo_dato' => $data['tipo_dato'],
                'permite_multiples' => (bool) ($data['permite_multiples'] ?? false),
                'dimension_unidad' => $data['dimension_unidad'] ?? null,
                'filtrable' => (bool) ($data['filtrable'] ?? true),
                'buscable' => false,
                'visible_en_ficha' => (bool) ($data['visible_en_ficha'] ?? true),
                'estado' => (bool) ($data['estado'] ?? true),
            ]);

            if (($data['tipo_dato'] ?? '') === 'opcion') {
                foreach (array_values($data['opciones'] ?? []) as $i => $op) {
                    $nombre = trim((string) ($op['nombre'] ?? ''));
                    if ($nombre === '') {
                        continue;
                    }
                    AtributoOpcion::create([
                        'atributo_id' => $attr->id,
                        'nombre' => $nombre,
                        'slug' => Str::slug($nombre) ?: 'op-'.$i,
                        'orden' => $i + 1,
                        'estado' => true,
                    ]);
                }
            }
        });

        return back()->with('success', 'Atributo creado.');
    }

    public function updateAtributo(Request $request, int $id): RedirectResponse
    {
        $attr = Atributo::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('atributos', 'slug')->ignore($attr->id)],
            'tipo_dato' => ['required', Rule::in(['texto', 'texto_largo', 'entero', 'decimal', 'booleano', 'fecha', 'opcion', 'medida'])],
            'permite_multiples' => 'boolean',
            'dimension_unidad' => 'nullable|string|max:40',
            'filtrable' => 'boolean',
            'visible_en_ficha' => 'boolean',
            'estado' => 'boolean',
            'opciones' => 'nullable|array',
            'opciones.*.id' => 'nullable|integer',
            'opciones.*.nombre' => 'required_with:opciones|string|max:120',
            'opciones.*.estado' => 'boolean',
        ]);

        DB::transaction(function () use ($attr, $data) {
            $attr->update([
                'nombre' => $data['nombre'],
                'slug' => $data['slug'] ?: $attr->slug,
                'tipo_dato' => $data['tipo_dato'],
                'permite_multiples' => (bool) ($data['permite_multiples'] ?? false),
                'dimension_unidad' => $data['dimension_unidad'] ?? null,
                'filtrable' => (bool) ($data['filtrable'] ?? true),
                'visible_en_ficha' => (bool) ($data['visible_en_ficha'] ?? true),
                'estado' => (bool) ($data['estado'] ?? true),
            ]);

            if ($attr->tipo_dato === 'opcion' && array_key_exists('opciones', $data)) {
                $kept = [];
                foreach (array_values($data['opciones'] ?? []) as $i => $op) {
                    $nombre = trim((string) ($op['nombre'] ?? ''));
                    if ($nombre === '') {
                        continue;
                    }
                    $payload = [
                        'nombre' => $nombre,
                        'slug' => Str::slug($nombre) ?: 'op-'.$i,
                        'orden' => $i + 1,
                        'estado' => (bool) ($op['estado'] ?? true),
                    ];
                    if (! empty($op['id'])) {
                        $row = AtributoOpcion::query()->where('atributo_id', $attr->id)->where('id', $op['id'])->first();
                        if ($row) {
                            $row->update($payload);
                            $kept[] = $row->id;
                            continue;
                        }
                    }
                    $created = AtributoOpcion::create(array_merge($payload, ['atributo_id' => $attr->id]));
                    $kept[] = $created->id;
                }
                AtributoOpcion::query()
                    ->where('atributo_id', $attr->id)
                    ->whereNotIn('id', $kept)
                    ->update(['estado' => false]);
            }
        });

        return back()->with('success', 'Atributo actualizado.');
    }

    public function destroyAtributo(int $id): RedirectResponse
    {
        $attr = Atributo::findOrFail($id);
        if ($attr->categoriaAtributos()->exists()) {
            $attr->update(['estado' => false]);

            return back()->with('success', 'Atributo desactivado (está asignado a categorías).');
        }
        $attr->delete();

        return back()->with('success', 'Atributo eliminado.');
    }

    public function storeUnidad(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'simbolo' => 'required|string|max:20',
            'dimension' => 'required|string|max:40',
            'decimales' => 'nullable|integer|min:0|max:6',
            'estado' => 'boolean',
        ]);
        UnidadMedida::create([
            'nombre' => $data['nombre'],
            'simbolo' => $data['simbolo'],
            'dimension' => $data['dimension'],
            'decimales' => (int) ($data['decimales'] ?? 2),
            'estado' => (bool) ($data['estado'] ?? true),
        ]);

        return back()->with('success', 'Unidad creada.');
    }

    public function updateUnidad(Request $request, int $id): RedirectResponse
    {
        $u = UnidadMedida::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'simbolo' => 'required|string|max:20',
            'dimension' => 'required|string|max:40',
            'decimales' => 'nullable|integer|min:0|max:6',
            'estado' => 'boolean',
        ]);
        $u->update([
            'nombre' => $data['nombre'],
            'simbolo' => $data['simbolo'],
            'dimension' => $data['dimension'],
            'decimales' => (int) ($data['decimales'] ?? 2),
            'estado' => (bool) ($data['estado'] ?? true),
        ]);

        return back()->with('success', 'Unidad actualizada.');
    }

    public function destroyUnidad(int $id): RedirectResponse
    {
        UnidadMedida::findOrFail($id)->update(['estado' => false]);

        return back()->with('success', 'Unidad desactivada.');
    }

    public function storeNotaOlfativa(Request $request): RedirectResponse
    {
        $this->exigirPerfumeriaEnUso();
        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'boolean',
        ]);
        $slug = Str::slug($data['nombre']) ?: 'nota-'.Str::random(6);
        NotaOlfativa::create([
            'nombre' => $data['nombre'],
            'slug' => $slug,
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => (bool) ($data['estado'] ?? true),
        ]);

        return back()->with('success', 'Nota olfativa creada.');
    }

    public function updateNotaOlfativa(Request $request, int $id): RedirectResponse
    {
        $this->exigirPerfumeriaEnUso();
        $nota = NotaOlfativa::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'boolean',
        ]);
        $nota->update([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => (bool) ($data['estado'] ?? true),
        ]);

        return back()->with('success', 'Nota olfativa actualizada.');
    }

    public function destroyNotaOlfativa(int $id): RedirectResponse
    {
        $this->exigirPerfumeriaEnUso();
        NotaOlfativa::findOrFail($id)->update(['estado' => false]);

        return back()->with('success', 'Nota olfativa desactivada.');
    }

    public function updateExtensionProducto(Request $request, int $id): RedirectResponse
    {
        $ext = ExtensionProducto::findOrFail($id);
        $data = $request->validate([
            'habilitada' => 'required|boolean',
        ]);
        $ext->update(['habilitada' => (bool) $data['habilitada']]);
        app(ResolverExtensionesProductoService::class)->invalidarCacheCategoria();

        return back()->with('success', 'Extensión actualizada.');
    }

    private function exigirPerfumeriaEnUso(): void
    {
        $resolver = app(ResolverExtensionesProductoService::class);
        if (! $resolver->algunaCategoriaUsa('perfumeria')) {
            abort(404);
        }
    }
}
