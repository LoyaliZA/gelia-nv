import React from 'react';
import { Download, BookOpen } from 'lucide-react';

export default function TabDocumentacion({ documentacion, baseUrl }) {
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

            <div className="rounded-2xl theme-border border p-5 space-y-4">
                <h4 className="font-black uppercase flex items-center gap-2 text-sm">
                    <BookOpen className="w-4 h-4" /> Resumen de integración
                </h4>
                <p className="text-sm theme-text-muted">URL base: <code>{baseUrl}</code></p>

                <div className="space-y-2 text-sm">
                    <p><strong>1.</strong> Obtenga token: <code>POST {baseUrl}/auth/token</code></p>
                    <p><strong>2.</strong> Use header: <code>Authorization: Bearer {'{token}'}</code></p>
                    <p><strong>3.</strong> Siempre envíe: <code>Accept: application/json</code></p>
                </div>

                {(documentacion.recursos || []).map((recurso) => (
                    <div key={recurso.slug} className="pt-4 border-t theme-border">
                        <h5 className="font-bold uppercase text-xs">{recurso.nombre}</h5>
                        <p className="text-xs theme-text-muted mb-2">
                            Lectura: {recurso.lectura_habilitada ? 'Sí' : 'No'} · Escritura: {recurso.escritura_habilitada ? 'Sí' : 'No'}
                        </p>
                        {(recurso.endpoints || []).map((ep) => (
                            <p key={`${ep.metodo}-${ep.ruta}`} className="text-xs font-mono">
                                {ep.metodo} {ep.ruta} — {ep.descripcion}
                            </p>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
