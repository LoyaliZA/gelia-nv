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
    const [convertirWebp, setConvertirWebp] = useState(true);
    const [ajustar1280, setAjustar1280] = useState(true);
    const [modo1280, setModo1280] = useState('square');
    const modoEnvio = ajustar1280 ? modo1280 : 'none';

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

            <div className="p-3 rounded-xl border theme-border space-y-2">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Al subir</p>
                <div className="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-center">
                    <label className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={convertirWebp}
                            onChange={(e) => setConvertirWebp(e.target.checked)}
                            className="rounded border theme-border"
                        />
                        Convertir a WebP
                    </label>
                    <label className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={ajustar1280}
                            onChange={(e) => setAjustar1280(e.target.checked)}
                            className="rounded border theme-border"
                        />
                        Ajustar a 1280
                    </label>
                    <label className={`inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest cursor-pointer select-none ${ajustar1280 ? 'theme-text-muted' : 'opacity-40 pointer-events-none'}`}>
                        <input
                            type="radio"
                            name="tn-modo-1280"
                            checked={modo1280 === 'fit'}
                            disabled={!ajustar1280}
                            onChange={() => setModo1280('fit')}
                            className="border theme-border"
                        />
                        Fit 1280
                    </label>
                    <label className={`inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest cursor-pointer select-none ${ajustar1280 ? 'theme-text-muted' : 'opacity-40 pointer-events-none'}`}>
                        <input
                            type="radio"
                            name="tn-modo-1280"
                            checked={modo1280 === 'square'}
                            disabled={!ajustar1280}
                            onChange={() => setModo1280('square')}
                            className="border theme-border"
                        />
                        Cuadrado 1280×1280
                    </label>
                </div>
                <p className="text-[10px] theme-text-muted">
                    Fit: lado mayor ≤ 1280, sin recortar. Cuadrado: recorte centrado a 1280×1280. GIF no se convierte.
                </p>
            </div>

            {tab === 'individual' ? (
                <PanelVincularImagenes
                    permisos={permisos}
                    credencialesOk={credencialesOk}
                    onChanged={onChanged}
                    onImportStarted={onImportStarted}
                    convertirWebp={convertirWebp}
                    modo1280={modoEnvio}
                />
            ) : (
                <PanelImportImagenes
                    embedded
                    permisos={permisos}
                    credencialesOk={credencialesOk}
                    imageImportActivo={imageImportActivo}
                    ultimosImportImagenes={ultimosImportImagenes}
                    onImportStarted={onImportStarted}
                    convertirWebp={convertirWebp}
                    modo1280={modoEnvio}
                />
            )}
        </div>
    );
}
