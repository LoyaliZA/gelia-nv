import React from 'react';
import { History, Link2, FileImage } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { formatearFechaOperativa } from './resguardosStyles';

const badgeCategoria = (categoria) => {
    const mapa = {
        recepcion: 'bg-blue-500/10 text-blue-600 dark:text-blue-300',
        incidencia: 'bg-purple-500/10 text-purple-600 dark:text-purple-300',
        entrega: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        devolucion: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        correccion: 'bg-orange-500/10 text-orange-600 dark:text-orange-300',
        integracion: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
        sistema: 'bg-slate-500/10 theme-text-muted',
        operacion: 'bg-slate-500/10 theme-text-muted',
    };

    return mapa[categoria] || mapa.operacion;
};

export default function TimelineResguardo({ eventos = [], soloLectura = true }) {
    if (eventos.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-6 text-center`}>
                <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">
                    Sin eventos registrados
                </p>
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6`}>
            <div className="flex items-center gap-2 mb-5">
                <History className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Línea de tiempo
                </h2>
                {soloLectura && (
                    <span className="ml-auto text-[9px] font-black uppercase tracking-widest theme-text-muted">
                        Solo lectura
                    </span>
                )}
            </div>
            <ol className="space-y-4 m-0 p-0 list-none">
                {eventos.map((evento) => (
                    <li key={evento.id} className="relative pl-6 border-l-2 border-[var(--color-primario)]/25">
                        <span className="absolute left-[-5px] top-1.5 w-2 h-2 rounded-full bg-[var(--color-primario)]" />
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-black theme-text-main m-0">
                                {evento.tipo_etiqueta}
                            </p>
                            {evento.categoria && (
                                <span className={`inline-flex px-2 py-0.5 rounded-lg text-[8px] font-black uppercase ${badgeCategoria(evento.categoria)}`}>
                                    {evento.categoria}
                                </span>
                            )}
                            {evento.origen === 'integracion_cp' && (
                                <Link2 className="w-3.5 h-3.5 theme-text-muted" aria-hidden />
                            )}
                        </div>
                        <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">
                            {formatearFechaOperativa(evento.ocurrido_at)}
                            {evento.actor_referencia ? ` · ${evento.actor_referencia}` : ''}
                            {evento.bulto_folio ? ` · Bulto ${evento.bulto_folio}` : ''}
                        </p>
                        {(evento.estado_anterior_etiqueta || evento.estado_nuevo_etiqueta) && (
                            <p className="text-[10px] theme-text-muted m-0 mt-1">
                                {evento.estado_anterior_etiqueta || '—'}
                                {' → '}
                                {evento.estado_nuevo_etiqueta || '—'}
                            </p>
                        )}
                        {evento.metadata_legible?.length > 0 && (
                            <dl className="mt-2 space-y-1">
                                {evento.metadata_legible.map((campo) => (
                                    <div key={`${evento.id}-${campo.clave}`} className="grid grid-cols-1 sm:grid-cols-[minmax(0,140px)_1fr] gap-1">
                                        <dt className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                            {campo.etiqueta}
                                        </dt>
                                        <dd className="text-xs theme-text-main font-semibold m-0 break-words">
                                            {campo.valor}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        {evento.evidencias?.length > 0 && (
                            <ul className="mt-2 space-y-1 m-0 p-0 list-none">
                                {evento.evidencias.map((evidencia) => (
                                    <li key={evidencia.id}>
                                        {evidencia.ruta_publica ? (
                                            <a
                                                href={evidencia.ruta_publica}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-500 hover:text-blue-600"
                                            >
                                                <FileImage className="w-3 h-3" />
                                                {evidencia.nombre_original || 'Ver evidencia'}
                                            </a>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest theme-text-muted">
                                                <FileImage className="w-3 h-3" />
                                                {evidencia.tipo === 'firma' ? 'Firma capturada' : (evidencia.nombre_original || 'Evidencia')}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
}
