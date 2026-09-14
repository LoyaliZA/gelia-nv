import React, { useCallback, useEffect, useState } from 'react';
import { geliaCardClass, geliaToggleBtnClass } from '../../../../utils/geliaTheme';
import BarraAccionesRevision from './BarraAccionesRevision';
import ModalAprobarRevision from './ModalAprobarRevision';
import PanelAplicacionApi from './PanelAplicacionApi';
import PanelExportacionCsv from './PanelExportacionCsv';
import ProgresoSimulacion from './ProgresoSimulacion';
import TablaRevisionPrecios from './TablaRevisionPrecios';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const FILTROS = [
    { id: 'todas', label: 'Todas' },
    { id: 'con_cambio', label: 'Con cambio' },
    { id: 'con_errores', label: 'Con errores' },
    { id: 'sin_costo', label: 'Sin costo' },
];

export default function RevisionPreciosPanel({
    lote,
    permisos,
    onLoteChange,
    onVolver,
    onRecalcular,
}) {
    const [items, setItems] = useState({ data: [], meta: {} });
    const [filtro, setFiltro] = useState('todas');
    const [page, setPage] = useState(1);
    const [busy, setBusy] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [confirmar, setConfirmar] = useState(false);
    const jsonHeaders = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    const resumen = lote?.revision?.resumen || {};
    const destino = lote?.destino || 'normal';
    const simulando = lote?.simulacion && !['completada', 'error'].includes(lote.simulacion.estado);

    const cargarItems = useCallback(async (nextFiltro = filtro, nextPage = page) => {
        if (!lote?.lote_id) return;
        const params = new URLSearchParams({ filtro: nextFiltro, page: String(nextPage), per_page: '25' });
        const res = await fetch(`${route('tiendanube.precios.lotes.items', lote.lote_id)}?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'No se pudieron cargar las filas.');
        }
        setItems(data);
    }, [filtro, page, lote?.lote_id]);

    useEffect(() => {
        cargarItems(filtro, page).catch((e) => setMensaje(e.message));
    }, [cargarItems, filtro, page, lote?.revision?.checksum, lote?.estado]);

    useEffect(() => {
        if (!simulando || !lote?.lote_id) return undefined;
        const t = setInterval(async () => {
            const res = await fetch(route('tiendanube.precios.lotes.progreso', lote.lote_id), { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const progreso = await res.json();
            if (progreso.estado === 'completada' || progreso.estado === 'error') {
                const show = await fetch(route('tiendanube.precios.lotes.show', lote.lote_id), { headers: { Accept: 'application/json' } });
                if (show.ok) onLoteChange(await show.json());
            } else {
                onLoteChange({ ...lote, simulacion: progreso });
            }
        }, 1500);
        return () => clearInterval(t);
    }, [simulando, lote, onLoteChange]);

    const patchItem = async (item, body) => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.items.update', [lote.lote_id, item.id]), {
                method: 'PATCH',
                headers: jsonHeaders,
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo actualizar la fila.');
            onLoteChange(data.lote);
            await cargarItems();
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const aprobar = async () => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.aprobar', lote.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({ checksum: lote.revision?.checksum }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo aprobar.');
            onLoteChange(data);
            setConfirmar(false);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className={`${geliaCardClass()} p-3 md:p-4 space-y-3`}>
            <div>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Revisión de precios</h2>
                <p className="text-xs theme-text-muted m-0 mt-1">
                    {resumen.productos || 0} productos · {resumen.seleccionadas || 0} variantes
                    {lote?.fuente ? ` · fuente ${lote.fuente}` : ''}
                    {lote?.operacion ? ` · ${lote.operacion}` : ''}
                    {lote?.destino ? ` → ${lote.destino}` : ''}
                    {lote?.moneda ? ` · ${lote.moneda}` : ''}
                </p>
                <p className="text-xs theme-text-muted m-0">
                    Con cambio {resumen.con_cambio || 0} · Sin cambio {resumen.sin_cambio || 0} · Bloqueadas {resumen.bloqueadas || 0} · Excluidas {resumen.excluidas || 0}
                </p>
            </div>

            <ProgresoSimulacion simulacion={lote?.simulacion} />
            {mensaje && <p className="text-sm theme-text-main m-0">{mensaje}</p>}

            {lote?.estado === 'aprobado' && (
                <>
                    <PanelAplicacionApi
                        lote={lote}
                        puedeAplicar={!!permisos?.precios_aplicar}
                        busy={busy}
                        setBusy={setBusy}
                        onVolverCatalogo={onVolver}
                    />
                    <PanelExportacionCsv
                        lote={lote}
                        puedeExportar={!!permisos?.precios_exportar}
                        puedeConfigurar={!!permisos?.configurar}
                        busy={busy}
                        setBusy={setBusy}
                    />
                </>
            )}

            <div className="flex flex-wrap gap-2">
                {FILTROS.map((f) => (
                    <button
                        key={f.id}
                        type="button"
                        onClick={() => { setFiltro(f.id); setPage(1); }}
                        className={geliaToggleBtnClass(filtro === f.id)}
                        style={filtro === f.id ? { backgroundColor: 'var(--color-primario)' } : undefined}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            <TablaRevisionPrecios
                filas={items.data || []}
                meta={items.meta}
                puedeVerCosto={!!permisos?.precios_ver}
                destinoPrincipal={destino}
                busy={busy}
                onPagina={setPage}
                onExcluir={(fila, excluir) => patchItem(fila, {
                    accion: excluir ? 'excluir' : 'reincluir',
                    motivo: excluir ? 'Excluida de esta revisión' : null,
                })}
                onAjustar={(fila, datos) => patchItem(fila, { accion: 'ajustar', ...datos })}
            />

            <BarraAccionesRevision
                lote={lote}
                puedeAprobar={!!permisos?.precios_aprobar}
                busy={busy}
                onVolver={onVolver}
                onRecalcular={onRecalcular}
                onAprobar={() => setConfirmar(true)}
            />

            {confirmar && (
                <ModalAprobarRevision
                    lote={lote}
                    busy={busy}
                    onCancelar={() => setConfirmar(false)}
                    onConfirmar={aprobar}
                />
            )}
        </section>
    );
}
