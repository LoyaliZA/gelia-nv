import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Check, Save, UserPlus, Trash2 } from 'lucide-react';
import axios from 'axios';

const TIPOS_UI = [
    { id: 'resurtido', label: 'Resurtido' },
    { id: 'costos', label: 'Costos' },
    { id: 'actualizada', label: 'Actualizada' },
    { id: 'inventario', label: 'Inventario' },
    { id: 'venta_especial', label: 'Venta Especial' },
    { id: 'meli', label: 'Lista MELI' },
];

export default function ModalDestinatariosListados({
    show,
    onClose,
    usuariosSistema = [],
    destinatariosPorTipo = {},
    onSaved,
    onError,
}) {
    const [tipoActivo, setTipoActivo] = useState('resurtido');
    const [porTipo, setPorTipo] = useState({});
    const [busqueda, setBusqueda] = useState('');
    const [nuevoExterno, setNuevoExterno] = useState({ nombre: '', email: '' });
    const [saving, setSaving] = useState(false);
    const [errorLocal, setErrorLocal] = useState('');

    useEffect(() => {
        if (!show) return;
        const initial = {};
        TIPOS_UI.forEach(({ id }) => {
            const src = destinatariosPorTipo[id] || { user_ids: [], externos: [] };
            initial[id] = {
                user_ids: (src.user_ids || []).map((x) => Number(x)),
                externos: [...(src.externos || [])],
            };
        });
        setPorTipo(initial);
        setTipoActivo('resurtido');
        setBusqueda('');
        setNuevoExterno({ nombre: '', email: '' });
        setErrorLocal('');
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [show, destinatariosPorTipo]);

    if (!show) return null;

    const actual = porTipo[tipoActivo] || { user_ids: [], externos: [] };

    const setActual = (patch) => {
        setPorTipo((prev) => ({
            ...prev,
            [tipoActivo]: { ...prev[tipoActivo], ...patch },
        }));
    };

    const toggleUser = (id) => {
        const numId = Number(id);
        const next = actual.user_ids.includes(numId)
            ? actual.user_ids.filter((x) => x !== numId)
            : [...actual.user_ids, numId];
        setActual({ user_ids: next });
    };

    const agregarExterno = () => {
        const email = nuevoExterno.email.trim().toLowerCase();
        const nombre = nuevoExterno.nombre.trim();
        if (!email || !email.includes('@')) {
            setErrorLocal('Ingresa un correo válido.');
            return;
        }
        if (actual.externos.some((e) => (e.email || '').toLowerCase() === email)) {
            setErrorLocal('Ese correo ya está en la lista.');
            return;
        }
        setActual({ externos: [...actual.externos, { nombre: nombre || email, email }] });
        setNuevoExterno({ nombre: '', email: '' });
        setErrorLocal('');
    };

    const guardar = async () => {
        setSaving(true);
        setErrorLocal('');
        try {
            const { data } = await axios.post(route('listados.destinatarios.guardar'), {
                tipo_lista: tipoActivo,
                user_ids: actual.user_ids.map(Number),
                externos: actual.externos,
            });
            const normalized = {};
            Object.entries(data.destinatarios_por_tipo || {}).forEach(([tipo, val]) => {
                normalized[tipo] = {
                    user_ids: (val.user_ids || []).map(Number),
                    externos: val.externos || [],
                };
            });
            onSaved?.(normalized);
            setErrorLocal('');
        } catch (error) {
            const msg = error.response?.data?.error || error.response?.data?.errors || error.message;
            setErrorLocal(typeof msg === 'string' ? msg : 'No se pudo guardar.');
            onError?.(msg);
        } finally {
            setSaving(false);
        }
    };

    const filteredUsers = usuariosSistema.filter((u) => {
        const q = busqueda.toLowerCase();
        return (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q);
    });

    return createPortal(
        <div className="fixed inset-0 z-[140] flex items-center justify-center bg-black/70 backdrop-blur-xl p-4 animate-fade-in" onClick={onClose}>
            <div
                className="theme-surface border border-zinc-200 dark:border-zinc-700 w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-6 border-b theme-border flex justify-between items-center theme-element shrink-0">
                    <h2 className="text-lg font-black uppercase tracking-widest theme-text-main italic">
                        Destinatarios por Lista_
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main rounded-full outline-none">
                        <X className="w-6 h-6" />
                    </button>
                </div>

                <div className="px-6 pt-4 flex flex-wrap gap-2 shrink-0">
                    {TIPOS_UI.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTipoActivo(t.id)}
                            className={`text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-xl border transition-all outline-none ${
                                tipoActivo === t.id
                                    ? 'text-white border-transparent'
                                    : 'theme-text-muted theme-border theme-surface'
                            }`}
                            style={tipoActivo === t.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                <div className="p-6 overflow-y-auto flex-1 space-y-4 custom-scrollbar">
                    <p className="text-xs theme-text-muted font-bold">
                        Configura a quién se precarga el envío al generar la lista <strong className="theme-text-main">{TIPOS_UI.find((t) => t.id === tipoActivo)?.label}</strong>.
                    </p>

                    <input
                        type="text"
                        placeholder="Buscar usuario..."
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        className="w-full px-4 py-3 text-sm theme-surface border theme-border rounded-2xl theme-text-main outline-none"
                    />

                    <div className="space-y-2 border theme-border rounded-2xl p-2 max-h-[220px] overflow-y-auto custom-scrollbar">
                        {filteredUsers.length === 0 && (
                            <p className="text-xs theme-text-muted font-bold p-3">No hay usuarios Gelia para mostrar.</p>
                        )}
                        {filteredUsers.map((user) => {
                            const isSelected = actual.user_ids.includes(Number(user.id));
                            return (
                                <button
                                    type="button"
                                    key={user.id}
                                    onClick={() => toggleUser(user.id)}
                                    className={`w-full flex items-center gap-3 p-3 rounded-xl text-left border transition-colors ${
                                        isSelected ? 'border-[var(--color-primario)] bg-black/5 dark:bg-white/5' : 'border-transparent hover:bg-black/5 dark:hover:bg-white/5'
                                    }`}
                                >
                                    <div
                                        className={`w-5 h-5 rounded flex items-center justify-center border ${isSelected ? '' : 'border-zinc-300 dark:border-zinc-600'}`}
                                        style={isSelected ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                                    >
                                        {isSelected && <Check className="w-3 h-3 text-white" />}
                                    </div>
                                    <div className="flex flex-col min-w-0">
                                        <span className="text-xs font-bold theme-text-main truncate">{user.name}</span>
                                        <span className="text-[10px] theme-text-muted truncate">{user.email || 'Sin correo'}</span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    <div className="p-4 rounded-2xl border theme-border theme-element space-y-3">
                        <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2">
                            <UserPlus className="w-4 h-4" /> Destinatario externo
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input
                                type="text"
                                placeholder="Nombre"
                                value={nuevoExterno.nombre}
                                onChange={(e) => setNuevoExterno((p) => ({ ...p, nombre: e.target.value }))}
                                className="px-4 py-3 text-sm theme-surface border theme-border rounded-xl theme-text-main outline-none"
                            />
                            <input
                                type="email"
                                placeholder="correo@ejemplo.com"
                                value={nuevoExterno.email}
                                onChange={(e) => setNuevoExterno((p) => ({ ...p, email: e.target.value }))}
                                className="px-4 py-3 text-sm theme-surface border theme-border rounded-xl theme-text-main outline-none"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={agregarExterno}
                            className="text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-xl text-white"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            Agregar correo
                        </button>
                        {actual.externos.map((ext) => (
                            <div key={ext.email} className="flex items-center justify-between gap-2 p-3 rounded-xl theme-surface border theme-border">
                                <div className="min-w-0">
                                    <p className="text-xs font-bold theme-text-main truncate">{ext.nombre}</p>
                                    <p className="text-[10px] theme-text-muted truncate">{ext.email}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setActual({ externos: actual.externos.filter((e) => e.email !== ext.email) })}
                                    className="p-2 text-red-500 outline-none"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                    </div>

                    {errorLocal && <p className="text-red-500 text-xs font-bold">{errorLocal}</p>}
                </div>

                <div className="p-6 border-t theme-border theme-element flex justify-end gap-3 shrink-0">
                    <button type="button" onClick={onClose} className="text-[11px] font-black uppercase tracking-widest px-6 py-4 theme-text-muted rounded-2xl outline-none">
                        Cerrar
                    </button>
                    <button
                        type="button"
                        onClick={guardar}
                        disabled={saving}
                        className="text-[11px] font-black uppercase tracking-widest px-8 py-4 text-white rounded-2xl shadow-lg outline-none flex items-center gap-2 disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Save className="w-4 h-4" />
                        {saving ? 'Guardando...' : 'Guardar este tipo'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
