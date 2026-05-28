import React from 'react';
import { createPortal } from 'react-dom';
import { X, History, ShieldCheck, CheckCircle2, FileImage, Camera, Users, TrendingUp, Tag, Server } from 'lucide-react';

const ComparativaSnapshot = ({ antes, despues }) => {
    if (!antes && !despues) return null;

    const filas = [
        { label: 'Monto de venta', key: 'monto_venta', formato: (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v || 0) },
        { label: 'Lista', key: 'lista_nombre', formato: (v) => v || 'Sin lista' },
        { label: 'TAG (Vendedora)', key: 'tag_vendedor_nombre', formato: (v) => v || 'Sin asignar' },
        { label: 'Clasificación', key: 'tipo_cliente_nombre', formato: (v) => v || 'Normal' },
    ];

    return (
        <div className="mb-4 p-4 bg-black/5 dark:bg-white/5 rounded-2xl border theme-border">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Comparativa de cambios</p>
            <div className="grid grid-cols-3 gap-2 text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">
                <span>Campo</span>
                <span className="text-center">Antes</span>
                <span className="text-center">Después</span>
            </div>
            {filas.map(({ label, key, formato }) => (
                <div key={key} className="grid grid-cols-3 gap-2 py-2 border-t theme-border items-center">
                    <span className="text-[9px] font-black uppercase theme-text-muted">{label}</span>
                    <span className="text-xs font-bold theme-text-main text-center">{formato(antes?.[key])}</span>
                    <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400 text-center">{formato(despues?.[key])}</span>
                </div>
            ))}
        </div>
    );
};

