import React from 'react';
import { createPortal } from 'react-dom';
import { ImagePlus, X } from 'lucide-react';

export default function ModalContinuarSegundoPlano({
    open,
    importId,
    preview = null,
    onContinuarSegundoPlano,
    onSeguirAqui,
}) {
    if (!open || typeof document === 'undefined') return null;

    return createPortal(
        <div className="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-6 md:p-8 space-y-5 relative shadow-2xl">
                <button
                    type="button"
                    onClick={onSeguirAqui}
                    className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main"
                    aria-label="Cerrar"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 pr-8">
                    <ImagePlus className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0">
                            Carga en curso
                        </h2>
                        <p className="text-xs theme-text-muted mt-1 m-0">
                            Importación #{importId}. Puedes seguir en este panel o continuar en segundo plano con el widget flotante.
                        </p>
                    </div>
                </div>

                {preview && (
                    <p className="text-xs theme-text-muted m-0">
                        Preview: {preview.matched ?? 0} listos
                        {' · '}
                        {preview.sku_no_encontrado ?? 0} SKU no encontrado
                        {' · '}
                        {preview.nombre_invalido ?? 0} nombre inválido
                        {' · '}
                        {preview.total ?? 0} total
                    </p>
                )}

                <div className="flex flex-col sm:flex-row gap-2">
                    <button
                        type="button"
                        onClick={onContinuarSegundoPlano}
                        className="flex-1 px-4 py-3 rounded-xl text-[10px] font-black uppercase text-white"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Continuar en segundo plano
                    </button>
                    <button
                        type="button"
                        onClick={onSeguirAqui}
                        className="flex-1 px-4 py-3 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main"
                    >
                        Seguir aquí
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
