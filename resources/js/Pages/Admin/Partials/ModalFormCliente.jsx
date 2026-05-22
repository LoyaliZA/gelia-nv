import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, User, ChevronDown, Check, TrendingUp, ShieldCheck, ListOrdered } from 'lucide-react';

export default function ModalFormCliente({ onClose, modoModal, clienteActual, tiposCliente = [], vendedores = [], listas = [] }) {
    
    // --- SECCIÓN: INICIALIZACIÓN DE FORMULARIO 360 ---
    const { data, setData, post, put, processing, errors } = useForm({
        numero_cliente: clienteActual?.numero_cliente || '',
        nombre: clienteActual?.nombre || '',
        vendedor_id: clienteActual?.vendedor_id || '',
        es_heredado: clienteActual?.es_heredado === 1 || clienteActual?.es_heredado === true,
        catalogo_tipo_cliente_id: clienteActual?.catalogo_tipo_cliente_id || '',
        monto_venta_actual: clienteActual?.monto_venta_actual || 0,
        lista_actual_id: clienteActual?.lista_actual_id || '',
        lista_bloqueada: clienteActual?.lista_bloqueada === 1 || clienteActual?.lista_bloqueada === true,
    });

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
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-xl theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col space-y-6 relative modal-pop max-h-[90vh] overflow-y-auto custom-scrollbar" onClick={e => e.stopPropagation()}>
                
                <button onClick={onClose} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main rounded-full transition-colors outline-none">
                    <X className="w-5 h-5" />
                </button>
                
                <form onSubmit={guardarCliente} className="space-y-6 w-full">
                    <div>
                        <h3 className="text-xl font-black uppercase italic theme-text-main m-0">
                            {modoModal === 'crear' ? 'Nuevo' : 'Gestión 360'} <span style={{ color: 'var(--color-primario)' }}>Cliente_</span>
                        </h3>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">
                            Control total de identidad, comercial y seguridad_
                        </p>
                    </div>

                    <div className="space-y-5">
                        {/* --- BLOQUE 1: IDENTIDAD --- */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Número Cliente</label>
                                <input 
                                    type="text" 
                                    value={data.numero_cliente} 
                                    onChange={e => setData('numero_cliente', e.target.value)}
                                    className="w-full px-5 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main theme-placeholder outline-none focus:ring-2 transition-all shadow-sm"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    placeholder="W-0000"
                                    onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                    onBlur={e => e.target.style.borderColor = ''}
                                />
                                {errors.numero_cliente && <p className="text-[9px] text-red-500 font-bold ml-1">{errors.numero_cliente}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre Completo</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                    <input 
                                        type="text" 
                                        value={data.nombre} 
                                        onChange={e => setData('nombre', e.target.value)}
                                        className="w-full pl-10 pr-4 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main theme-placeholder outline-none focus:ring-2 transition-all shadow-sm"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        placeholder="Nombre completo"
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    />
                                </div>
                                {errors.nombre && <p className="text-[9px] text-red-500 font-bold ml-1">{errors.nombre}</p>}
                            </div>
                        </div>

                        {/* --- BLOQUE 2: COMERCIAL --- */}
                        <div className="p-6 theme-element border theme-border rounded-2xl space-y-4 shadow-sm">
                            <div className="flex items-center gap-2 mb-2">
                                <TrendingUp className="w-4 h-4 text-emerald-500 drop-shadow-sm" />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Situación Comercial Actual_</span>
                            </div>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Monto de Venta (MXN)</label>
                                    <input 
                                        type="number" 
                                        step="0.01"
                                        value={data.monto_venta_actual} 
                                        onChange={e => setData('monto_venta_actual', e.target.value)}
                                        className="w-full px-5 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main outline-none focus:ring-2 transition-all shadow-sm"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Lista de Descuento</label>
                                    <div className="relative">
                                        <ListOrdered className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                        <select 
                                            value={data.lista_actual_id} 
                                            onChange={e => setData('lista_actual_id', e.target.value)}
                                            className="w-full pl-10 pr-10 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main appearance-none outline-none focus:ring-2 transition-all shadow-sm cursor-pointer"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                            onBlur={e => e.target.style.borderColor = ''}
                                        >
                                            <option value="">-- Seleccionar --</option>
                                            {listas?.map(lista => (
                                                <option key={lista.id} value={lista.id}>{lista.nombre}</option>
                                            ))}
                                        </select>
                                        <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* --- BLOQUE 3: ASIGNACIÓN Y SEGURIDAD --- */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Vendedora_</label>
                                <div className="relative">
                                    <select 
                                        value={data.vendedor_id} 
                                        onChange={e => setData('vendedor_id', e.target.value)}
                                        className="w-full pl-5 pr-10 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main appearance-none outline-none focus:ring-2 transition-all shadow-sm cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    >
                                        <option value="">-- Sin Asignar --</option>
                                        {vendedores?.map(v => <option key={v.id} value={v.id}>{v.name}</option>)}
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipo_</label>
                                <div className="relative">
                                    <select 
                                        value={data.catalogo_tipo_cliente_id} 
                                        onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)}
                                        className="w-full pl-5 pr-10 py-3.5 theme-surface border theme-border rounded-xl font-bold text-sm theme-text-main appearance-none outline-none focus:ring-2 transition-all shadow-sm cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    >
                                        <option value="">-- Por definir --</option>
                                        {tiposCliente?.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* --- BLOQUE 4: SWITCHES DE PROTECCIÓN --- */}
                        <div className="flex flex-col gap-3">
                            <div 
                                className="p-4 theme-element border theme-border rounded-xl flex items-center justify-between cursor-pointer transition-all hover:shadow-md" 
                                style={{ borderColor: data.lista_bloqueada ? 'var(--color-primario)' : '' }}
                                onClick={() => setData('lista_bloqueada', !data.lista_bloqueada)}
                            >
                                <div className="flex items-center gap-3">
                                    <ShieldCheck className={`w-5 h-5 ${data.lista_bloqueada ? 'text-emerald-500' : 'theme-text-muted'}`} />
                                    <div>
                                        <span className="text-[11px] font-black theme-text-main uppercase tracking-widest block leading-tight">Proteger Lista Actual_</span>
                                        <span className="text-[9px] font-bold theme-text-muted uppercase">Inmune a cambios por sincronización CSV</span>
                                    </div>
                                </div>
                                {/* Estructura correcta del switch */}
                                <div className="gelia-switch shrink-0 pointer-events-none" data-active={data.lista_bloqueada}>
                                    <div className="gelia-switch-thumb shadow-md" />
                                </div>
                            </div>

                            <div 
                                className="p-4 theme-element border theme-border rounded-xl flex items-center justify-between cursor-pointer transition-all hover:shadow-md" 
                                style={{ borderColor: data.es_heredado ? 'var(--color-primario)' : '' }}
                                onClick={() => setData('es_heredado', !data.es_heredado)}
                            >
                                <div className="flex items-center gap-3">
                                    <User className={`w-5 h-5 ${data.es_heredado ? 'text-amber-500' : 'theme-text-muted'}`} />
                                    <div>
                                        <span className="text-[11px] font-black theme-text-main uppercase tracking-widest block leading-tight">Cliente Heredado_</span>
                                        <span className="text-[9px] font-bold theme-text-muted uppercase">Reglas de seguridad especiales</span>
                                    </div>
                                </div>
                                {/* Estructura correcta del switch */}
                                <div className="gelia-switch shrink-0 pointer-events-none" data-active={data.es_heredado}>
                                    <div className="gelia-switch-thumb shadow-md" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" disabled={processing} className="w-full py-4 mt-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-50 disabled:scale-100" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Check className="w-5 h-5" /> {processing ? 'Sincronizando...' : 'Guardar Cambios_'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}