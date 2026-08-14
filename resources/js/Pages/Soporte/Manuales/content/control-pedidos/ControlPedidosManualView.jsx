import React from 'react';
import { geliaCardClass } from '@/utils/geliaTheme';
import DiagramaMaestro from './DiagramaMaestro';
import EjemplosPracticos from './EjemplosPracticos';

function SectionBlock({ seccion }) {
    return (
        <section id={`sec-${seccion.id}`} className="scroll-mt-24 space-y-4">
            <div>
                <p className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-muted m-0 mb-1">
                    {seccion.cargo} · {seccion.ruta}
                </p>
                <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                    {seccion.titulo}
                </h2>
                <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">{seccion.resumen}</p>
            </div>

            <div className="space-y-3">
                <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">Pasos</h3>
                <ol className="m-0 space-y-3 list-none p-0">
                    {(seccion.pasos || []).map((p, i) => (
                        <li key={p.titulo} className={geliaCardClass('p-4 flex gap-3')}>
                            <span
                                className="text-sm font-black shrink-0 w-7 h-7 rounded-lg flex items-center justify-center"
                                style={{
                                    color: 'var(--color-primario)',
                                    backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)',
                                }}
                            >
                                {i + 1}
                            </span>
                            <div>
                                <p className="text-sm font-bold theme-text-main m-0">{p.titulo}</p>
                                <p className="text-xs theme-text-muted m-0 mt-1 leading-relaxed">{p.detalle}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>

            {(seccion.elementos || []).length > 0 && (
                <div className="space-y-2">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">
                        Elementos de la pantalla
                    </h3>
                    <div className="overflow-x-auto rounded-xl border theme-border">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="theme-element border-b theme-border">
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Elemento</th>
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Uso</th>
                                </tr>
                            </thead>
                            <tbody>
                                {seccion.elementos.map((e) => (
                                    <tr key={e.nombre} className="border-b theme-border last:border-0">
                                        <td className="px-3 py-2.5 font-bold theme-text-main align-top whitespace-nowrap">{e.nombre}</td>
                                        <td className="px-3 py-2.5 theme-text-muted leading-relaxed">{e.uso}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {(seccion.errores_que_te_llegan || []).length > 0 && (
                <div className={geliaCardClass('p-4')}>
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0 mb-2">
                        Señales / errores que te corresponden
                    </h3>
                    <ul className="m-0 pl-4 space-y-1 text-xs theme-text-muted">
                        {seccion.errores_que_te_llegan.map((e) => (
                            <li key={e}>{e}</li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

export default function ControlPedidosManualView({ contenido, seccionesMeta = [] }) {
    const overview = contenido?.overview;
    const idsPermitidos = new Set(seccionesMeta.map((s) => s.id));
    const idsList = [...idsPermitidos];

    return (
        <div className="space-y-12">
            <section id="sec-overview" className="scroll-mt-24 space-y-4">
                <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                    {overview?.titulo}
                </h2>
                {(overview?.parrafos || []).map((p) => (
                    <p key={p.slice(0, 40)} className="text-sm theme-text-muted m-0 leading-relaxed">{p}</p>
                ))}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {(overview?.escritorios || [])
                        .filter((e) => {
                            const map = {
                                Vendedora: 'vendedora',
                                Auxiliar: 'auxiliar',
                                CEDIS: 'cedis',
                                Guías: 'guias',
                                'Auxiliar / Direcciones': 'direcciones',
                            };
                            const id = map[e.cargo];
                            return !id || idsPermitidos.has(id) || idsPermitidos.size === 0;
                        })
                        .map((e) => (
                            <div key={e.ruta} className={geliaCardClass('p-4')}>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{e.cargo}</p>
                                <p className="text-sm font-bold theme-text-main m-0 mt-1">{e.nombre}</p>
                                <p className="text-[11px] theme-text-muted m-0 mt-1 font-mono">{e.ruta}</p>
                            </div>
                        ))}
                </div>
            </section>

            <section id="sec-flujo" className="scroll-mt-24">
                <DiagramaMaestro />
            </section>

            {(contenido?.secciones || []).map((s) => (
                <SectionBlock key={s.id} seccion={s} />
            ))}

            {(contenido?.flujo?.reaperturas || []).length > 0 && (
                <section id="sec-reaperturas" className="scroll-mt-24 space-y-3">
                    <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Matriz de reapertura
                    </h2>
                    <div className="overflow-x-auto rounded-xl border theme-border">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="theme-element border-b theme-border">
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Desde</th>
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Hacia</th>
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Permiso</th>
                                    <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                {contenido.flujo.reaperturas.map((r) => (
                                    <tr key={`${r.desde}-${r.hacia}`} className="border-b theme-border last:border-0">
                                        <td className="px-3 py-2.5 font-bold theme-text-main align-top">{r.desde}</td>
                                        <td className="px-3 py-2.5 theme-text-main align-top">{r.hacia}</td>
                                        <td className="px-3 py-2.5 font-mono theme-text-muted align-top">{r.permiso}</td>
                                        <td className="px-3 py-2.5 theme-text-muted align-top">{r.nota}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            <section id="sec-estatus" className="scroll-mt-24 space-y-3">
                <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                    Catálogo de estatus (fase_ciclo)
                </h2>
                <div className="overflow-x-auto rounded-xl border theme-border">
                    <table className="w-full text-left text-xs">
                        <thead>
                            <tr className="theme-element border-b theme-border">
                                <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Fase</th>
                                <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Etiqueta</th>
                                <th className="px-3 py-2 font-black uppercase tracking-wider theme-text-muted">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(contenido?.estatus || []).map((e) => (
                                <tr key={e.fase} className="border-b theme-border last:border-0">
                                    <td className="px-3 py-2.5 font-mono font-bold theme-text-main">{e.fase}</td>
                                    <td className="px-3 py-2.5 theme-text-main">{e.etiqueta}</td>
                                    <td className="px-3 py-2.5 theme-text-muted">{e.nota}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {(contenido?.errores || []).length > 0 && (
                <section id="sec-errores" className="scroll-mt-24 space-y-3">
                    <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Alertas relevantes a tu perfil
                    </h2>
                    <ul className="m-0 space-y-2 list-none p-0">
                        {contenido.errores.map((e) => (
                            <li key={e.tipo} className={geliaCardClass('p-3 flex flex-col sm:flex-row sm:gap-3')}>
                                <code className="text-[10px] font-bold shrink-0" style={{ color: 'var(--color-primario)' }}>
                                    {e.tipo}
                                </code>
                                <span className="text-xs theme-text-muted">{e.cuando}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {idsList.length > 0 && (
                <section id="sec-ejemplos" className="scroll-mt-24">
                    <EjemplosPracticos idsSecciones={idsList} />
                </section>
            )}
        </div>
    );
}
