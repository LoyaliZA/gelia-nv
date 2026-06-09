import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Check, Save } from 'lucide-react';

export default function ConfiguracionBellaroma({ onClose, users, notifiedUserIds }) {
    const { data, setData, post, processing, errors } = useForm({
        notified_users: notifiedUserIds || [],
    });

    const [busqueda, setBusqueda] = useState('');

    const toggleUser = (userId) => {
        const current = [...data.notified_users];
        if (current.includes(userId)) {
            setData('notified_users', current.filter(id => id !== userId));
        } else {
            setData('notified_users', [...current, userId]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('plantilla_bellaroma.configuracion.guardar'), {
            onSuccess: () => onClose(),
        });
    };

    const filteredUsers = users.filter(u => u.name.toLowerCase().includes(busqueda.toLowerCase()) || u.email.toLowerCase().includes(busqueda.toLowerCase()));

    const modalContent = (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
            
            <div className="relative bg-white dark:bg-[#1a1c23] rounded-2xl shadow-2xl w-full max-w-lg border border-gray-200 dark:border-gray-800 overflow-hidden flex flex-col max-h-[90vh]">
                <div className="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-white/5">
                    <h2 className="text-lg font-black uppercase tracking-tight theme-text-main">
                        Configuración <span style={{ color: 'var(--color-primario)' }}>Notificaciones</span>
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-6 overflow-y-auto flex-1">
                    <p className="text-xs font-bold theme-text-muted mb-4">
                        Selecciona a los usuarios que recibirán la plantilla de pedidos por correo electrónico y notificación interna al momento de generarse.
                    </p>

                    <input 
                        type="text" 
                        placeholder="Buscar usuario..."
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        className="w-full mb-4 px-4 py-2 text-sm theme-surface border theme-border rounded-lg theme-text-main focus:outline-none transition-colors"
                        style={{ backgroundColor: 'var(--color-fondo-secundario)' }}
                        onFocus={(e) => { e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                        onBlur={(e) => { e.currentTarget.style.borderColor = ''; }}
                    />

                    <div className="space-y-2 border theme-border rounded-xl p-2 max-h-[300px] overflow-y-auto custom-scrollbar">
                        {filteredUsers.map(user => {
                            const isSelected = data.notified_users.includes(user.id);
                            return (
                                <div 
                                    key={user.id}
                                    onClick={() => toggleUser(user.id)}
                                    className={`flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors
                                        ${isSelected ? 'bg-black/5 dark:bg-white/5 border' : 'hover:bg-gray-50 dark:hover:bg-white/5 border border-transparent'}
                                    `}
                                    style={isSelected ? { borderColor: 'var(--color-primario)' } : {}}
                                >
                                    <div className={`w-5 h-5 rounded flex items-center justify-center border ${isSelected ? '' : 'border-gray-300 dark:border-gray-600'}`} style={isSelected ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}>
                                        {isSelected && <Check className="w-3 h-3 text-white" />}
                                    </div>
                                    <div className="flex flex-col">
                                        <span className={`text-xs font-bold ${isSelected ? '' : 'theme-text-main'}`} style={isSelected ? { color: 'var(--color-primario)' } : {}}>{user.name}</span>
                                        <span className="text-[10px] theme-text-muted">{user.email}</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {errors.notified_users && <p className="text-red-500 text-xs font-bold mt-2">{errors.notified_users}</p>}
                </div>

                <div className="p-6 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/5 flex justify-end gap-3">
                    <button 
                        type="button" 
                        onClick={onClose}
                        className="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wide theme-text-main border theme-border hover:bg-gray-100 dark:hover:bg-white/5 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button 
                        onClick={submit}
                        disabled={processing}
                        className="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wide text-white disabled:opacity-50 transition-colors flex items-center gap-2"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                        onMouseEnter={(e) => { e.currentTarget.style.filter = 'brightness(0.9)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.filter = ''; }}
                    >
                        <Save className="w-4 h-4" />
                        {processing ? 'Guardando...' : 'Guardar Configuración'}
                    </button>
                </div>
            </div>
        </div>
    );

    if (typeof window === 'undefined') return modalContent;

    return createPortal(modalContent, document.body);
}
