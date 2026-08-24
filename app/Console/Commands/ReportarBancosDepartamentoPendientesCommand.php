<?php

namespace App\Console\Commands;

use App\Models\CatalogoBanco;
use App\Models\CatalogoBancoDepartamento;
use App\Models\Departamento;
use Illuminate\Console\Command;

/**
 * No adivina IDs: solo reporta bancos/departamentos sin asignación administrable.
 */
class ReportarBancosDepartamentoPendientesCommand extends Command
{
    protected $signature = 'control-pedidos:bancos-departamento-pendientes';

    protected $description = 'Lista bancos activos y departamentos sin relación en catalogo_banco_departamento';

    public function handle(): int
    {
        $asignados = CatalogoBancoDepartamento::query()->count();
        $bancos = CatalogoBanco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $departamentos = Departamento::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);

        $this->info("Relaciones banco↔departamento existentes: {$asignados}");
        $this->newLine();
        $this->info('Bancos activos (asigne por ID, no por nombre en código):');
        foreach ($bancos as $b) {
            $n = CatalogoBancoDepartamento::query()->where('catalogo_banco_id', $b->id)->count();
            $this->line("  [{$b->id}] {$b->nombre} — depts: {$n}");
        }

        $this->newLine();
        $this->info('Departamentos activos:');
        foreach ($departamentos as $d) {
            $n = CatalogoBancoDepartamento::query()->where('departamento_id', $d->id)->count();
            $codigo = $d->codigo ?: '—';
            $this->line("  [{$d->id}] {$d->nombre} ({$codigo}) — bancos: {$n}");
        }

        if ($asignados === 0) {
            $this->warn('Sin relaciones: el catálogo de bancos sigue mostrando todos los bancos activos (comportamiento anterior).');
        }

        return self::SUCCESS;
    }
}
