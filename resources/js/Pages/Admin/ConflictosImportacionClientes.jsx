import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function ConflictosImportacionClientes({ auth, conflictos }) {
    const { flash } = usePage().props;
    const decidir = (id, decision) => {
        router.post(route('admin.clientes.conflictos.resolver', id), { decision }, { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Conflictos de importación" />
            <div className="max-w-5xl mx-auto p-4 sm:p-8 space-y-4">
                <h1 className="text-2xl font-black theme-text-main m-0">Conflictos de importación</h1>
                {flash?.success && <p className="text-sm theme-text-main m-0">{flash.success}</p>}
                {(conflictos.data || []).map((fila) => (
                    <article key={fila.id} className={`${geliaCardClass()} p-4 space-y-2`}>
                        <p className="text-sm font-bold theme-text-main m-0">
                            Cliente {fila.numero_cliente} · {fila.cliente?.nombre || 'Sin nombre'} · {fila.alcance}
                        </p>
                        <p className="text-xs theme-text-muted m-0">{fila.motivo}</p>
                        <p className="text-xs theme-text-main m-0 break-words">Vigente: {JSON.stringify(fila.valor_vigente)}</p>
                        <p className="text-xs theme-text-main m-0 break-words">Propuesto: {JSON.stringify(fila.valor_propuesto)} · {fila.origen_propuesto}</p>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" className="px-3 py-2 rounded-xl theme-element border theme-border text-xs font-bold" onClick={() => decidir(fila.id, 'conservar')}>Conservar vigente</button>
                            <button type="button" className="px-3 py-2 rounded-xl text-white text-xs font-bold" style={{ backgroundColor: 'var(--color-primario)' }} onClick={() => decidir(fila.id, 'aplicar')}>Aplicar propuesto</button>
                        </div>
                    </article>
                ))}
                {conflictos.total === 0 && <p className="text-sm theme-text-muted">No hay conflictos pendientes.</p>}
                <GeliaPaginacion paginator={conflictos} onIrAPagina={(pagina) => router.get(route('admin.clientes.conflictos'), { page: pagina }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
