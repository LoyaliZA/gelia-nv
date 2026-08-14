<?php

namespace App\Http\Controllers\SaldosAFavor;

use App\Http\Controllers\Controller;
use App\Support\SaldosAFavor\ReglasSaf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ConfigurarSaldosAFavorController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('SaldosAFavor/Configurar', [
            'reglas' => ReglasSaf::todas(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'monto_minimo' => ['required', 'numeric', 'min:0'],
            'vigencia_modo' => ['required', 'in:dias,fecha_limite'],
            'vigencia_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'fecha_limite' => ['nullable', 'date', 'required_if:vigencia_modo,fecha_limite'],
        ]);

        try {
            ReglasSaf::guardar($datos);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['vigencia_modo' => $e->getMessage()]);
        }

        return redirect()
            ->route('saldos_favor.configurar.edit')
            ->with('success', 'Reglas de saldos a favor actualizadas.');
    }
}
