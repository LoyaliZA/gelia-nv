import React from 'react';
import { createPortal } from 'react-dom';
import { RefreshCw, X } from 'lucide-react';
import PanelSync from './PanelSync';
import PanelWebhooks from './PanelWebhooks';

export default function ModalHerramientas({
    onClose,
    permisos,
    credencialesOk,
    procesoActivo,
    ultimosSyncs,
    syncLogId,
    onSyncStarted,
    webhookUrl,
    eventosRecomendados,
}) {
    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto relative space-y-6">
                <button type="button" onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 pr-10">
                    <RefreshCw className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-xl md:text-2xl font-black italic uppercase theme-text-main">Herramientas</h2>
                        <p className="text-xs theme-text-muted mt-1">
                            Sincronizar catálogo y gestionar webhooks.
                        </p>
                    </div>
                </div>

                <PanelSync
                    embedded
                    permisos={permisos}
                    procesoActivo={procesoActivo}
                    ultimosSyncs={ultimosSyncs}
                    syncLogId={syncLogId}
                    onSyncStarted={onSyncStarted}
                    credencialesOk={credencialesOk}
                />

                {permisos.configurar && (
                    <>
                        <div className="border-t theme-border" />
                        <PanelWebhooks
                            embedded
                            permisos={permisos}
                            credencialesOk={credencialesOk}
                            webhookUrl={webhookUrl}
                            eventosRecomendados={eventosRecomendados}
                        />
                    </>
                )}
            </div>
        </div>,
        document.body
    );
}
