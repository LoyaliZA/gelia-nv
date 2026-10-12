import React from 'react';
import { CheckCircle2, FileText, ArrowDown, Info } from 'lucide-react';

function Section({ id, titulo, children }) {
    return (
        <section id={`sec-${id}`} className="scroll-mt-28 space-y-5">
            <h2 className="text-xl md:text-2xl font-bold tracking-tight theme-text-main m-0">{titulo}</h2>
            {children}
        </section>
    );
}

function Bullets({ items, checks = false }) {
    return (
        <ul className={`m-0 space-y-3 ${checks ? 'list-none p-0' : 'pl-5 list-disc'}`}>
            {items.map((item) => (
                <li key={item} className={checks ? 'flex items-start gap-3' : ''}>
                    {checks && <CheckCircle2 className="w-4 h-4 mt-1 shrink-0 theme-text-primario" aria-hidden />}
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}

/** Ejemplo de lectura: no monta formularios ni acciones del pedido real. */
export function PasoVisual({ visual }) {
    return (
        <figure className="m-0 min-w-0 rounded-xl border theme-border overflow-hidden theme-surface">
            <figcaption className="px-4 py-3 border-b theme-border theme-element flex items-center gap-2 text-xs font-semibold theme-text-muted">
                <FileText className="w-4 h-4 shrink-0" aria-hidden />
                Ejemplo visual · {visual.titulo}
            </figcaption>
            <div className="p-4 space-y-4">
                <dl className="m-0 grid grid-cols-1 gap-3 text-sm">
                    {visual.campos.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-xs theme-text-muted mb-1">{label}</dt>
                            <dd className="m-0 px-3 py-2 rounded-md border theme-border theme-element theme-text-main break-words">{value}</dd>
                        </div>
                    ))}
                </dl>
                <p className="m-0 inline-flex items-center gap-2 px-3 py-2 rounded-md text-xs font-semibold theme-text-main border theme-border" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, var(--theme-surface-bg))' }}>
                    {visual.accion}
                </p>
                <p className="m-0 text-xs leading-relaxed theme-text-muted">{visual.nota}</p>
            </div>
        </figure>
    );
}

function Definition({ label, children }) {
    return (
        <div>
            <dt className="font-semibold theme-text-main">{label}</dt>
            <dd className="m-0 mt-1">{children}</dd>
        </div>
    );
}

export default function RegistrarPedidosVendedorManualView({ contenido = {} }) {
    const c = contenido;
    return (
        <div className="space-y-12 md:space-y-16 text-sm md:text-base leading-relaxed theme-text-muted break-words">
            <Section id="overview" titulo="Del pedido del cliente a su seguimiento">
                <p className="m-0 text-xs theme-text-muted">Versión {c.version} · Contenido revisado el {c.actualizado}</p>
                <p className="m-0 max-w-[75ch]">{c.intro}</p>
                <p className="m-0 max-w-[75ch]">{c.alcance}</p>
                <aside className="border-l-4 pl-4 py-2 theme-text-main" style={{ borderColor: 'var(--color-primario)' }}>
                    <p className="m-0 font-semibold">Tres acciones que conviene distinguir</p>
                    <p className="m-0 mt-2 max-w-[75ch]">{c.regla}</p>
                </aside>
                <p className="m-0 flex items-start gap-2 text-xs leading-relaxed">
                    <Info className="w-4 h-4 shrink-0" aria-hidden />
                    <span>{c.visuales_nota}</span>
                </p>
            </Section>

            <Section id="preparacion" titulo="Qué tener a la mano">
                <Bullets items={c.preparacion || []} />
            </Section>

            <Section id="pasos" titulo="Paso a paso: registro habitual">
                <p className="m-0 max-w-[75ch]">Sigue las opciones que muestre tu pedido. Al final encontrarás las variantes de Tienda, apartado, resguardo y envío municipal.</p>
                <ol className="list-none m-0 p-0 space-y-8">
                    {(c.pasos || []).map((paso, i) => (
                        <li key={paso.titulo} className="border-t theme-border pt-6">
                            <div className="flex items-start gap-3 mb-5">
                                <span className="w-9 h-9 rounded-full theme-element border theme-border theme-text-primario inline-flex items-center justify-center shrink-0 font-bold" aria-hidden>{i + 1}</span>
                                <div className="min-w-0">
                                    <h3 className="m-0 text-base md:text-lg font-bold theme-text-main">{i + 1}. {paso.titulo}</h3>
                                    <p className="m-0 mt-1 text-xs theme-text-muted">{paso.donde}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] gap-5 lg:gap-7 items-start">
                                <div className="space-y-5 min-w-0">
                                    <Bullets items={paso.acciones} />
                                    <div className="pl-4 border-l-2 theme-border">
                                        <p className="m-0 font-semibold theme-text-main text-sm">Qué debe pasar</p>
                                        <p className="m-0 mt-1 text-sm">{paso.resultado}</p>
                                    </div>
                                </div>
                                <PasoVisual visual={paso.visual} />
                            </div>
                        </li>
                    ))}
                </ol>
            </Section>

            <Section id="casos" titulo="Casos de uso: elige tu situación">
                <div className="space-y-3">
                    {(c.casos || []).map((caso) => (
                        <details key={caso.titulo} className="rounded-xl border theme-border p-4 md:p-5">
                            <summary className="cursor-pointer font-semibold theme-text-main focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primario)]">{caso.titulo}</summary>
                            <p className="m-0 mt-4">{caso.ejemplo}</p>
                            <dl className="m-0 mt-4 space-y-4">
                                <Definition label="Qué haces">{caso.hacer}</Definition>
                                <Definition label="Qué respuesta esperas">{caso.esperar}</Definition>
                            </dl>
                        </details>
                    ))}
                </div>
            </Section>

            <Section id="respuestas" titulo="Qué esperar de las otras áreas">
                <p className="m-0 max-w-[75ch]">Cada área trabaja sobre el mismo pedido. Comparte el folio y mantén datos y archivos vigentes para que la siguiente persona pueda continuar.</p>
                <ol className="list-none p-0 m-0 space-y-5">
                    {(c.respuestas || []).map((respuesta) => (
                        <li key={respuesta.area} className="border theme-border rounded-xl p-4 md:p-5">
                            <h3 className="m-0 mb-4 font-bold text-base theme-text-main">{respuesta.area}</h3>
                            <dl className="m-0 grid grid-cols-1 lg:grid-cols-3 gap-4 text-sm">
                                <Definition label="Tú registras">{respuesta.envias}</Definition>
                                <Definition label="El área responde">{respuesta.recibes}</Definition>
                                <Definition label="Tu siguiente paso">{respuesta.sigues}</Definition>
                            </dl>
                        </li>
                    ))}
                </ol>
                <p className="m-0 text-sm">Estos son los puntos de conexión con los próximos manuales. Los tiempos dependen de la operación y de los pendientes de cada pedido.</p>
            </Section>

            <Section id="estatus" titulo="Cómo saber en qué va mi pedido">
                <p className="m-0">Revisa el estado y los avisos en el listado o en Ver. La Bitácora explica quién registró los cambios.</p>
                <dl className="m-0 divide-y theme-border">
                    {(c.estatus || []).map((estado) => (
                        <div key={estado.nombre} className="py-4 grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-2 md:gap-5">
                            <dt className="font-semibold theme-text-main">{estado.nombre}</dt>
                            <dd className="m-0 space-y-2">
                                <p className="m-0">{estado.significado}</p>
                                <p className="m-0 text-sm theme-text-main">{estado.accion}</p>
                            </dd>
                        </div>
                    ))}
                </dl>
            </Section>

            <Section id="problemas" titulo="Si algo se detiene">
                <div className="space-y-5">
                    {(c.problemas || []).map((problema) => (
                        <div key={problema.situacion} className="border-b theme-border pb-5">
                            <h3 className="m-0 font-semibold theme-text-main text-base">{problema.situacion}</h3>
                            <p className="m-0 mt-2 max-w-[75ch]">{problema.hacer}</p>
                        </div>
                    ))}
                </div>
            </Section>

            <Section id="checklist" titulo="Antes de enviar: una última revisión">
                <Bullets items={c.checklist || []} checks />
                <div className="pt-6 space-y-4">
                    <h3 className="m-0 text-lg font-bold theme-text-main">Recomendaciones para el día a día</h3>
                    <Bullets items={c.recomendaciones || []} />
                </div>
                <div className="border-t theme-border pt-5 flex items-start gap-3 text-sm theme-text-main">
                    <ArrowDown className="w-4 h-4 shrink-0 mt-1" aria-hidden />
                    <p className="m-0">La descarga PDF incluye los casos completos y los ejemplos visuales, para que puedas consultarlos sin conexión o imprimirlos.</p>
                </div>
            </Section>

            <Section id="glosario" titulo="Palabras que verás en pantalla">
                <dl className="m-0 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5 text-sm">
                    {(c.glosario || []).map((item) => <Definition key={item.palabra} label={item.palabra}>{item.significado}</Definition>)}
                </dl>
            </Section>
        </div>
    );
}
