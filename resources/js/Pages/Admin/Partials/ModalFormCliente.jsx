import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, User, ChevronDown, Check } from 'lucide-react';

export default function ModalFormCliente({ onClose, modoModal, clienteActual, tiposCliente = [], vendedores = [] }) {
    
    // --- SECCIÓN: INICIALIZACIÓN DE FORMULARIO ---
    // Al instanciar useForm dentro del modal, garantizamos que el estado
    // se reinicie limpiamente cada vez que se abre y se destruya al cerrarse.
    const { data, setData, post, put, processing, errors } = useForm({
        numero_cliente: clienteActual?.numero_cliente || '',
        nombre: clienteActual?.nombre || '',
        vendedor_id: clienteActual?.vendedor_id || '',
        es_heredado: clienteActual?.es_heredado === 1 || clienteActual?.es_heredado === true,
        catalogo_tipo_cliente_id: clienteActual?.catalogo_tipo_cliente_id || '',
    });

    // --- SECCIÓN: LÓGICA DE GUARDADO ---
    const guardarCliente = (e) => {
        e.preventDefault();
        
        const config = { onSuccess: () => onClose() };

        if (modoModal === 'crear') {
            post(route('admin.clientes.store'), config);
        } else {
            put(route('admin.clientes.update', clienteActual.id), config);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col space-y-6 relative modal-pop max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                
                <button onClick={onClose} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                    <X className="w-5 h-5" />
                </button>
                
                <form onSubmit={guardarCliente} className="space-y-6 w-full">
                    <div>
                        <h3 className="text-xl font-black uppercase italic tracking-tighter theme-text-main m-0 drop-shadow-sm">
                            {modoModal === 'crear' ? 'Nuevo' : 'Editar'} <span style={{ color: 'var(--color-primario)' }}>Cliente_</span>
                        </h3>
                        <p className="text-xs font-bold theme-text-muted mt-1">
                            {modoModal === 'crear' ? 'Ingresa los datos del nuevo cliente.' : clienteActual?.nombre}
                        </p>
                    </div>

                    <div className="space-y-4">
                        
                        {/* --- SECCIÓN: CAMPOS DEL FORMULARIO --- */}
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipo de Cliente_</label>
                            <div className="relative">
                                <select 
                                    value={data.catalogo_tipo_cliente_id} 
                                    onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)}
                                    className="w-full px-5 py-4 theme-surface border theme-border rounded-xl font-bold text-sm outline-none transition-all shadow-sm appearance-none cursor-pointer"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                    onBlur={e => e.target.style.borderColor = ''}
                                >
                                    <option value="">-- Sin asignar (Por definir) --</option>
                                    {tiposCliente.map(tipo => (
                                        <option key={tipo.id} value={tipo.id}>{tipo.nombre}</option>
                                    ))}
                                </select>
                                <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                    <ChevronDown className="w-4 h-4 theme-text-muted" />
                                </div>
                            </div>
                            {errors.catalogo_tipo_cliente_id && <p className="text-xs text-red-500 mt-1">{errors.catalogo_tipo_cliente_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre Completo</label>
                            <div className="relative">
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <input 
                                    type="text" 
                                    value={data.nombre} 
                                    onChange={e => setData('nombre', e.target.value)}
                                    className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md theme-placeholder"
                                    placeholder="Nombre del cliente o empresa"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                    onBlur={e => e.target.style.borderColor = ''}
                                />
                            </div>
                            {errors.nombre && <p className="text-[10px] font-bold text-red-500 uppercase tracking-widest m-0 mt-1 ml-1">{errors.nombre}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Vendedora Asignada</label>
                            <div className="relative">
                                <select 
                                    value={data.vendedor_id} 
                                    onChange={e => setData('vendedor_id', e.target.value)}
                                    className="w-full px-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md cursor-pointer appearance-none"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                    onBlur={e => e.target.style.borderColor = ''}
                                >
                                    <option value="">-- Sin Asignar --</option>
                                    {vendedores?.map(vendedor => (
                                        <option key={vendedor.id} value={vendedor.id}>{vendedor.name}</option>
                                    ))}
                                </select>
                                <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                    <ChevronDown className="w-4 h-4 theme-text-muted" />
                                </div>
                            </div>
                        </div>

                        <div 
                            className="p-5 theme-element border theme-border rounded-xl flex items-center justify-between cursor-pointer group transition-all hover:shadow-md mt-2" 
                            style={{ borderColor: data.es_heredado ? 'var(--color-primario)' : '' }}
                            onClick={() => setData('es_heredado', !data.es_heredado)}
                        >
                            <div>
                                <span className="text-sm font-black theme-text-main uppercase tracking-widest block leading-tight transition-colors" style={{ color: data.es_heredado ? 'var(--color-primario)' : '' }}>Cliente Heredado</span>
                                <span className="text-[10px] font-bold theme-text-muted uppercase tracking-widest">Reglas de seguridad especiales</span>
                            </div>
                            <button type="button" className="gelia-switch shrink-0 scale-110 origin-right pointer-events-none" data-active={data.es_heredado}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </div>
                    </div>

                    <button type="submit" disabled={processing} className="w-full py-4 mt-6 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-60 disabled:scale-100" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Check className="w-5 h-5" /> {processing ? 'Guardando...' : 'Guardar Cliente_'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}