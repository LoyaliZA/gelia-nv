import React from 'react';
import { Download, BookOpen, Terminal, AlertTriangle, Send } from 'lucide-react';

export default function TabDocumentacion({ documentacion, baseUrl }) {
    const endpointsGenerales = documentacion.endpoints_generales || [];
    const instrucciones = documentacion.instrucciones || [];
    const guiasClienteHttp = documentacion.guias_cliente_http || [];
    const recursos = documentacion.recursos || [];
    const codigosError = documentacion.codigos_error || [];

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap gap-3">
                <a
                    href={route('admin.api_externa.documentacion.pdf')}
                    className="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-white text-xs font-black uppercase tracking-widest"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Download className="w-4 h-4" /> Descargar PDF
                </a>
            </div>

            <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 space-y-2">
                <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-bold text-sm">
                    <AlertTriangle className="w-4 h-4" />
                    Si recibe HTML de login en lugar de JSON
                </div>
                <p className="text-sm theme-text-muted">
                    Use siempre la URL <code>{baseUrl}</code> (con <code>/v1</code>) y el encabezado{' '}
                    <code>Accept: application/json</code> en cada petición, incluidas las protegidas con token.
                </p>
            </div>

            <div className="rounded-2xl theme-border border p-5 space-y-4">
                <h4 className="font-black uppercase flex items-center gap-2 text-sm">
                    <BookOpen className="w-4 h-4" /> Guía de integración
                </h4>
                <p className="text-sm theme-text-muted">URL base: <code>{baseUrl}</code></p>

                <ol className="space-y-2 text-sm list-decimal list-inside">
                    <li>Pruebe disponibilidad: <code>GET {baseUrl}/health</code> (sin autenticación).</li>
                    <li>Obtenga token: <code>POST {baseUrl}/auth/token</code> con <code>client_id</code> y <code>client_secret</code>.</li>
                    <li>Use el token: <code>Authorization: Bearer {'{access_token}'}</code></li>
                    <li>Siempre envíe: <code>Accept: application/json</code> y <code>Content-Type: application/json</code> en POST/PUT.</li>
                </ol>

                {instrucciones.length > 0 && (
                    <ul className="text-xs theme-text-muted space-y-1 list-disc list-inside border-t theme-border pt-4">
                        {instrucciones.map((texto) => (
                            <li key={texto}>{texto}</li>
                        ))}
                    </ul>
                )}
            </div>

            {guiasClienteHttp.length > 0 && (
                <div className="rounded-2xl theme-border border p-5 space-y-6">
                    <h4 className="font-black uppercase flex items-center gap-2 text-sm">
                        <Send className="w-4 h-4" /> Probar con Postman o Thunder Client
                    </h4>
                    <p className="text-xs theme-text-muted">
                        URL base para configurar en ambas herramientas: <code>{baseUrl}</code>
                    </p>
                    {guiasClienteHttp.map((guia) => (
                        <div key={guia.nombre} className="space-y-2 border-t theme-border pt-4 first:border-0 first:pt-0">
                            <h5 className="font-bold uppercase text-xs">{guia.nombre}</h5>
                            <ol className="text-xs theme-text-muted space-y-1.5 list-decimal list-inside">
                                {(guia.pasos || []).map((paso) => (
                                    <li key={paso} className={paso.startsWith('{') ? 'list-none ml-0' : ''}>
                                        {paso.startsWith('{') ? (
                                            <pre className="mt-1 font-mono p-3 rounded-xl bg-black/5 dark:bg-white/5 overflow-x-auto whitespace-pre-wrap">{paso}</pre>
                                        ) : (
                                            paso
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    ))}
                </div>
            )}

            <div className="rounded-2xl theme-border border p-5 space-y-4">
                <h4 className="font-black uppercase flex items-center gap-2 text-sm">
                    <Terminal className="w-4 h-4" /> Rutas para probar
                </h4>

                {endpointsGenerales.map((ep) => (
                    <div key={`${ep.metodo}-${ep.ruta}`} className="space-y-2 pb-4 border-b theme-border last:border-0">
                        <p className="text-xs font-bold uppercase">
                            <span className="text-green-600">{ep.metodo}</span> {ep.ruta}
                            {ep.auth === false && <span className="ml-2 text-[10px] theme-text-muted">(pública)</span>}
                        </p>
                        <p className="text-xs theme-text-muted">{ep.descripcion}</p>
                        {ep.curl && (
                            <pre className="text-[10px] font-mono p-3 rounded-xl bg-black/5 dark:bg-white/5 overflow-x-auto whitespace-pre-wrap">{ep.curl}</pre>
                        )}
                    </div>
                ))}

                {recursos.map((recurso) => (
                    <div key={recurso.slug} className="pt-4 border-t theme-border space-y-3">
                        <h5 className="font-bold uppercase text-xs">{recurso.nombre}</h5>
                        <p className="text-xs theme-text-muted">
                            Lectura: {recurso.lectura_habilitada ? 'Sí' : 'No'} · Escritura: {recurso.escritura_habilitada ? 'Sí' : 'No'}
                        </p>
                        {(recurso.endpoints || []).map((ep) => (
                            <div key={`${ep.metodo}-${ep.ruta}`} className="space-y-1">
                                <p className="text-xs font-mono">
                                    <strong>{ep.metodo}</strong> {ep.ruta} — {ep.descripcion}
                                </p>
                                {ep.curl && (
                                    <pre className="text-[10px] font-mono p-3 rounded-xl bg-black/5 dark:bg-white/5 overflow-x-auto whitespace-pre-wrap">{ep.curl}</pre>
                                )}
                            </div>
                        ))}
                    </div>
                ))}
            </div>

            {codigosError.length > 0 && (
                <div className="rounded-2xl theme-border border p-5">
                    <h4 className="font-black uppercase text-sm mb-3">Códigos de respuesta</h4>
                    <div className="grid sm:grid-cols-2 gap-2 text-xs">
                        {codigosError.map((err) => (
                            <p key={err.codigo}><strong>{err.codigo}</strong> — {err.descripcion}</p>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
