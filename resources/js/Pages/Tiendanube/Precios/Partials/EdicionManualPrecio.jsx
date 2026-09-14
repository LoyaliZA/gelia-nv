import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

export default function EdicionManualPrecio({ item, destino, onGuardar, onCancelar, busy }) {
    const actual = item?.campos?.[destino]?.propuesto ?? item?.campos?.[destino]?.actual ?? '';
    const [valor, setValor] = useState(actual ?? '');
    const [motivo, setMotivo] = useState('');

    return (
        <form
            className="mt-2 space-y-2 rounded-lg border theme-border p-2"
            onSubmit={(e) => {
                e.preventDefault();
                onGuardar({ destino, valor, motivo });
            }}
        >
            <label className="block text-xs theme-text-main">
                Nuevo {destino}
                <input
                    type="text"
                    inputMode="decimal"
                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1 text-sm"
                    value={valor}
                    onChange={(e) => setValor(e.target.value)}
                    autoFocus
                />
            </label>
            <label className="block text-xs theme-text-main">
                Motivo
                <input
                    type="text"
                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1 text-sm"
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    required
                />
            </label>
            <div className="flex flex-wrap gap-2">
                <button
                    type="submit"
                    disabled={busy}
                    className="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest text-white"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    Guardar ajuste
                </button>
                <button type="button" onClick={onCancelar} className={GELIA_BTN_OUTLINE}>
                    Cancelar
                </button>
            </div>
        </form>
    );
}

export function enlaceCorregirCosto(varianteId) {
    return route('tiendanube.precios.fuentes', { variante_id: varianteId });
}

export function irACorregirCosto(varianteId) {
    router.visit(route('tiendanube.precios.fuentes', { variante_id: varianteId }));
}
