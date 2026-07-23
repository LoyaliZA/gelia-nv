import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Warehouse, Package, CheckCircle2, User, Calendar, Boxes,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { recargarModuloInertia } from '../../../utils/recargarModuloInertia';
import useSolicitudRealtime from '../../../hooks/useSolicitudRealtime';
import FeedbackResolucionTraspaso from '../Partials/FeedbackResolucionTraspaso';
import ModalDetalleDanoCedis from './Partials/ModalDetalleDanoCedis';
import ModalRevisarProductosCedis from './Partials/ModalRevisarProductosCedis';

const PROPS = ['traspasos', 'filtros'];

function formatearFecha(valor) {
    if (!valor) return '—';
    return new Date(valor).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function TarjetaCedis({ traspaso, onRevisar, onConfirmar, confirmandoId }) {
    const productos = traspaso.productos || [];
    const conDetalle = productos.filter((p) => p.detalle_dano || p.detalleDano).length;
    const ocupado = confirmandoId === traspaso.id;

    return (
        <article
            className={geliaCardClass(
                'p-6 border theme-border flex flex-col h-full min-w-0 hover:shadow-md transition-all duration-300 hover:border-[var(--color-primario)]/50',
            )}
        >
            <div className="flex justify-between items-start gap-3 mb-4">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center flex-wrap gap-2 mb-2">
                        <span className="text-xs font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                            {traspaso.folio}
                        </span>
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20">
                            Respondida
                        </span>
                        {conDetalle > 0 && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30">
                                {conDetalle} con detalle
                            </span>
                        )}
                    </div>
                    <h3 className="text-base font-bold theme-text-main leading-snug m-0 break-words">
                        {traspaso.cliente?.numero_cliente} — {traspaso.cliente?.nombre}
                    </h3>
                    <p className="text-xs theme-text-muted mt-2 font-bold m-0 inline-flex items-center gap-1.5">
                        <User className="w-3.5 h-3.5 shrink-0" />
                        <span className="truncate">{traspaso.vendedor?.name}</span>
                    </p>
                </div>
                <div className="p-2 rounded-xl shrink-0" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)', color: 'var(--color-primario)' }}>
                    <Package className="w-5 h-5" />
                </div>
            </div>

            <div className="grid grid-cols-3 gap-3 pt-4 border-t theme-border/60">
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Piezas</p>
                    <p className="text-base font-black m-0 tabular-nums text-sky-600 dark:text-sky-400">{traspaso.total_piezas}</p>
                </div>
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Origen</p>
                    <p className="text-sm font-bold theme-text-main m-0 truncate" title={traspaso.almacen_origen?.nombre}>
                        {traspaso.almacen_origen?.nombre || '—'}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Líneas</p>
                    <p className="text-base font-black theme-text-main m-0 tabular-nums">{productos.length}</p>
                </div>
            </div>

            {traspaso.fecha_entrega_estimada && (
                <p className="text-xs theme-text-muted font-bold mt-3 mb-0 inline-flex items-center gap-1.5">
                    <Calendar className="w-3.5 h-3.5 shrink-0" />
                    {formatearFecha(traspaso.fecha_entrega_estimada)}
                    {traspaso.horario?.nombre ? ` · ${traspaso.horario.nombre}` : ''}
                </p>
            )}

            <div className="mt-3">
                <FeedbackResolucionTraspaso traspaso={traspaso} />
            </div>

            <div className="pt-4 mt-auto space-y-2">
                <button
                    type="button"
                    onClick={() => onRevisar(traspaso)}
                    className="w-full py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 outline-none border theme-border theme-element theme-text-main"
                >
                    <Boxes className="w-4 h-4" />
                    Revisar productos ({productos.length})
                </button>
                <button
                    type="button"
                    disabled={ocupado}
                    onClick={() => onConfirmar(traspaso)}
                    className="w-full py-4 rounded-2xl text-white text-sm font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 outline-none disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <CheckCircle2 className="w-5 h-5" />
                    {ocupado ? 'Confirmando…' : 'Confirmar OK'}
                </button>
            </div>
        </article>
    );
}

export default function Index({ auth, traspasos }) {
    const lista = traspasos?.data || [];
    const [modalRevisar, setModalRevisar] = useState(null);
    const [modalDano, setModalDano] = useState(null);
    const [confirmandoId, setConfirmandoId] = useState(null);
    const [ocultos, setOcultos] = useState(() => new Set());

    useSolicitudRealtime('solicitudes.traspasos', '.solicitud-traspaso.actualizada', PROPS, auth);

    const visibles = lista.filter((t) => !ocultos.has(t.id));
    const recargar = () => recargarModuloInertia(PROPS);

    const confirmar = (traspaso) => {
        if (confirmandoId) return;
        if (!window.confirm(`¿Confirmar recepción OK de ${traspaso.folio}?`)) return;
        setConfirmandoId(traspaso.id);
        router.put(route('traspasos.cedis.confirmar', traspaso.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                setOcultos((prev) => new Set(prev).add(traspaso.id));
                recargar();
            },
            onFinish: () => setConfirmandoId(null),
        });
    };

    const reportarDesdeRevision = (producto) => {
        if (!modalRevisar) return;
        setModalDano({ traspaso: modalRevisar, producto });
        setModalRevisar(null);
    };

    return (
        <AppLayout>
            <Head title="Traspasos CEDIS | GELIA" />
            <GeliaPageShell className="space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-10`}>
                    <div className="flex items-center space-x-3 mb-2">
                        <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                            Revisión de piezas
                        </p>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0 flex items-center gap-3">
                        <Warehouse className="w-8 h-8 shrink-0 hidden sm:block" style={{ color: 'var(--color-primario)' }} />
                        Traspasos <span style={{ color: 'var(--color-primario)' }}>CEDIS</span>
                    </h1>
                    <p className="text-sm theme-text-muted font-bold mt-3 m-0 max-w-2xl">
                        Solo solicitudes respondidas. Confirma OK o revisa productos para reportar detalle/daño.
                    </p>
                </header>

                {visibles.length === 0 ? (
                    <div className="theme-surface rounded-3xl p-10 md:p-12 text-center border theme-border theme-text-muted font-bold text-sm uppercase tracking-wider">
                        No hay traspasos pendientes de revisión_
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {visibles.map((t) => (
                            <TarjetaCedis
                                key={t.id}
                                traspaso={t}
                                onRevisar={setModalRevisar}
                                onConfirmar={confirmar}
                                confirmandoId={confirmandoId}
                            />
                        ))}
                    </div>
                )}

                {lista.length > 0 && (
                    <GeliaPaginacion
                        paginator={traspasos}
                        onIrAPagina={(page) => router.get(route('traspasos.cedis.index'), { page }, {
                            preserveState: true,
                            preserveScroll: true,
                            only: PROPS,
                        })}
                    />
                )}
            </GeliaPageShell>

            {modalRevisar && (
                <ModalRevisarProductosCedis
                    traspaso={modalRevisar}
                    onClose={() => setModalRevisar(null)}
                    onReportarProducto={reportarDesdeRevision}
                />
            )}

            {modalDano && (
                <ModalDetalleDanoCedis
                    traspaso={modalDano.traspaso}
                    producto={modalDano.producto}
                    onClose={() => setModalDano(null)}
                    onExito={recargar}
                />
            )}
        </AppLayout>
    );
}
