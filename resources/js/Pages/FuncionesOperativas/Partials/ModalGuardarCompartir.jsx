import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Check, Mail, Save, Download, UserPlus, Trash2 } from 'lucide-react';

export default function ModalGuardarCompartir({
    show,
    tempFile,
    nombreDescarga,
    tipoLista,
    permisos = {},
    destinatariosDefault = { user_ids: [], externos: [] },
    usuariosSistema = [],
    inconsistencias = [],
    onClose,
    onConfirm,
}) {
    const [guardar, setGuardar] = useState(false);
    const [enviar, setEnviar] = useState(false);
    const [userIds, setUserIds] = useState([]);
    const [externos, setExternos] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [nuevoExterno, setNuevoExterno] = useState({ nombre: '', email: '' });
    const [errorLocal, setErrorLocal] = useState('');

    useEffect(() => {
        if (!show) return;
        setGuardar(!!permisos.guardar_generado);
        setEnviar(false);
        setUserIds((destinatariosDefault.user_ids || []).map((x) => Number(x)));
        setExternos([...(destinatariosDefault.externos || [])]);
        setBusqueda('');
        setNuevoExterno({ nombre: '', email: '' });
        setErrorLocal('');
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [show, tempFile, destinatariosDefault, permisos.guardar_generado]);

    if (!show) return null;

    const toggleUser = (id) => {
        const numId = Number(id);
        setUserIds((prev) => (prev.includes(numId) ? prev.filter((x) => x !== numId) : [...prev, numId]));
    };

    const agregarExterno = () => {
        const email = nuevoExterno.email.trim().toLowerCase();
        const nombre = nuevoExterno.nombre.trim();
        if (!email || !email.includes('@')) {
            setErrorLocal('Ingresa un correo válido para el destinatario externo.');
            return;
        }
        if (externos.some((e) => (e.email || '').toLowerCase() === email)) {
            setErrorLocal('Ese correo ya está en la lista.');
            return;
        }
        setExternos((prev) => [...prev, { nombre: nombre || email, email }]);
        setNuevoExterno({ nombre: '', email: '' });
        setErrorLocal('');
    };

    const quitarExterno = (email) => {
        setExternos((prev) => prev.filter((e) => e.email !== email));
    };

    const handleConfirm = () => {
        if (enviar && userIds.length === 0 && externos.length === 0) {
            setErrorLocal('Selecciona al menos un destinatario para enviar.');
            return;
        }
        onConfirm({
            temp_file: tempFile,
            nombre_descarga: nombreDescarga,
            tipo_lista: tipoLista,
            guardar: guardar && !!permisos.guardar_generado,
            enviar: enviar && !!permisos.enviar,
            destinatarios_user_ids: userIds.map(Number),
            destinatarios_externos: externos,
        });
    };

    const filteredUsers = usuariosSistema.filter((u) => {
        const q = busqueda.toLowerCase();
        return (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q);
    });

    return createPortal(
        <div className="fixed inset-0 z-[145] flex items-center justify-center bg-black/70 backdrop-blur-xl p-4 animate-fade-in" onClick={onClose}>
            <div
                className="theme-surface border border-zinc-200 dark:border-zinc-700 w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-6 border-b theme-border flex justify-between items-center theme-element shrink-0">
                    <div>
                        <h2 className="text-lg font-black uppercase tracking-widest theme-text-main italic">
                            Listado Generado_
                        </h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1 truncate max-w-md">
                            {nombreDescarga}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main rounded-full outline-none">
                        <X className="w-6 h-6" />
                    </button>
                </div>

                <div className="p-6 overflow-y-auto flex-1 space-y-6 custom-scrollbar">
                    {inconsistencias.length > 0 && (
                        <div className="p-4 rounded-2xl border border-orange-500/40 bg-orange-500/5 text-orange-600 text-xs font-bold">
                            Se detectaron {inconsistencias.length} inconsistencia(s). Puedes continuar con guardar/compartir o solo descargar.
                        </div>
                    )}

                    <div className="space-y-3">
                        {permisos.guardar_generado && (
                            <label className="flex items-center gap-3 p-4 rounded-2xl border theme-border theme-surface cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={guardar}
                                    onChange={(e) => setGuardar(e.target.checked)}
                                    className="w-5 h-5 rounded"
                                    style={{ accentColor: 'var(--color-primario)' }}
                                />
                                <div>
                                    <span className="text-xs font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                                        <Save className="w-4 h-4" /> Guardar en historial
                                    </span>
                                    <p className="text-[10px] theme-text-muted font-bold mt-1">El archivo quedará disponible para descarga posterior.</p>
                                </div>
                            </label>
                        )}

                        {permisos.enviar && (
                            <label className="flex items-center gap-3 p-4 rounded-2xl border theme-border theme-surface cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={enviar}
                                    onChange={(e) => setEnviar(e.target.checked)}
                                    className="w-5 h-5 rounded"
                                    style={{ accentColor: 'var(--color-primario)' }}
                                />
                                <div>
                                    <span className="text-xs font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                                        <Mail className="w-4 h-4" /> Compartir por correo
                                    </span>
                                    <p className="text-[10px] theme-text-muted font-bold mt-1">Envía el Excel a usuarios Gelia y/o correos externos.</p>
                                </div>
                            </label>
                        )}
                    </div>

                    {enviar && permisos.enviar && (
                        <div className="space-y-4">
                            <input
                                type="text"
                                placeholder="Buscar usuario..."
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                className="w-full px-4 py-3 text-sm theme-surface border theme-border rounded-2xl theme-text-main outline-none"
                            />

                            <div className="space-y-2 border theme-border rounded-2xl p-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                                {filteredUsers.length === 0 && (
                                    <p className="text-xs theme-text-muted font-bold p-3">No hay usuarios Gelia para mostrar.</p>
                                )}
                                {filteredUsers.map((user) => {
                                    const isSelected = userIds.includes(Number(user.id));
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
                                {externos.length > 0 && (
                                    <div className="space-y-2 pt-2">
                                        {externos.map((ext) => (
                                            <div key={ext.email} className="flex items-center justify-between gap-2 p-3 rounded-xl theme-surface border theme-border">
                                                <div className="min-w-0">
                                                    <p className="text-xs font-bold theme-text-main truncate">{ext.nombre}</p>
                                                    <p className="text-[10px] theme-text-muted truncate">{ext.email}</p>
                                                </div>
                                                <button type="button" onClick={() => quitarExterno(ext.email)} className="p-2 text-red-500 outline-none">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {errorLocal && <p className="text-red-500 text-xs font-bold">{errorLocal}</p>}
                </div>

                <div className="p-6 border-t theme-border theme-element flex flex-wrap justify-end gap-3 shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-[11px] font-black uppercase tracking-widest px-6 py-4 theme-text-muted rounded-2xl hover:bg-black/5 dark:hover:bg-white/5 outline-none"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        className="text-[11px] font-black uppercase tracking-widest px-8 py-4 text-white rounded-2xl shadow-lg outline-none flex items-center gap-2"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Download className="w-4 h-4" />
                        Confirmar y Descargar
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
