import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { UploadCloud, History, Download } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import SelectorSucursalAlmacen from '@/Components/Almacenes/SelectorSucursalAlmacen';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

export default function Index({
    auth,
    lotes,
    casos = [],
    caso_inicial,
    sucursales,
    almacenes,
    sucursal_activa_id,
    sin_sucursales_operables,
}) {
    const inicial = casos.find((caso) => caso.id === caso_inicial) || casos[0] || null;
    const [casoId, setCasoId] = useState(inicial?.id || '');
    const [sucursalId, setSucursalId] = useState(sucursal_activa_id ? String(sucursal_activa_id) : '');
    const [almacenId, setAlmacenId] = useState('');
    const [archivo, setArchivo] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const caso = casos.find((item) => item.id === casoId) || null;

    const enviar = async () => {
        if (!caso || !archivo) {
            setError('Elige un caso y un archivo.');
            return;
        }
        if (!sucursalId || !almacenId) {
            setError('Selecciona sucursal y almacén.');
            return;
        }
        setError('');
        setLoading(true);
        const form = new FormData();
        form.append('archivo', archivo);
        caso.operaciones.forEach((op) => form.append('operaciones[]', op));
        form.append('almacen_id', almacenId);
        form.append('sucursal_id', sucursalId);
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
            <GeliaPageShell className="max-w-5xl mx-auto space-y-6">
                <header className={geliaCardClass('p-6')}>
                    <h1 className="text-2xl font-semibold theme-text-main">Importaciones</h1>
                    <p className="text-sm theme-text-muted mt-2 max-w-2xl">
                        Cada caso indica qué identificador usa el archivo, qué datos puede modificar y qué datos conserva. Simular no aplica cambios al catálogo. El almacén elegido aplica a todo el archivo.
                    </p>
                </header>

                {sin_sucursales_operables ? (
                    <div className={geliaCardClass('p-6')}>
                        <p className="text-sm theme-text-muted">Sin sucursales operables. No se puede importar a un almacén hasta tener una sucursal asignada.</p>
                    </div>
                ) : casos.length === 0 ? (
                    <div className={geliaCardClass('p-6')}>
                        <p className="text-sm theme-text-muted">No hay casos de importación disponibles con los permisos actuales.</p>
                    </div>
                ) : (
                    <div className={geliaCardClass('p-6 space-y-5')}>
                        <h2 className="text-base font-semibold theme-text-main">Qué va a escribir el archivo</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Caso de importación">
                            {casos.map((item) => {
                                const seleccionado = item.id === casoId;
                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={seleccionado}
                                        onClick={() => setCasoId(item.id)}
                                        className={`text-left rounded-2xl border p-4 theme-element ${seleccionado ? 'ring-2 ring-[var(--color-primario)]' : 'theme-border'}`}
                                    >
                                        <span className="block text-base font-semibold theme-text-main">{item.titulo}</span>
                                        <span className="block mt-1 text-sm theme-text-muted">{item.frase}</span>
                                    </button>
                                );
                            })}
                        </div>

                        {caso && (
                            <p className="text-sm theme-text-muted">Columnas de la plantilla: {caso.columnas}.</p>
                        )}

                        <SelectorSucursalAlmacen
                            sucursales={sucursales}
                            almacenes={almacenes}
                            sucursalId={sucursalId}
                            almacenId={almacenId}
                            onSucursalChange={onSucursalChange}
                            onAlmacenChange={setAlmacenId}
                            requiereAlmacen
                            requiereSucursal
                            className="theme-input w-full px-3 py-2 text-sm"
                        />

                        {caso?.plantilla && (
                            <a href={caso.plantilla} className="inline-flex items-center gap-2 text-sm font-semibold theme-text-primario hover:underline">
                                <Download className="w-4 h-4" /> Descargar plantilla de {caso.titulo}
                            </a>
                        )}

                        <label className="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed theme-border rounded-xl cursor-pointer theme-element">
                            <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                            <span className="text-sm theme-text-main">{archivo ? archivo.name : 'Excel o CSV'}</span>
                            <input type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={(e) => setArchivo(e.target.files[0])} />
                        </label>
                        {error && <p className="text-sm font-semibold theme-text-peligro" role="alert">{error}</p>}
                        <button type="button" disabled={loading} onClick={enviar} className={`${THEME_BTN_PRIMARY} w-full py-3`}>
                            {loading ? 'Subiendo…' : 'Siguiente: mapear columnas'}
                        </button>
                    </div>
                )}

                <div className={geliaCardClass('p-6')}>
                    <h2 className="text-base font-semibold theme-text-main flex items-center gap-2 mb-4">
                        <History className="w-4 h-4" /> Historial reciente
                    </h2>
                    {lotes.length === 0 ? (
                        <p className="text-sm theme-text-muted">Sin cargas previas.</p>
                    ) : (
                        <ul className="space-y-2">
                            {lotes.map((l) => (
                                <li key={l.id}>
                                    <Link href={route('almacenes.importaciones.show', l.id)} className="text-sm theme-text-main hover:underline">
                                        #{l.id} — {l.etiqueta_tipo} — {l.estado}
                                        {l.almacen ? ` — ${l.almacen.codigo} (${l.almacen.sucursal_nombre || 'sin sucursal'})` : ''}
                                        {' — '}{l.created_at}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
