import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { UploadCloud, FileSpreadsheet, History, Download } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import SelectorSucursalAlmacen from '@/Components/Almacenes/SelectorSucursalAlmacen';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import { operacionesDesdePreset } from '@/config/importacionHubPresets';

export default function Index({
    auth,
    lotes,
    operacionesDisponibles,
    sucursales,
    almacenes,
    sucursal_activa_id,
    preset,
    plantillas,
    sin_sucursales_operables,
}) {
    const presetOps = useMemo(() => operacionesDesdePreset(preset), [preset]);
    const [operaciones, setOperaciones] = useState(presetOps);
    const [sucursalId, setSucursalId] = useState(sucursal_activa_id ? String(sucursal_activa_id) : '');
    const [almacenId, setAlmacenId] = useState('');
    const [archivo, setArchivo] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (presetOps.length > 0) {
            setOperaciones(presetOps);
        }
    }, [preset]);

    const idsPermitidos = useMemo(() => new Set(operacionesDisponibles.map((o) => o.id)), [operacionesDisponibles]);

    const toggleOp = (id) => {
        if (!idsPermitidos.has(id)) return;
        setOperaciones((prev) => (prev.includes(id) ? prev.filter((o) => o !== id) : [...prev, id]));
    };

    const requiereAlmacen = operaciones.some((o) => ['asignacion_almacen', 'costos_precios', 'cantidades_referencia'].includes(o));

    const plantillaUrl = plantillas?.[preset] || plantillas?.inventario;

    const enviar = async () => {
        if (!archivo || operaciones.length === 0) {
            setError('Selecciona operaciones y un archivo.');
            return;
        }
        if (requiereAlmacen && (!sucursalId || !almacenId)) {
            setError('Selecciona sucursal y almacén.');
            return;
        }
        setError('');
        setLoading(true);
        const form = new FormData();
        form.append('archivo', archivo);
        operaciones.forEach((op) => form.append('operaciones[]', op));
        if (almacenId) form.append('almacen_id', almacenId);
        if (sucursalId) form.append('sucursal_id', sucursalId);
        try {
            const { data } = await axios.post(route('almacenes.importaciones.analizar'), form);
            router.visit(data.redirect);
        } catch (err) {
            setError(err.response?.data?.message || 'No se pudo analizar el archivo.');
        } finally {
            setLoading(false);
        }
    };

    const guardarSucursalActiva = async (id) => {
        if (!id) return;
        try {
            await axios.put(route('almacenes.contexto.sucursal_activa'), { sucursal_id: id });
        } catch {
            // no bloquear flujo
        }
    };

    const onSucursalChange = (id) => {
        setSucursalId(id);
        guardarSucursalActiva(id);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Importaciones" />
            <div className="max-w-4xl mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6')}>
                    <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                        <FileSpreadsheet className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                        Importaciones unificadas
                    </h1>
                    <p className="text-[11px] font-bold theme-text-muted mt-2">
                        Elige qué actualizará el archivo, la sucursal y el almacén cuando aplique. Luego mapea columnas, simula y aplica.
                    </p>
                </header>

                {sin_sucursales_operables ? (
                    <div className={geliaCardClass('p-6')}>
                        <p className="text-[11px] font-bold theme-text-muted">Sin sucursales operables. No puedes importar a almacenes hasta que te asignen una sucursal.</p>
                    </div>
                ) : (
                    <div className={geliaCardClass('p-6 space-y-4')}>
                        <p className="text-[10px] font-black uppercase theme-text-muted">Qué deseas actualizar</p>
                        <div className="grid grid-cols-1 gap-3">
                            {operacionesDisponibles.map((op) => (
                                <label key={op.id} className="flex flex-col gap-1 text-[11px] font-bold theme-text-main cursor-pointer border theme-border rounded-xl p-3">
                                    <span className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={operaciones.includes(op.id)}
                                            onChange={() => toggleOp(op.id)}
                                        />
                                        {op.label}
                                    </span>
                                    {op.descripcion && (
                                        <span className="text-[10px] font-bold theme-text-muted pl-6">{op.descripcion}</span>
                                    )}
                                </label>
                            ))}
                        </div>

                        {requiereAlmacen && (
                            <SelectorSucursalAlmacen
                                sucursales={sucursales}
                                almacenes={almacenes}
                                sucursalId={sucursalId}
                                almacenId={almacenId}
                                onSucursalChange={onSucursalChange}
                                onAlmacenChange={setAlmacenId}
                                requiereAlmacen
                                requiereSucursal
                            />
                        )}

                        {plantillaUrl && (
                            <a href={plantillaUrl} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:underline">
                                <Download className="w-4 h-4" /> Descargar plantilla sugerida
                            </a>
                        )}

                        <label className="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed theme-border rounded-xl cursor-pointer theme-element">
                            <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                            <span className="text-[11px] font-bold theme-text-main">{archivo ? archivo.name : 'Excel o CSV'}</span>
                            <input type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={(e) => setArchivo(e.target.files[0])} />
                        </label>
                        {error && <p className="text-[11px] font-bold text-red-500">{error}</p>}
                        <button type="button" disabled={loading} onClick={enviar} className={`${THEME_BTN_PRIMARY} w-full py-3`}>
                            {loading ? 'Subiendo…' : 'Siguiente: mapear columnas'}
                        </button>
                    </div>
                )}

                <div className={geliaCardClass('p-6')}>
                    <h2 className="text-sm font-black uppercase theme-text-main flex items-center gap-2 mb-4">
                        <History className="w-4 h-4" /> Historial reciente
                    </h2>
                    {lotes.length === 0 ? (
                        <p className="text-[11px] font-bold theme-text-muted">Sin cargas previas.</p>
                    ) : (
                        <ul className="space-y-2">
                            {lotes.map((l) => (
                                <li key={l.id}>
                                    <Link href={route('almacenes.importaciones.show', l.id)} className="text-[11px] font-bold theme-text-main hover:underline">
                                        #{l.id} — {l.etiqueta_tipo} — {l.estado}
                                        {l.almacen ? ` — ${l.almacen.codigo} (${l.almacen.sucursal_nombre || 'sin sucursal'})` : ''}
                                        {' — '}{l.created_at}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
