<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    /**
     * Muestra el formulario de edición de perfil.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit');
    }

    /**
     * Actualiza la información del perfil del usuario.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $datos = $request->validate([
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Manejo de la foto de perfil
        if ($request->hasFile('foto_perfil')) {
            // Borrar la foto anterior si existe
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $datos['foto_perfil'] = $request->file('foto_perfil')->store('perfiles', 'public');
        } else {
            // Evitar sobreescribir con null si no enviaron una nueva foto
            unset($datos['foto_perfil']);
        }

        $user->update($datos);

        return back()->with('success', 'Perfil actualizado exitosamente.');
    }
}