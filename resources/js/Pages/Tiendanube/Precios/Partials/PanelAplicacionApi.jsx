import React, { useCallback, useEffect, useState } from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';
import ModalConfirmarAplicacion from './ModalConfirmarAplicacion';
import ProgresoAplicacion from './ProgresoAplicacion';
import TablaProblemasAplicacion from './TablaProblemasAplicacion';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function PanelAplicacionApi({
    lote,
    puedeAplicar,
    busy,
    setBusy,
    onVolverCatalogo,
}) {
    const [ejecucion, setEjecucion] = useState(null);
    const [problemas, setProblemas] = useState({ data: [], meta: {} });
    const [page, setPage] = useState(1);
    const [confirmar, setConfirmar] = useState(false);
    const [vigencia, setVigencia] = useState(null);
    const [mensaje, setMensaje] = useState(null);

    const jsonHeaders = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    const cargarEjecucion = useCallback(async () => {
        if (!lote?.lote_id) return null;
        const res = await fetch(route('tiendanube.precios.lotes.ejecucion', lote.lote_id), {
            headers: { Accept: 'application/json' },
        });
        if (res.status === 404) {
            setEjecucion(null);
            return null;
        }
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'No se pudo leer la ejecución.');
        setEjecucion(data);
        return data;
    }, [lote?.lote_id]);

    const cargarProblemas = useCallback(async (ejecucionId, nextPage = page) => {
        if (!ejecucionId) return;
        const params = new URLSearchParams({ filtro: 'problemas', page: String(nextPage), per_page: '25' });
        const res = await fetch(`${route('tiendanube.precios.ejecuciones.items', ejecucionId)}?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'No se pudieron cargar los problemas.');
        setProblemas(data);
    }, [page]);

    useEffect(() => {
        cargarEjecucion().catch((e) => setMensaje(e.message));
    }, [cargarEjecucion, lote?.revision?.checksum]);

    const abierta = ejecucion && ['pendiente', 'procesando', 'suspendida'].includes(ejecucion.estado);

    useEffect(() => {
        if (!abierta || !lote?.lote_id) return undefined;
        const t = setInterval(() => {
            cargarEjecucion()
                .then((data) => data?.id && cargarProblemas(data.id, page))
                .catch(() => {});
        }, 1500);
        return () => clearInterval(t);
    }, [abierta, lote?.lote_id, cargarEjecucion, cargarProblemas, page]);

    useEffect(() => {
        if (ejecucion?.id) {
            cargarProblemas(ejecucion.id, page).catch((e) => setMensaje(e.message));
        }
    }, [ejecucion?.id, ejecutarChecksum(ejecucion), page, cargarProblemas]);

    const abrirConfirmacion = async () => {
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.revision_aprobada', lote.lote_id), {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo comprobar la vigencia.');
            const misma = data.checksum === lote.revision?.checksum;
            setVigencia({
                ok: misma && data.store_id === lote.store_id,
                mensaje: misma ? null : 'La revisión aprobada ya no coincide con la pantalla.',
            });
            setConfirmar(true);
        } catch (e) {
            setMensaje(e.message);
        }
    };

    const aplicar = async () => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.aplicar', lote.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({ checksum: lote.revision?.checksum }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo iniciar la aplicación.');
            setEjecucion(data);
            setConfirmar(false);
            await cargarProblemas(data.id, 1);
            setPage(1);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const detener = async () => {
        if (!ejecucion?.id) return;
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.ejecuciones.cancelar', ejecucion.id), {
                method: 'POST',
                headers: jsonHeaders,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudieron detener los pendientes.');
            setEjecucion(data);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const accionItem = async (fila, tipo) => {
        setBusy(true);
        try {
            const nombre = tipo === 'reintentar' ? 'tiendanube.precios.ejecuciones.items.reintentar' : 'tiendanube.precios.ejecuciones.items.verificar';
            const res = await fetch(route(nombre, [ejecucion.id, fila.id]), {
                method: 'POST',
                headers: jsonHeaders,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo completar la acción.');
            setEjecucion(data);
            await cargarProblemas(data.id, page);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const terminada = ejecucion && ['completada', 'parcial', 'cancelada', 'fallida'].includes(ejecucion.estado);
    const hayProblemas = (ejecucion?.conflictos || 0) + (ejecucion?.fallidas || 0) + (ejecucion?.por_verificar || 0) > 0;

    return (
        <div className="space-y-3">
            {mensaje && <p className="text-sm theme-text-main m-0">{mensaje}</p>}
            {!ejecucion && puedeAplicar && (
                <button type="button" disabled={busy} onClick={abrirConfirmacion} className={GELIA_BTN_OUTLINE}>
                    Aplicar en TiendaNube
                </button>
            )}
            {!puedeAplicar && !ejecucion && (
                <p className="text-xs theme-text-muted m-0">Se requiere permiso de aplicación masiva para publicar por API.</p>
            )}
            <ProgresoAplicacion
                ejecucion={ejecucion}
                onDetener={detener}
                puedeAplicar={puedeAplicar}
                busy={busy}
            />
            {ejecucion && (
                <TablaProblemasAplicacion
                    items={problemas.data}
                    meta={problemas.meta}
                    onPagina={setPage}
                    onReintentar={(fila) => accionItem(fila, 'reintentar')}
                    onVerificar={(fila) => accionItem(fila, 'verificar')}
                    puedeAplicar={puedeAplicar}
                    busy={busy}
                />
            )}
            {terminada && (
                <div className="flex flex-wrap gap-2">
                    <button type="button" className={GELIA_BTN_OUTLINE} onClick={onVolverCatalogo}>
                        Volver al catálogo
                    </button>
                    {hayProblemas && (
                        <p className="text-xs theme-text-muted m-0 self-center">
                            Puede exportar CSV de esta revisión; no se genera un archivo automático.
                        </p>
                    )}
                </div>
            )}
            {confirmar && (
                <ModalConfirmarAplicacion
                    lote={lote}
                    ejecucionPrevia={ejecucion}
                    vigencia={vigencia}
                    busy={busy}
                    onCancelar={() => setConfirmar(false)}
                    onConfirmar={aplicar}
                />
            )}
        </div>
    );
}

function ejecutarChecksum(ejecucion) {
    return `${ejecucion?.confirmadas || 0}-${ejecucion?.conflictos || 0}-${ejecucion?.por_verificar || 0}-${ejecucion?.estado || ''}`;
}
