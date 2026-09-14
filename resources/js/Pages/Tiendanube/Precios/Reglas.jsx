import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Archive, Copy, Pencil } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import EditorCalculoPanel from './Partials/EditorCalculoPanel';
import PreciosNav from './Partials/PreciosNav';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function Reglas({ auth, configuracion, reglas: reglasIniciales = [], metadatos, permisos }) {
    const [reglas, setReglas] = useState(reglasIniciales);
    const [incluirArchivadas, setIncluirArchivadas] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [busy, setBusy] = useState(false);
    const [editorAbierto, setEditorAbierto] = useState(false);
    const [reglaEditando, setReglaEditando] = useState(null);

    const puedeAdministrar = !!permisos?.reglas_administrar;
    const jsonHeaders = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() };

    const recargar = async () => {
        const params = incluirArchivadas ? '?incluir_archivadas=1' : '';
        const res = await fetch(`${route('tiendanube.precios.reglas.listar')}${params}`, { headers: jsonHeaders });
        const data = await res.json();
        if (res.ok) {
            setReglas(data.reglas || []);
        }
    };

    const duplicar = async (id) => {
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.reglas.duplicar', id), { method: 'POST', headers: jsonHeaders });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo duplicar.');
            setMensaje('Regla duplicada.');
            await recargar();
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const archivar = async (id) => {
        if (!window.confirm('¿Archivar esta regla? No aparecerá en nuevas selecciones.')) return;
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.reglas.archivar', id), { method: 'POST', headers: jsonHeaders });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo archivar.');
            setMensaje('Regla archivada.');
            await recargar();
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const abrirNueva = () => {
        setReglaEditando(null);
        setEditorAbierto(true);
    };

    const abrirEditar = async (id) => {
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.reglas.show', id), { headers: jsonHeaders });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo cargar la regla.');
            setReglaEditando(data);
            setEditorAbierto(true);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <AppLayout user={auth.user}>
            <Head title="Reglas de precios · TiendaNube" />
            <div className="space-y-4">
                <header>
                    <h1 className="text-lg font-black uppercase tracking-widest theme-text-main m-0">Precios TiendaNube</h1>
                    <p className="text-xs theme-text-muted mt-1">
                        {configuracion?.store_name ? `Tienda: ${configuracion.store_name}` : 'Sin tienda configurada'}
                    </p>
                </header>

                <PreciosNav activa="reglas" />

                {mensaje && <div className={`${geliaCardClass()} p-3 text-sm theme-text-main`}>{mensaje}</div>}

                <section className={`${geliaCardClass()} p-4 space-y-3`}>
                    <div className="flex flex-wrap items-center gap-3 justify-between">
                        <label className="flex items-center gap-2 text-sm theme-text-main">
                            <input
                                type="checkbox"
                                checked={incluirArchivadas}
                                onChange={async (e) => {
                                    setIncluirArchivadas(e.target.checked);
                                    setTimeout(recargar, 0);
                                }}
                            />
                            Incluir archivadas
                        </label>
                        {puedeAdministrar && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={abrirNueva}
                                className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Nueva regla
                            </button>
                        )}
                    </div>

                    {reglas.length === 0 ? (
                        <p className="text-sm theme-text-muted m-0">No hay reglas guardadas.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-[10px] uppercase tracking-widest theme-text-muted">
                                        <th className="py-2 pr-2">Nombre</th>
                                        <th className="py-2 pr-2">Estado</th>
                                        <th className="py-2 pr-2">Versión</th>
                                        <th className="py-2 pr-2">Utilizable</th>
                                        <th className="py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reglas.map((regla) => (
                                        <tr key={regla.id} className="border-t theme-border">
                                            <td className="py-2 pr-2">
                                                <div className="font-medium theme-text-main">{regla.nombre}</div>
                                                {regla.descripcion && (
                                                    <div className="text-xs theme-text-muted">{regla.descripcion}</div>
                                                )}
                                            </td>
                                            <td className="py-2 pr-2">
                                                {regla.archivada ? 'Archivada' : (regla.habilitada ? 'Habilitada' : 'Deshabilitada')}
                                            </td>
                                            <td className="py-2 pr-2">v{regla.version_actual?.numero || '—'}</td>
                                            <td className="py-2 pr-2">
                                                {regla.version_actual?.utilizable ? 'Sí' : 'No'}
                                                {!regla.version_actual?.utilizable && regla.version_actual?.motivo_no_utilizable && (
                                                    <span className="block text-xs theme-text-muted">
                                                        {regla.version_actual.motivo_no_utilizable}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2">
                                                <div className="flex flex-wrap gap-1">
                                                    {puedeAdministrar && !regla.archivada && (
                                                        <button
                                                            type="button"
                                                            title="Editar"
                                                            onClick={() => abrirEditar(regla.id)}
                                                            className="p-1.5 rounded border theme-border"
                                                        >
                                                            <Pencil size={14} />
                                                        </button>
                                                    )}
                                                    {puedeAdministrar && (
                                                        <button
                                                            type="button"
                                                            title="Duplicar"
                                                            onClick={() => duplicar(regla.id)}
                                                            className="p-1.5 rounded border theme-border"
                                                        >
                                                            <Copy size={14} />
                                                        </button>
                                                    )}
                                                    {puedeAdministrar && !regla.archivada && (
                                                        <button
                                                            type="button"
                                                            title="Archivar"
                                                            onClick={() => archivar(regla.id)}
                                                            className="p-1.5 rounded border theme-border"
                                                        >
                                                            <Archive size={14} />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <p className="text-xs theme-text-muted px-1">
                    Guardar o habilitar una regla no aplica precios. Use el catálogo para seleccionar variantes y previsualizar con una selección activa.
                </p>
            </div>

            <EditorCalculoPanel
                abierto={editorAbierto}
                onCerrar={() => { setEditorAbierto(false); setReglaEditando(null); recargar(); }}
                seleccion={null}
                metadatos={metadatos}
                permisos={permisos}
                reglaInicial={reglaEditando}
            />
        </AppLayout>
    );
}
