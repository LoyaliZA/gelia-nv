import React, { useState } from 'react';
import { CheckCircle2, Loader2, ShieldCheck } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import {
    badgeEstadoIncidencia,
    BTN_SECONDARY,
    formatearFechaOperativa,
    THEME_INPUT,
} from './resguardosStyles';
import {
    admiteResolucionIncidencia,
    incidenciaEstaResuelta,
    puedeResolverIncidencia,
} from './incidenciasResguardoUtils';

export default function DetalleIncidenciaResguardo({
    incidencia,
    catalogos = {},
    permisos = {},
    enviandoResolucion = false,
    errorResolucion = null,
    onResolver,
}) {
    const [motivoResolucion, setMotivoResolucion] = useState('');
    const [confirmar, setConfirmar] = useState(false);
    const [errorLocal, setErrorLocal] = useState(null);

    const puedeResolver = puedeResolverIncidencia(permisos, incidencia);
    const resuelta = incidenciaEstaResuelta(incidencia);
    const estadoEtiqueta = incidencia.estado_etiqueta
        || catalogos.estados_incidencia?.[incidencia.estado]
        || incidencia.estado;

    const solicitarResolucion = (e) => {
        e.preventDefault();
        setErrorLocal(null);
        setConfirmar(true);
    };

    const confirmarResolucion = async () => {
        setConfirmar(false);
        const resultado = await onResolver(incidencia, motivoResolucion);

        if (resultado?.validacion) {
            setErrorLocal(Object.values(resultado.validacion)[0]);
            return;
        }

        if (resultado?.ok) {
            setMotivoResolucion('');
            setErrorLocal(null);
        }
    };

    const errorVisible = errorResolucion || errorLocal;

    return (
        <article className={`${geliaCardClass()} p-4 md:p-5 space-y-4`}>
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1 min-w-0">
                    <h3 className="text-sm font-black theme-text-main m-0">
                        {incidencia.tipo_etiqueta || catalogos.tipos_incidencia?.[incidencia.tipo]}
                    </h3>
                    <p className="text-[10px] theme-text-muted m-0">
                        Reportado {formatearFechaOperativa(incidencia.reportado_at)}
                        {incidencia.reportado_por_referencia ? ` · ${incidencia.reportado_por_referencia}` : ''}
                    </p>
                </div>
                <span className={`inline-flex px-2.5 py-1 rounded-lg text-[9px] font-black uppercase ${badgeEstadoIncidencia(incidencia.estado)}`}>
                    {estadoEtiqueta}
                </span>
            </header>

            <section className="rounded-2xl border theme-border p-4 space-y-2 bg-black/[0.02] dark:bg-white/[0.02]">
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Reporte original</p>
                <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{incidencia.descripcion}</p>
                {(incidencia.evidencias || []).length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-2">
                        {incidencia.evidencias.map((evidencia) => (
                            <div
                                key={evidencia.id}
                                className="rounded-xl border theme-border p-2 text-[10px] theme-text-muted"
                            >
                                {evidencia.nombre_original || 'Evidencia'}
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {resuelta ? (
                <section className="rounded-2xl border border-emerald-500/30 p-4 space-y-2">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">Resolución</p>
                    </div>
                    <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">
                        {incidencia.motivo_resolucion || '—'}
                    </p>
                    <p className="text-[10px] theme-text-muted m-0">
                        Resuelto {formatearFechaOperativa(incidencia.autorizado_at)}
                        {incidencia.autorizado_por_referencia ? ` · ${incidencia.autorizado_por_referencia}` : ''}
                    </p>
                </section>
            ) : admiteResolucionIncidencia(incidencia) && (
                <section className="rounded-2xl border theme-border p-4 space-y-3">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Seguimiento</p>
                    <p className="text-sm theme-text-muted m-0">
                        Incidencia abierta pendiente de resolución.
                    </p>

                    {puedeResolver ? (
                        <form onSubmit={solicitarResolucion} className="space-y-3">
                            <label className="space-y-1.5 block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                    Motivo de resolución
                                </span>
                                <textarea
                                    value={motivoResolucion}
                                    onChange={(e) => {
                                        setMotivoResolucion(e.target.value);
                                        setErrorLocal(null);
                                    }}
                                    className={`${THEME_INPUT} min-h-[80px] resize-y`}
                                    placeholder="Indica la decisión o autorización…"
                                    required
                                    disabled={enviandoResolucion}
                                />
                            </label>
                            {errorVisible && (
                                <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{errorVisible}</p>
                            )}
                            <button
                                type="submit"
                                className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                                disabled={enviandoResolucion}
                            >
                                {enviandoResolucion
                                    ? <Loader2 className="w-4 h-4 animate-spin" />
                                    : <ShieldCheck className="w-4 h-4" />}
                                Resolver incidencia
                            </button>
                        </form>
                    ) : (
                        <p className="text-[10px] theme-text-muted font-bold m-0 flex items-center gap-2">
                            <ShieldCheck className="w-4 h-4 shrink-0" />
                            Sin permiso para resolver esta incidencia.
                        </p>
                    )}
                </section>
            )}

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar resolución"
                mensaje="La resolución quedará registrada de forma permanente. El reporte original no se modificará."
                etiquetaConfirmar="Sí, resolver incidencia"
                onConfirmar={confirmarResolucion}
                onCancelar={() => setConfirmar(false)}
            />
        </article>
    );
}