export default function ModalBitacoraSolicitud({ onClose, solicitud, listas = [], tiposCliente = [] }) {
    if (!solicitud) return null;

    const auditoriasLimpias = solicitud?.auditorias || [];
    const objListaActual = solicitud.lista_descuento || solicitud.listaDescuento;
    const objTipoActual = solicitud.tipo_cliente || solicitud.tipoCliente;
    const consultas = solicitud?.consultas || [];

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-6xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh]" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none hover:scale-110 transition-transform z-10">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-4 mb-8 shrink-0 border-b theme-border pb-6">
                    <History className="w-10 h-10 text-purple-500 drop-shadow-sm" />
                    <div>
                        <h2 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">Expediente de Auditoría_</h2>
                        <p className="text-xs font-bold theme-text-muted uppercase tracking-widest mt-1">Folio: FOL-{solicitud?.id}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 flex-1 overflow-hidden">
                    <div className="theme-element border theme-border rounded-3xl p-8 overflow-y-auto custom-scrollbar">
                        <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-6">Estado Actual de la Solicitud</h3>
                        <div className="space-y-6">
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cliente</p><p className="text-base font-black theme-text-main">{solicitud?.cliente?.nombre}</p></div>
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Proceso Solicitado</p><p className="text-base font-black theme-text-main">{solicitud?.proceso?.nombre}</p></div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-4 rounded-xl bg-black/5 dark:bg-white/5 border theme-border">
                                    <p className="text-[10px] font-black uppercase theme-text-muted tracking-widest mb-1 flex items-center gap-1"><Users className="w-3 h-3" /> Clasificación</p>
                                    <p className="text-sm font-bold theme-text-main">{objTipoActual?.nombre || 'Normal'}</p>
                                </div>
                                <div className="p-4 rounded-xl bg-black/5 dark:bg-white/5 border theme-border">
                                    <p className="text-[10px] font-black uppercase theme-text-muted tracking-widest mb-1 flex items-center gap-1"><TrendingUp className="w-3 h-3" /> Lista Solicitada</p>
                                    <p className="text-sm font-bold theme-text-main">{objListaActual?.nombre || 'Mantener actual'}</p>
                                </div>
                            </div>
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cotización Final</p><p className="text-base font-black theme-text-main">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud?.monto_cotizado || 0)}</p></div>
                            <div>
                                <p className="text-[10px] font-bold theme-text-muted uppercase mb-3">Comentario de la Vendedora</p>
                                {solicitud?.observaciones_vendedor?.trim() ? (
                                    <p className="text-sm font-bold theme-text-main italic leading-relaxed p-4 rounded-2xl border theme-border theme-element">
                                        {solicitud.observaciones_vendedor}
                                    </p>
                                ) : (
                                    <p className="text-sm font-bold theme-text-muted italic">Sin comentario registrado.</p>
                                )}
                            </div>
                            {(solicitud?.monto_final_tentativo || solicitud?.total_proyectado_neto) && (
                                <div className="grid grid-cols-2 gap-3">
                                    {solicitud.monto_final_tentativo != null && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Pago Tentativo</p>
                                            <p className="text-sm font-black theme-text-main">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_final_tentativo)}</p>
                                        </div>
                                    )}
                                    {solicitud.total_proyectado_neto != null && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Total Neto Proyectado</p>
                                            <p className={`text-sm font-black ${parseFloat(solicitud.total_proyectado_neto) >= parseFloat(objListaActual?.monto_requerido || 0) ? 'text-emerald-500' : 'text-amber-500'}`}>
                                                {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.total_proyectado_neto)}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                            {solicitud?.compra_en_tienda && (
                                <p className="text-[10px] font-bold text-[#b87333] dark:text-[#daa520] uppercase tracking-widest flex items-center gap-1">
                                    Compra en tienda — lista Bronce asignada al crear la solicitud.
                                </p>
                            )}
                            {solicitud?.confirmo_informacion_escalonamiento && (
                                <p className="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest">
                                    Vendedora confirmó haber informado al cliente sobre el escalonamiento.
                                </p>
                            )}
                            {solicitud?.evidencia_path && (
                                <div>
                                    <p className="text-[10px] font-bold theme-text-muted uppercase mb-3">Evidencia Histórica</p>
                                    <a href={`/storage/${solicitud.evidencia_path}`} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-2xl border theme-border hover:ring-2 transition-all h-40">
                                        <img src={`/storage/${solicitud.evidencia_path}`} className="w-full h-full object-cover hover:scale-105 transition-transform" alt="Evidencia histórica" />
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="overflow-y-auto custom-scrollbar relative px-6 py-4 before:absolute before:inset-0 before:ml-6 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-purple-300 dark:before:via-purple-900/50 before:to-transparent">
                        <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-8 ml-8 relative z-10 theme-surface inline-block pr-4">Línea de Tiempo Operativa</h3>

                        <div className="space-y-8">
                            {auditoriasLimpias.length === 0 && consultas.length === 0 && (
                                <p className="ml-10 text-xs font-bold theme-text-muted italic">No hay registros de auditoría disponibles.</p>
                            )}

                            {auditoriasLimpias.map((registro, idx) => {
                                const estadoNombre = registro.estado_nuevo?.nombre;
                                const esRespuesta = estadoNombre === 'Respondida' || estadoNombre === 'Verificada' || estadoNombre === 'Incorrecta';
                                const esSistema = registro.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE') || registro.motivo_reporte?.toUpperCase().includes('SISTEMA AUTOMÁTICO');
                                const snapshot = typeof registro.datos_snapshot === 'string' ? JSON.parse(registro.datos_snapshot) : registro.datos_snapshot;
                                const nombreListaHistorial = snapshot?.lista_descuento_id ? listas.find(l => l.id == snapshot.lista_descuento_id)?.nombre : null;
                                const nombreTipoHistorial = snapshot?.tipo_cliente_id ? tiposCliente.find(t => t.id == snapshot.tipo_cliente_id)?.nombre : null;

                                return (
                                    <div key={`audit-${idx}`} className="relative flex flex-col ml-10">
                                        <div className={`absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface flex items-center justify-center shadow-md z-10 ${esSistema ? 'bg-slate-500 text-white' : esRespuesta ? 'bg-emerald-500 text-white' : 'bg-purple-500 text-white'}`}>
                                            {esSistema ? <Server className="w-4 h-4" /> : esRespuesta ? <CheckCircle2 className="w-4 h-4" /> : <ShieldCheck className="w-4 h-4" />}
                                        </div>

                                        <div className={`theme-element border ${esRespuesta ? 'border-emerald-500/30' : 'theme-border'} p-6 rounded-3xl shadow-sm`}>
                                            <div className="flex justify-between items-center mb-3">
                                                <div className="flex items-center gap-2">
                                                    <span className={`font-black text-xs uppercase tracking-widest ${esRespuesta ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`}>
                                                        {registro.usuario?.name}
                                                    </span>
                                                    {esSistema && (
                                                        <span className="text-[8px] font-black uppercase px-2 py-0.5 rounded bg-slate-500/10 text-slate-500 border border-slate-500/20">Sistema</span>
                                                    )}
                                                </div>
                                                <span className="text-[10px] font-bold theme-text-muted">{new Date(registro.created_at).toLocaleString()}</span>
                                            </div>

                                            <p className="text-sm font-black theme-text-main mb-3">
                                                Estado: <span className="italic">{estadoNombre || 'Actualización / Corrección'}</span>
                                            </p>

                                            {(snapshot?.antes || snapshot?.despues) && (
                                                <ComparativaSnapshot antes={snapshot.antes} despues={snapshot.despues} />
                                            )}

                                            {snapshot && !esRespuesta && !snapshot?.antes && (
                                                <div className="mb-4 p-4 bg-black/5 dark:bg-white/5 rounded-2xl border theme-border flex flex-col gap-3">
                                                    {(nombreListaHistorial || nombreTipoHistorial || snapshot?.compra_en_tienda) && (
                                                        <div className="flex flex-wrap gap-2 mb-2">
                                                            {snapshot?.compra_en_tienda && (
                                                                <span className="text-[9px] font-black uppercase px-2 py-1 rounded bg-[#cd7f32]/15 text-[#b87333] border border-[#cd7f32]/30">
                                                                    Compra en tienda · {snapshot.lista_descuento_nombre || nombreListaHistorial || 'Bronce'}
                                                                </span>
                                                            )}
                                                            {nombreListaHistorial && <span className="text-[9px] font-black uppercase px-2 py-1 rounded bg-blue-500/10 text-blue-500 border border-blue-500/20">Aspiraba a: {nombreListaHistorial}</span>}
                                                            {nombreTipoHistorial && <span className="text-[9px] font-black uppercase px-2 py-1 rounded bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">Clasificación: {nombreTipoHistorial}</span>}
                                                        </div>
                                                    )}
                                                    <div className="flex justify-between items-center">
                                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización Proyectada</p>
                                                        <span className="text-xs font-black theme-text-main">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(snapshot.monto_cotizado || 0)}</span>
                                                    </div>
                                                    {snapshot.evidencia_path && (
                                                        <a href={`/storage/${snapshot.evidencia_path}`} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-blue-500 hover:text-blue-600 transition-colors w-fit">
                                                            <FileImage className="w-3 h-3" /> Ver Evidencia Adjunta
                                                        </a>
                                                    )}
                                                </div>
                                            )}

                                            {registro.motivo_reporte && (
                                                <div className="p-4 theme-surface rounded-2xl border theme-border">
                                                    <p className="text-xs font-bold theme-text-main m-0 uppercase tracking-widest leading-relaxed">
                                                        Nota: {registro.motivo_reporte}
                                                    </p>
                                                </div>
                                            )}

                                            {esRespuesta && snapshot?.evidencia_respuesta_path && (
                                                <div className="mt-6 pt-4 border-t border-emerald-500/20">
                                                    <h4 className="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                        <Camera className="w-3 h-3" /> Evidencia de Resolución
                                                    </h4>
                                                    <a href={`/storage/${snapshot.evidencia_respuesta_path}`} target="_blank" rel="noreferrer" className="block rounded-2xl overflow-hidden border border-emerald-500/30 h-32 relative group">
                                                        <img src={`/storage/${snapshot.evidencia_respuesta_path}`} className="w-full h-full object-cover group-hover:scale-105 transition-transform" alt="Evidencia" />
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}

                            {consultas.map((consulta, idx) => {
                                const temas = [consulta.consulta_tag && 'TAG', consulta.consulta_lista && 'Lista'].filter(Boolean);
                                return (
                                    <div key={`consulta-${idx}`} className="relative flex flex-col ml-10">
                                        <div className="absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface flex items-center justify-center shadow-md z-10 bg-amber-500 text-white">
                                            <Tag className="w-4 h-4" />
                                        </div>
                                        <div className="theme-element border border-amber-500/30 p-6 rounded-3xl shadow-sm">
                                            <div className="flex justify-between items-center mb-3">
                                                <span className="font-black text-xs uppercase tracking-widest text-amber-600 dark:text-amber-400">
                                                    Consulta · {consulta.vendedor?.name}
                                                </span>
                                                <span className="text-[10px] font-bold theme-text-muted">{new Date(consulta.created_at).toLocaleString()}</span>
                                            </div>
                                            <div className="flex flex-wrap gap-2 mb-3">
                                                {temas.map(t => (
                                                    <span key={t} className="text-[9px] font-black uppercase px-2 py-1 rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">{t}</span>
                                                ))}
                                                <span className={`text-[9px] font-black uppercase px-2 py-1 rounded border ${consulta.estado === 'pendiente' ? 'bg-orange-500/10 text-orange-500 border-orange-500/20' : consulta.respuesta_positiva ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20'}`}>
                                                    {consulta.estado === 'pendiente' ? 'Pendiente' : consulta.respuesta_positiva ? 'Confirmada' : 'Rechazada'}
                                                </span>
                                            </div>
                                            {consulta.comentario_vendedor && (
                                                <p className="text-xs font-bold theme-text-main mb-2">Pregunta: {consulta.comentario_vendedor}</p>
                                            )}
                                            {consulta.estado === 'respondida' && (
                                                <>
                                                    {consulta.comentario_encargada && (
                                                        <p className="text-xs font-bold theme-text-main mb-2">Respuesta ({consulta.encargada?.name}): {consulta.comentario_encargada}</p>
                                                    )}
                                                    {consulta.evidencia_respuesta_path && (
                                                        <a href={`/storage/${consulta.evidencia_respuesta_path}`} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-blue-500">
                                                            <FileImage className="w-3 h-3" /> Ver evidencia de respuesta
                                                        </a>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
