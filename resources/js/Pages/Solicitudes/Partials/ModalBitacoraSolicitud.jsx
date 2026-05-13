import React from 'react';
import { createPortal } from 'react-dom';
import { X, History, ShieldCheck, CheckCircle2, FileImage, Camera } from 'lucide-react';

export default function ModalBitacoraSolicitud({ onClose, solicitud }) {
    if (!solicitud) return null;

    // FILTRO DE RUIDO: Eliminamos los registros automáticos duplicados
    const auditoriasLimpias = solicitud?.auditorias?.filter(
        (r) => !r.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE')
    ) || [];

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-6xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh]" onClick={e => e.stopPropagation()}>
                
                {/* BOTÓN CERRAR */}
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none hover:scale-110 z-10">
                    <X className="w-5 h-5" />
                </button>

                {/* CABECERA */}
                <div className="flex items-center gap-4 mb-8 shrink-0 border-b theme-border pb-6">
                    <History className="w-10 h-10 text-purple-500 drop-shadow-sm" />
                    <div>
                        <h2 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">Expediente de Auditoría_</h2>
                        <p className="text-xs font-bold theme-text-muted uppercase tracking-widest mt-1">Folio: FOL-{solicitud?.id}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 flex-1 overflow-hidden">
                    
                    {/* COLUMNA IZQUIERDA: ESTADO ACTUAL */}
                    <div className="theme-element border theme-border rounded-3xl p-8 overflow-y-auto custom-scrollbar">
                        <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-6">Estado Actual de la Solicitud</h3>
                        <div className="space-y-6">
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cliente</p><p className="text-base font-black theme-text-main">{solicitud?.cliente?.nombre}</p></div>
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Proceso Solicitado</p><p className="text-base font-black theme-text-main">{solicitud?.proceso?.nombre}</p></div>
                            <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cotización Final</p><p className="text-base font-black theme-text-main">${solicitud?.monto_cotizado}</p></div>
                            <div>
                                <p className="text-[10px] font-bold theme-text-muted uppercase mb-3">Evidencia Vigente (Vendedora)</p>
                                {solicitud?.evidencia_path ? (
                                    <a href={`/storage/${solicitud.evidencia_path}`} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-2xl border theme-border hover:ring-2 transition-all h-56">
                                        <img src={`/storage/${solicitud.evidencia_path}`} className="w-full h-full object-cover hover:scale-105 transition-transform" />
                                    </a>
                                ) : (<p className="text-sm font-bold theme-text-muted italic">Sin evidencia adjunta.</p>)}
                            </div>
                        </div>
                    </div>

                    {/* COLUMNA DERECHA: TIMELINE DE VERSIONES */}
                    <div className="overflow-y-auto custom-scrollbar relative px-6 py-4 before:absolute before:inset-0 before:ml-6 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-purple-300 dark:before:via-purple-900/50 before:to-transparent">
                        <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-8 ml-8 relative z-10 theme-surface inline-block pr-4">Línea de Tiempo Operativa</h3>
                        
                        <div className="space-y-8">
                            {auditoriasLimpias.length === 0 && (
                                <p className="ml-10 text-xs font-bold theme-text-muted italic">No hay registros de auditoría disponibles.</p>
                            )}

                            {auditoriasLimpias.map((registro, idx) => {
                                // Determinamos si este registro fue una acción de Encargada/Verificador
                                const estadoNombre = registro.estado_nuevo?.nombre;
                                const esRespuesta = estadoNombre === 'Respondida' || estadoNombre === 'Verificada' || estadoNombre === 'Incorrecta';
                                
                                // Parseamos el snapshot si viene como string desde la base de datos
                                const snapshot = typeof registro.datos_snapshot === 'string' ? JSON.parse(registro.datos_snapshot) : registro.datos_snapshot;

                                return (
                                    <div key={idx} className="relative flex flex-col ml-10">
                                        
                                        {/* ICONO DEL TIMELINE */}
                                        <div className={`absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface flex items-center justify-center shadow-md z-10 ${esRespuesta ? 'bg-emerald-500 text-white' : 'bg-purple-500 text-white'}`}>
                                            {esRespuesta ? <CheckCircle2 className="w-4 h-4" /> : <ShieldCheck className="w-4 h-4" />}
                                        </div>
                                        
                                        {/* TARJETA DEL REGISTRO */}
                                        <div className={`theme-element border ${esRespuesta ? 'border-emerald-500/30' : 'theme-border'} p-6 rounded-3xl shadow-sm`}>
                                            <div className="flex justify-between items-center mb-3">
                                                <span className={`font-black text-xs uppercase tracking-widest ${esRespuesta ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`}>
                                                    {registro.usuario?.name}
                                                </span>
                                                <span className="text-[10px] font-bold theme-text-muted">{new Date(registro.created_at).toLocaleString()}</span>
                                            </div>
                                            
                                            <p className="text-sm font-black theme-text-main mb-3">
                                                Estado: <span className="italic">{estadoNombre || 'Actualización / Corrección'}</span>
                                            </p>

                                            {/* BLOQUE DE VERSIÓN (SNAPSHOT DE LA VENDEDORA) */}
                                            {snapshot && !esRespuesta && (
                                                <div className="mb-4 p-4 bg-black/5 dark:bg-white/5 rounded-2xl border theme-border flex flex-col gap-3">
                                                    <div className="flex justify-between items-center">
                                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización Proyectada</p>
                                                        <span className="text-xs font-black theme-text-main">${snapshot.monto_cotizado}</span>
                                                    </div>
                                                    {snapshot.evidencia_path && (
                                                        <a href={`/storage/${snapshot.evidencia_path}`} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-blue-500 hover:text-blue-600 transition-colors w-fit">
                                                            <FileImage className="w-3 h-3" /> Ver Evidencia Adjunta
                                                        </a>
                                                    )}
                                                </div>
                                            )}

                                            {/* NOTA O MOTIVO */}
                                            {registro.motivo_reporte && (
                                                <div className="p-4 theme-surface rounded-2xl border theme-border">
                                                    <p className="text-xs font-bold theme-text-main m-0 uppercase tracking-widest leading-relaxed">
                                                        Nota: {registro.motivo_reporte}
                                                    </p>
                                                </div>
                                            )}

                                            {/* EVIDENCIA OFICIAL DE LA ENCARGADA (Leída desde el Snapshot) */}
                                            {esRespuesta && snapshot?.evidencia_respuesta_path && (
                                                <div className="mt-6 pt-4 border-t border-emerald-500/20">
                                                    <h4 className="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                        <Camera className="w-3 h-3" /> Evidencia de Resolución
                                                    </h4>
                                                    <a href={`/storage/${snapshot.evidencia_respuesta_path}`} target="_blank" rel="noreferrer" className="block rounded-2xl overflow-hidden border border-emerald-500/30 h-32 relative group">
                                                        <img src={`/storage/${snapshot.evidencia_respuesta_path}`} className="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                                        <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-bold uppercase tracking-widest backdrop-blur-sm">Ver Completa</div>
                                                    </a>
                                                </div>
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