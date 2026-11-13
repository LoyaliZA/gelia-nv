import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function RecuperacionAplicacionesCliente({ auth, solicitudes }) {
    const { flash, errors } = usePage().props;

    return (
        <AppLayout auth={auth}>
            <Head title="Recuperar autorizaciones" />
            <div className="max-w-5xl mx-auto p-4 sm:p-8 space-y-4">
                <h1 className="text-2xl font-black theme-text-main m-0">Autorizaciones sin aplicar</h1>
                <p className="text-sm theme-text-muted m-0">Cada fila se aplica por separado. Una segunda ejecución no vuelve a escribir lo que ya quedó.</p>
                {flash?.success && <p className="text-sm theme-text-main m-0">{flash.success}</p>}
                {errors?.solicitud && <p className="text-sm theme-text-peligro m-0">{errors.solicitud}</p>}
                {(solicitudes.data || []).map((solicitud) => (
                    <article key={solicitud.id} className={`${geliaCardClass()} p-4 space-y-2`}>
                        <p className="text-sm font-bold theme-text-main m-0">
                            Solicitud {solicitud.id} · Cliente {solicitud.cliente?.numero_cliente} {solicitud.cliente?.nombre}
                        </p>
                        <p className="text-xs theme-text-muted m-0">
                            Estado del cliente: {solicitud.cliente?.es_inactivo ? 'Inactivo' : 'Activo'} · {solicitud.cliente?.es_menudeo ? 'Menudeo' : 'Mayoreo'} · Aplicación: {solicitud.aplicacion_cliente || 'sin registrar'}
                        </p>
                        <button
                            type="button"
                            className="px-3 py-2 rounded-xl text-white text-xs font-bold"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                            onClick={() => router.post(route('admin.clientes.recuperacion.aplicar', solicitud.id), {}, { preserveScroll: true })}
                        >
                            Aplicar esta autorización
                        </button>
                    </article>
                ))}
                {solicitudes.total === 0 && <p className="text-sm theme-text-muted">No hay autorizaciones pendientes de aplicar.</p>}
                <GeliaPaginacion paginator={solicitudes} onIrAPagina={(pagina) => router.get(route('admin.clientes.recuperacion'), { page: pagina }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
