<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonalizacionFondo;
use App\Models\PersonalizacionTema;
use App\Models\PersonalizacionTono;
use App\Services\PersonalizacionCatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PersonalizacionController extends Controller
{
    public function index(): Response
    {
        $seccion = request('seccion', 'tonos');
        if (! in_array($seccion, ['tonos', 'fondos', 'temas'], true)) {
            $seccion = 'tonos';
        }

        $page = max(1, (int) request('page', 1));

        $catalogo = match ($seccion) {
            'fondos' => PersonalizacionCatalogoService::fondosAdminPaginados($page),
            'temas'  => PersonalizacionCatalogoService::temasAdminPaginados($page),
            default  => PersonalizacionCatalogoService::tonosAdminPaginados($page),
        };

        return Inertia::render('Admin/GestorDePersonalizacion', [
            'seccion'        => $seccion,
            'catalogo'       => $catalogo,
            'conteos'        => PersonalizacionCatalogoService::conteosAdmin(),
            'fondos_opciones' => PersonalizacionCatalogoService::fondosOpcionesSelect(),
        ]);
    }

    // ─── TONOS ───────────────────────────────────────────────

    public function storeTono(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'  => 'required|string|max:120',
            'slug'    => 'nullable|string|max:80|alpha_dash|unique:personalizacion_tonos,slug',
            'archivo' => 'required|file|mimes:mp3,wav,ogg|max:5120',
            'activo'  => 'nullable|boolean',
            'orden'   => 'nullable|integer|min:0|max:9999',
        ]);

        $slug = $datos['slug'] ?? Str::slug($datos['nombre']);
        $slug = $this->slugUnico(PersonalizacionTono::class, $slug);

        $extension = $request->file('archivo')->getClientOriginalExtension();
        $archivo = $slug . '.' . strtolower($extension);

        $request->file('archivo')->move(public_path('assets/sounds'), $archivo);

        PersonalizacionTono::create([
            'slug'    => $slug,
            'nombre'  => $datos['nombre'],
            'archivo' => $archivo,
            'activo'  => $request->boolean('activo', true),
            'orden'   => $datos['orden'] ?? 0,
        ]);

        return back()->with('success', 'Tono de notificación registrado correctamente.');
    }

    public function updateTono(Request $request, int $id): RedirectResponse
    {
        $tono = PersonalizacionTono::findOrFail($id);

        $datos = $request->validate([
            'nombre'  => 'required|string|max:120',
            'slug'    => 'nullable|string|max:80|alpha_dash|unique:personalizacion_tonos,slug,' . $id,
            'archivo' => 'nullable|file|mimes:mp3,wav,ogg|max:5120',
            'activo'  => 'nullable|boolean',
            'orden'   => 'nullable|integer|min:0|max:9999',
        ]);

        $slugAnterior = $tono->slug;
        $archivoAnterior = $tono->archivo;

        if (!empty($datos['slug']) && $datos['slug'] !== $tono->slug) {
            $tono->slug = $datos['slug'];
        }

        $tono->nombre = $datos['nombre'];
        $tono->activo = $request->boolean('activo', $tono->activo);
        $tono->orden  = $datos['orden'] ?? $tono->orden;

        if ($request->hasFile('archivo')) {
            $extension = $request->file('archivo')->getClientOriginalExtension();
            $archivo = $tono->slug . '.' . strtolower($extension);
            $request->file('archivo')->move(public_path('assets/sounds'), $archivo);
            $this->eliminarArchivoTono($archivoAnterior);
            $tono->archivo = $archivo;
        } elseif ($slugAnterior !== $tono->slug) {
            $nuevoNombre = $tono->slug . '.' . pathinfo($tono->archivo, PATHINFO_EXTENSION);
            $rutaVieja = public_path('assets/sounds/' . $tono->archivo);
            $rutaNueva = public_path('assets/sounds/' . $nuevoNombre);
            if (is_file($rutaVieja)) {
                rename($rutaVieja, $rutaNueva);
                $tono->archivo = $nuevoNombre;
            }
        }

        $tono->save();

        return back()->with('success', 'Tono actualizado correctamente.');
    }

    public function destroyTono(int $id): RedirectResponse
    {
        $tono = PersonalizacionTono::findOrFail($id);

        if (PersonalizacionTono::where('activo', true)->count() <= 1 && $tono->activo) {
            return back()->with('error', 'Debe existir al menos un tono activo en el catálogo.');
        }

        $this->eliminarArchivoTono($tono->archivo);
        $tono->delete();

        return back()->with('success', 'Tono eliminado correctamente.');
    }

    // ─── FONDOS ────────────────────────────────────────────────

    public function storeFondo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:120',
            'slug'   => 'nullable|string|max:80|alpha_dash|unique:personalizacion_fondos,slug',
            'tipo'   => 'required|in:vector,imagen',
            'valor'  => 'nullable|string|max:500',
            'archivo'=> 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'activo' => 'nullable|boolean',
            'orden'  => 'nullable|integer|min:0|max:9999',
        ]);

        $slug = $datos['slug'] ?? Str::slug($datos['nombre']);
        $slug = $this->slugUnico(PersonalizacionFondo::class, $slug);

        if ($datos['tipo'] === 'vector') {
            $valor = $datos['valor'] ?? $slug;
        } else {
            if (!$request->hasFile('archivo')) {
                return back()->with('error', 'Debe subir una imagen para fondos de tipo imagen.');
            }
            $ruta = $request->file('archivo')->store('fondos_sistema', 'public');
            $valor = '/storage/' . $ruta;
        }

        PersonalizacionFondo::create([
            'slug'   => $slug,
            'nombre' => $datos['nombre'],
            'tipo'   => $datos['tipo'],
            'valor'  => $valor,
            'activo' => $request->boolean('activo', true),
            'orden'  => $datos['orden'] ?? 0,
        ]);

        return back()->with('success', 'Fondo registrado correctamente.');
    }

    public function updateFondo(Request $request, int $id): RedirectResponse
    {
        $fondo = PersonalizacionFondo::findOrFail($id);

        $datos = $request->validate([
            'nombre'  => 'required|string|max:120',
            'slug'    => 'nullable|string|max:80|alpha_dash|unique:personalizacion_fondos,slug,' . $id,
            'tipo'    => 'required|in:vector,imagen',
            'valor'   => 'nullable|string|max:500',
            'archivo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'activo'  => 'nullable|boolean',
            'orden'   => 'nullable|integer|min:0|max:9999',
        ]);

        if (!empty($datos['slug'])) {
            $fondo->slug = $datos['slug'];
        }

        $fondo->nombre = $datos['nombre'];
        $fondo->tipo   = $datos['tipo'];
        $fondo->activo = $request->boolean('activo', $fondo->activo);
        $fondo->orden  = $datos['orden'] ?? $fondo->orden;

        if ($datos['tipo'] === 'vector') {
            $fondo->valor = $datos['valor'] ?? $fondo->valor;
        } elseif ($request->hasFile('archivo')) {
            if ($fondo->tipo === 'imagen' && str_starts_with($fondo->valor, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $fondo->valor));
            }
            $ruta = $request->file('archivo')->store('fondos_sistema', 'public');
            $fondo->valor = '/storage/' . $ruta;
        }

        $fondo->save();

        return back()->with('success', 'Fondo actualizado correctamente.');
    }

    public function destroyFondo(int $id): RedirectResponse
    {
        $fondo = PersonalizacionFondo::findOrFail($id);

        if ($fondo->tipo === 'imagen' && str_starts_with($fondo->valor, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $fondo->valor));
        }

        $fondo->delete();

        return back()->with('success', 'Fondo eliminado correctamente.');
    }

    // ─── TEMAS ─────────────────────────────────────────────────

    public function storeTema(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'              => 'required|string|max:120',
            'slug'                => 'nullable|string|max:80|alpha_dash|unique:personalizacion_temas,slug',
            'modo'                => 'required|in:dark,light',
            'color_nombre'        => 'required|string|max:50',
            'color_hex'           => 'nullable|string|max:20',
            'fondo_base'          => 'required|string|max:500',
            'fuente_principal'    => 'required|string|max:50',
            'escala_fuente'       => 'nullable|numeric|min:0.875|max:1.5',
            'layout_sidebar'      => 'required|string|max:50',
            'efecto_cristal'      => 'nullable|boolean',
            'sonido'              => 'nullable|boolean',
            'activo'              => 'nullable|boolean',
            'orden'               => 'nullable|integer|min:0|max:9999',
        ]);

        $slug = $datos['slug'] ?? Str::slug($datos['nombre']);
        $slug = $this->slugUnico(PersonalizacionTema::class, $slug);

        PersonalizacionTema::create([
            'slug'          => $slug,
            'nombre'        => $datos['nombre'],
            'configuracion' => $this->configuracionTemaDesdeRequest($datos, $request),
            'activo'        => $request->boolean('activo', true),
            'orden'         => $datos['orden'] ?? 0,
        ]);

        return back()->with('success', 'Tema predefinido registrado correctamente.');
    }

    public function updateTema(Request $request, int $id): RedirectResponse
    {
        $tema = PersonalizacionTema::findOrFail($id);

        $datos = $request->validate([
            'nombre'              => 'required|string|max:120',
            'slug'                => 'nullable|string|max:80|alpha_dash|unique:personalizacion_temas,slug,' . $id,
            'modo'                => 'required|in:dark,light',
            'color_nombre'        => 'required|string|max:50',
            'color_hex'           => 'nullable|string|max:20',
            'fondo_base'          => 'required|string|max:500',
            'fuente_principal'    => 'required|string|max:50',
            'escala_fuente'       => 'nullable|numeric|min:0.875|max:1.5',
            'layout_sidebar'      => 'required|string|max:50',
            'efecto_cristal'      => 'nullable|boolean',
            'sonido'              => 'nullable|boolean',
            'activo'              => 'nullable|boolean',
            'orden'               => 'nullable|integer|min:0|max:9999',
        ]);

        if (!empty($datos['slug'])) {
            $tema->slug = $datos['slug'];
        }

        $tema->nombre        = $datos['nombre'];
        $tema->configuracion = $this->configuracionTemaDesdeRequest($datos, $request);
        $tema->activo        = $request->boolean('activo', $tema->activo);
        $tema->orden         = $datos['orden'] ?? $tema->orden;
        $tema->save();

        return back()->with('success', 'Tema predefinido actualizado correctamente.');
    }

    public function destroyTema(int $id): RedirectResponse
    {
        PersonalizacionTema::findOrFail($id)->delete();

        return back()->with('success', 'Tema predefinido eliminado correctamente.');
    }

    // ─── HELPERS ───────────────────────────────────────────────

    private function configuracionTemaDesdeRequest(array $datos, Request $request): array
    {
        return [
            'modo'               => $datos['modo'],
            'color_nombre'       => $datos['color_nombre'],
            'color_hex'          => $datos['color_hex'] ?? null,
            'fondo_base'         => $datos['fondo_base'],
            'fuente_principal'   => $datos['fuente_principal'],
            'escala_fuente'      => (float) ($datos['escala_fuente'] ?? 1),
            'layout_sidebar'     => $datos['layout_sidebar'],
            'efecto_cristal'     => $request->boolean('efecto_cristal', true),
            'sonido'             => $request->boolean('sonido', true),
        ];
    }

    private function slugUnico(string $modelClass, string $base): string
    {
        $slug = Str::slug($base);
        $original = $slug;
        $i = 1;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function eliminarArchivoTono(string $archivo): void
    {
        $ruta = public_path('assets/sounds/' . ltrim($archivo, '/'));
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
