import React, { useEffect, useState } from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';
import SelectorColumnasCsv from './SelectorColumnasCsv';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const estadoLabel = {
    generado: 'Generado',
    descargado: 'Descargado',
    importacion_declarada: 'Importación declarada',
};

export default function PanelExportacionCsv({
    lote,
    puedeExportar,
    puedeConfigurar,
    busy,
    setBusy,
}) {
    const [perfil, setPerfil] = useState(null);
    const [artefactos, setArtefactos] = useState([]);
    const [preset, setPreset] = useState('solo_precios');
    const [columnas, setColumnas] = useState([]);
    const [mensaje, setMensaje] = useState(null);

    const jsonHeaders = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    useEffect(() => {
        if (!lote?.lote_id) return undefined;
        let cancel = false;
        (async () => {
            const [p, a] = await Promise.all([
                fetch(route('tiendanube.precios.csv_perfil.show'), { headers: { Accept: 'application/json' } }),
                fetch(route('tiendanube.precios.lotes.exportaciones_csv.index', lote.lote_id), { headers: { Accept: 'application/json' } }),
            ]);
            if (cancel) return;
            if (p.ok) {
                const data = await p.json();
                setPerfil(data);
                const def = data.preset_default || 'solo_precios';
                setPreset(def);
                setColumnas(data.presets?.[def] || data.presets?.solo_precios || []);
            }
            if (a.ok) {
                const data = await a.json();
                setArtefactos(data.data || []);
            }
        })().catch(() => {
            if (!cancel) setMensaje('No se pudo cargar la exportación.');
        });
        return () => { cancel = true; };
    }, [lote?.lote_id, lote?.revision?.checksum]);

    const generar = async () => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.exportaciones_csv', lote.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({ preset, columnas }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo generar el CSV.');
            setArtefactos((prev) => [data, ...prev.filter((x) => x.id !== data.id)]);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const declarar = async (id) => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.exportaciones_csv.declarar', id), {
                method: 'POST',
                headers: jsonHeaders,
                body: '{}',
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo registrar la importación.');
            setArtefactos((prev) => prev.map((x) => (x.id === data.id ? data : x)));
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const resumen = lote?.revision?.resumen || {};
    const validado = !!perfil?.validado;
    const ultimo = artefactos[0];

    return (
        <div className="rounded-xl border theme-border p-3 space-y-3">
            <div>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Exportar para TiendaNube</h3>
                <p className="text-xs theme-text-muted m-0 mt-1">
                    Tienda {lote?.store_id} · Revisión {lote?.revision?.numero}
                    {lote?.revision?.aprobado_at ? ` · ${lote.revision.aprobado_at}` : ''}
                    {' · '}{resumen.productos || 0} productos / {resumen.con_cambio || 0} variantes con cambio
                </p>
                <p className="text-xs theme-text-muted m-0">
                    Datos del {lote?.fecha_lectura || lote?.revision?.aprobado_at || 'snapshot aprobado'}. No se consulta la API al generar.
                </p>
            </div>

            {!validado && (
                <p className="text-sm theme-text-main m-0">
                    Configura una exportación de ejemplo de tu tienda
                    {puedeConfigurar ? (
                        <>
                            {' · '}
                            <a className="underline" href={route('tiendanube.precios.fuentes')}>Ir a configuración</a>
                        </>
                    ) : null}
                </p>
            )}

            {validado && (
                <SelectorColumnasCsv
                    columnasMeta={perfil?.columnas || []}
                    presetsMap={perfil?.presets || {}}
                    preset={preset}
                    columnas={columnas}
                    onPreset={setPreset}
                    onColumnas={setColumnas}
                    disabled={busy || !puedeExportar}
                />
            )}

            <p className="text-xs theme-text-muted m-0">
                Columnas: {(columnas.length ? columnas : perfil?.presets?.solo_precios || []).join(', ')}
            </p>

            {mensaje && <p className="text-sm theme-text-main m-0">{mensaje}</p>}

            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    disabled={busy || !puedeExportar || !validado}
                    onClick={generar}
                    className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    Exportar para TiendaNube
                </button>
                <a
                    href={route('tiendanube.precios.lotes.reporte_revision', lote.lote_id)}
                    className={GELIA_BTN_OUTLINE}
                >
                    Descargar reporte de revisión
                </a>
            </div>

            {ultimo && (
                <div className="text-xs theme-text-main space-y-1">
                    <p className="m-0">
                        Archivo {estadoLabel[ultimo.estado] || ultimo.estado} · {ultimo.filas_exportadas} filas · {ultimo.nombre_archivo}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route('tiendanube.precios.exportaciones_csv.descargar', ultimo.id)}
                            className={GELIA_BTN_OUTLINE}
                        >
                            Descargar
                        </a>
                        {ultimo.estado !== 'importacion_declarada' && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => declarar(ultimo.id)}
                                className={`${GELIA_BTN_OUTLINE} disabled:opacity-50`}
                            >
                                Registrar importación declarada
                            </button>
                        )}
                    </div>
                    <p className="theme-text-muted m-0">
                        En el panel de TiendaNube: Productos → Importar. El reporte interno no es importable. Descargar no marca el lote como aplicado.
                    </p>
                </div>
            )}
        </div>
    );
}
