import React from 'react';
import { Download, BookOpen, Terminal, AlertTriangle, Send, Smartphone } from 'lucide-react';

export default function TabDocumentacion({ documentacion, baseUrl }) {
    const endpointsGenerales = documentacion.endpoints_generales || [];
    const instrucciones = documentacion.instrucciones || [];
    const guiasClienteHttp = documentacion.guias_cliente_http || [];
    const recursos = documentacion.recursos || [];
    const codigosError = documentacion.codigos_error || [];
    const mobile = documentacion.mobile || null;

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

            {mobile && (
                <div className="rounded-2xl theme-border border p-5 space-y-6">
                    <h4 className="font-black uppercase flex items-center gap-2 text-sm">
                        <Smartphone className="w-4 h-4" /> API móvil (apps nativas)
                    </h4>
                    <p className="text-sm theme-text-muted">{mobile.introduccion}</p>

                    {(mobile.requisitos_acceso || []).length > 0 && (
                        <div className="space-y-2">
                            <h5 className="font-bold uppercase text-xs">Requisitos de acceso</h5>
                            <ul className="text-xs theme-text-muted space-y-1 list-disc list-inside">
                                {mobile.requisitos_acceso.map((texto) => (
                                    <li key={texto}>{texto}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="space-y-2 border-t theme-border pt-4">
                        <h5 className="font-bold uppercase text-xs">Autenticación</h5>
                        <p className="text-xs theme-text-muted">
                            Los tokens de <code>/auth/token</code> no funcionan en <code>/mobile/*</code>.
                            Use login de usuario con <code>device_uuid</code>.
                        </p>
                        <pre className="text-[10px] font-mono p-3 rounded-xl bg-black/5 dark:bg-white/5 overflow-x-auto whitespace-pre-wrap">
{`POST ${baseUrl}/mobile/login
Content-Type: application/json
Accept: application/json

{
  "login": "usuario@ejemplo.com",
  "password": "********",
  "device_uuid": "11111111-1111-1111-1111-111111111111",
  "platform": "android",
  "app_version": "1.0.0"
}`}
                        </pre>
                        <p className="text-xs theme-text-muted">
                            Encabezados en sincronización: <code>Authorization: Bearer</code>,{' '}
                            <code>Accept: application/json</code>, <code>X-Mobile-Scope-Version</code>
                        </p>
                    </div>

                    {(mobile.instrucciones || []).length > 0 && (
                        <div className="space-y-2 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Sincronización</h5>
                            <ol className="text-xs theme-text-muted space-y-1 list-decimal list-inside">
                                {mobile.instrucciones.map((texto) => (
                                    <li key={texto}>{texto}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    {(mobile.flujo_sincronizacion || []).length > 0 && (
                        <div className="space-y-2 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Flujo recomendado</h5>
                            <ol className="text-xs theme-text-muted space-y-1 list-decimal list-inside">
                                {mobile.flujo_sincronizacion.map((texto) => (
                                    <li key={texto}>{texto}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    {(mobile.endpoints || []).length > 0 && (
                        <div className="space-y-4 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Endpoints</h5>
                            {mobile.endpoints.map((ep) => (
                                <div key={`mobile-${ep.metodo}-${ep.ruta}`} className="space-y-2 pb-4 border-b theme-border last:border-0">
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
                        </div>
                    )}

                    {(mobile.campos || []).length > 0 && (
                        <div className="space-y-3 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Campos de cliente</h5>
                            <p className="text-xs theme-text-muted">
                                Cada registro incluye <code>alcance</code> (<code>full</code>, <code>vendedor</code> o <code>none</code>).
                            </p>
                            {mobile.campos.map((grupo) => (
                                <div key={grupo.grupo} className="text-xs theme-text-muted">
                                    <p className="font-bold">Grupo {grupo.grupo} — {grupo.permisos_requeridos}</p>
                                    <p className="font-mono text-[10px] break-all">{grupo.campos.join(', ')}</p>
                                </div>
                            ))}
                        </div>
                    )}

                    {(mobile.eventos_cambio || []).length > 0 && (
                        <div className="space-y-2 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Eventos de cambio</h5>
                            <ul className="text-xs theme-text-muted space-y-1 list-disc list-inside">
                                {mobile.eventos_cambio.map((texto) => (
                                    <li key={texto}>{texto}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {mobile.evento_realtime && (
                        <div className="space-y-1 border-t theme-border pt-4 text-xs theme-text-muted">
                            <h5 className="font-bold uppercase text-xs">Tiempo real (Reverb)</h5>
                            <p>Canal: <code>{mobile.evento_realtime.canal}</code></p>
                            <p>Evento: <code>{mobile.evento_realtime.nombre}</code></p>
                            <p>Payload: <code>{mobile.evento_realtime.payload}</code></p>
                        </div>
                    )}

                    {(mobile.limites || []).length > 0 && (
                        <div className="space-y-2 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Límites</h5>
                            <ul className="text-xs theme-text-muted space-y-1 list-disc list-inside">
                                {mobile.limites.map((texto) => (
                                    <li key={texto}>{texto}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {(mobile.guias_cliente_http || []).length > 0 && (
                        <div className="space-y-4 border-t theme-border pt-4">
                            <h5 className="font-bold uppercase text-xs">Probar en Postman / Thunder</h5>
                            {mobile.guias_cliente_http.map((guia) => (
                                <div key={guia.nombre} className="space-y-2">
                                    <p className="font-bold text-xs">{guia.nombre}</p>
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
                </div>
            )}
        </div>
    );
}
