import React, { useState } from 'react';
import { Images } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import PanelVincularImagenes from './PanelVincularImagenes';
import PanelImportImagenes from './PanelImportImagenes';

export default function SeccionImagenes({
    permisos,
    credencialesOk,
    imageImportActivo,
    ultimosImportImagenes = [],
    onImportStarted,
    onChanged,
}) {
    const [tab, setTab] = useState('individual');

    if (!permisos?.editar) {
        return null;
    }

    return (
        <div className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                    <Images className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                    Imágenes
                </h2>
                <div className="flex gap-1 p-1 rounded-xl border theme-border">
                    {[
                        { id: 'individual', label: 'Individual' },
                        { id: 'zip', label: 'ZIP' },
                    ].map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={`px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest ${
                                tab === t.id ? 'text-white' : 'theme-text-muted'
                            }`}
                            style={tab === t.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            {tab === 'individual' ? (
                <PanelVincularImagenes
                    permisos={permisos}
                    credencialesOk={credencialesOk}
                    onChanged={onChanged}
                    onImportStarted={onImportStarted}
                />
            ) : (
                <PanelImportImagenes
                    embedded
                    permisos={permisos}
                    credencialesOk={credencialesOk}
                    imageImportActivo={imageImportActivo}
                    ultimosImportImagenes={ultimosImportImagenes}
                    onImportStarted={onImportStarted}
                />
            )}
        </div>
    );
}
