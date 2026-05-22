import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X, Shield, ShieldAlert, Search, RefreshCw, CheckCheck } from 'lucide-react';

export default function ModalConfiguracionEspecial({ onClose }) {
    // --- ESTADOS ---
    const [clientesEspeciales, setClientesEspeciales] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [cargando, setCargando] = useState(false);
    const [procesandoMasivo, setProcesandoMasivo] = useState(false);

    // --- EFECTOS ---
    useEffect(() => {
        cargarClientes();
    }, []);

    // --- PETICIONES API ---
    const cargarClientes = async () => {
        setCargando(true);
        try {
            const respuesta = await axios.get(route('admin.clientes.especiales'));
            setClientesEspeciales(respuesta.data);
        } catch (error) {
            console.error("Error al recuperar las cuentas protegidas", error);
        } finally {
            setCargando(false);
        }
    };

    // --- ACCIONES OPTIMISTAS ---
    const handleToggleBloqueo = async (clienteId, estadoActual) => {
        const nuevoEstado = !estadoActual;
        
        // 1. Actualización Optimista: Cambiamos la UI instantáneamente para evitar saltos de scroll
        setClientesEspeciales(prev => 
            prev.map(c => c.id === clienteId ? { ...c, lista_bloqueada: nuevoEstado } : c)
        );

        // 2. Petición en segundo plano
        try {
            await axios.post(route('admin.clientes.toggle_bloqueo'), {
                cliente_id: clienteId,
                bloquear: nuevoEstado
            });
        } catch (error) {
            // Reversión en caso de fallo de red
            setClientesEspeciales(prev => 
                prev.map(c => c.id === clienteId ? { ...c, lista_bloqueada: estadoActual } : c)
            );
            alert("No se pudo actualizar el estado de protección. Verifique su conexión.");
        }
    };

    const handleProtegerTodos = async () => {
        // Filtrar solo los que están actualmente en pantalla y desprotegidos
        const clientesADesproteger = clientesFiltrados.filter(c => !c.lista_bloqueada);
        if (clientesADesproteger.length === 0) return;

        const ids = clientesADesproteger.map(c => c.id);
        setProcesandoMasivo(true);

        // Actualización Optimista Masiva
        setClientesEspeciales(prev => 
            prev.map(c => ids.includes(c.id) ? { ...c, lista_bloqueada: true } : c)
        );

        try {
            await axios.post(route('admin.clientes.toggle_bloqueo_masivo'), {
                cliente_ids: ids,
                bloquear: true
            });
        } catch (error) {
            // En caso de fallo grave, recargamos la verdad de la base de datos
            cargarClientes();
            alert("Hubo un error al proteger masivamente.");
        } finally {
            setProcesandoMasivo(false);
        }
    };

    // --- FILTRADO ---
    const clientesFiltrados = clientesEspeciales.filter(c => 
        c.nombre.toLowerCase().includes(busqueda.toLowerCase()) ||
        c.numero_cliente.toLowerCase().includes(busqueda.toLowerCase())
    );

    const cantidadDesprotegidos = clientesFiltrados.filter(c => !c.lista_bloqueada).length;

    // --- RENDERIZADO ---
    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-2xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-8 flex flex-col max-h-[85vh] shadow-2xl modal-pop" onClick={e => e.stopPropagation()}>
                
                {/* --- HEADER --- */}
                <div className="flex justify-between items-center mb-6 relative">
                    <div>
                        <h3 className="text-xl font-black italic uppercase theme-text-main flex items-center gap-2">
                            PANEL DE <span style={{ color: 'var(--color-primario)' }}>PROTECCIÓN</span>
                        </h3>
                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-1">
                            Control de excepciones para sincronización masiva_
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main transition-colors outline-none absolute top-0 right-0">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* --- CONTROLES Y BUSCADOR --- */}
                <div className="flex flex-col gap-3 mb-4 shrink-0">
                    <div className="relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted" />
                        <input 
                            type="text"
                            placeholder="Buscar por número o nombre..."
                            value={busqueda}
                            onChange={e => setBusqueda(e.target.value)}
                            className="w-full px-12 py-3.5 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all"
                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                            onBlur={e => e.target.style.borderColor = ''}
                        />
                    </div>
                    
                    {/* Botón de Acción Masiva */}
                    <div className="flex justify-end">
                        <button
                            onClick={handleProtegerTodos}
                            disabled={cargando || procesandoMasivo || cantidadDesprotegidos === 0}
                            className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-2 outline-none disabled:opacity-50 hover:bg-emerald-500/10 text-emerald-600 border-emerald-500/30"
                        >
                            <CheckCheck className="w-4 h-4" /> 
                            {procesandoMasivo ? 'Procesando...' : `Proteger a Todos (${cantidadDesprotegidos})`}
                        </button>
                    </div>
                </div>

                {/* --- LISTADO DINAMICO --- */}
                <div className="overflow-y-auto flex-1 pr-2 space-y-3 custom-scrollbar">
                    {cargando ? (
                        <div className="text-center py-12 theme-text-muted text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2">
                            <RefreshCw className="w-4 h-4 animate-spin" /> Cargando registros...
                        </div>
                    ) : clientesFiltrados.length === 0 ? (
                        <div className="text-center py-12 border-2 border-dashed theme-border rounded-2xl theme-text-muted text-xs font-bold uppercase">
                            No hay clientes administrativos o protegidos.
                        </div>
                    ) : (
                        clientesFiltrados.map((cliente) => (
                            <div key={cliente.id} className="p-4 theme-surface border theme-border rounded-xl flex items-center justify-between gap-4 transition-all hover:shadow-md group">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-[13px] font-black theme-text-main uppercase">{cliente.nombre}</span>
                                        <span className="text-[9px] px-2 py-0.5 theme-element border theme-border theme-text-muted rounded-md font-black italic tracking-widest">{cliente.numero_cliente}</span>
                                    </div>
                                    <p className="text-[10px] font-bold mt-1 uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                                        Lista: {cliente.lista_descuento?.nombre || 'Ninguna'}
                                    </p>
                                </div>

                                <button
                                    onClick={() => handleToggleBloqueo(cliente.id, cliente.lista_bloqueada)}
                                    disabled={cargando || procesandoMasivo}
                                    className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-2 outline-none disabled:opacity-50 ${
                                        cliente.lista_bloqueada 
                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/20' 
                                            : 'bg-amber-500/10 text-amber-600 border-amber-500/30 hover:bg-amber-500/20'
                                    }`}
                                >
                                    {cliente.lista_bloqueada ? (
                                        <><Shield className="w-4 h-4" /> Protegido</>
                                    ) : (
                                        <><ShieldAlert className="w-4 h-4" /> Desprotegido</>
                                    )}
                                </button>
                            </div>
                        ))
                    )}
                </div>

                {/* --- FOOTER --- */}
                <div className="pt-6 mt-4 border-t theme-border flex justify-end shrink-0">
                    <button onClick={onClose} className="px-8 py-3 text-white rounded-xl font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                        Cerrar Panel_
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}