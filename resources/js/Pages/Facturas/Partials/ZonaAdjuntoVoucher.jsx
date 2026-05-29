import React, { useCallback, useState } from 'react';
import { Upload, Trash2, Image as ImageIcon, FileText } from 'lucide-react';
import { compressImageToWebp, validateImageSource } from '../../../utils/compressImage';

const MAX_VOUCHERS = 5;

export default function ZonaAdjuntoVoucher({ vouchers, onChange, error }) {
    const [previews, setPreviews] = useState({});

    const agregarArchivos = useCallback(async (files) => {
        const lista = Array.from(files || []);
        if (!lista.length) return;

        const actuales = [...vouchers];
        const nuevosPreviews = { ...previews };

        for (const file of lista) {
            if (actuales.length >= MAX_VOUCHERS) break;

            if (file.type.startsWith('image/')) {
                const err = validateImageSource(file, 'Voucher');
                if (err) continue;
                try {
                    const comprimido = await compressImageToWebp(file);
                    const idx = actuales.length;
                    actuales.push(comprimido);
                    nuevosPreviews[idx] = URL.createObjectURL(comprimido);
                } catch {
                    continue;
                }
            } else if (file.type === 'application/pdf') {
                actuales.push(file);
            }
        }

        setPreviews(nuevosPreviews);
        onChange(actuales);
    }, [vouchers, previews, onChange]);

    const handlePaste = useCallback(async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;

        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = item.getAsFile();
                if (file) await agregarArchivos([file]);
                break;
            }
        }
    }, [agregarArchivos]);

    const quitar = (index) => {
        const next = vouchers.filter((_, i) => i !== index);
        if (previews[index]) URL.revokeObjectURL(previews[index]);
        const nextPreviews = {};
        next.forEach((v, i) => {
            const oldIdx = vouchers.indexOf(v);
            if (previews[oldIdx]) nextPreviews[i] = previews[oldIdx];
        });
        setPreviews(nextPreviews);
        onChange(next);
    };

    return (
        <div className="space-y-2" onPaste={handlePaste}>
            <div className="flex items-center justify-between ml-1">
                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">
                    Comprobante de pago (voucher) — obligatorio_
                </label>
                <span className="text-[9px] font-black theme-text-muted">{vouchers.length}/{MAX_VOUCHERS}</span>
            </div>

            <div
                tabIndex={0}
                className="border-2 border-dashed rounded-2xl p-5 space-y-3 outline-none focus:border-[var(--color-primario)] transition-colors"
                style={{ borderColor: error ? '#ef4444' : 'var(--border-color, rgba(128,128,128,0.3))' }}
            >
                <p className="text-[10px] font-bold theme-text-muted text-center italic">
                    Pegue captura con Ctrl+V o seleccione imagen/PDF
                </p>

                {vouchers.map((v, i) => (
                    <div key={i} className="flex items-center gap-3 p-2 rounded-xl theme-element border theme-border">
                        {previews[i] ? (
                            <img src={previews[i]} alt="" className="w-12 h-12 object-cover rounded-lg border theme-border" />
                        ) : v.type === 'application/pdf' ? (
                            <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        ) : (
                            <ImageIcon className="w-8 h-8 theme-text-muted shrink-0" />
                        )}
                        <span className="text-[10px] font-bold theme-text-main truncate flex-1">{v.name}</span>
                        <button type="button" onClick={() => quitar(i)} className="p-1 text-red-500 hover:bg-red-500/10 rounded-lg outline-none">
                            <Trash2 className="w-4 h-4" />
                        </button>
                    </div>
                ))}

                {vouchers.length < MAX_VOUCHERS && (
                    <label className="flex flex-col items-center justify-center py-4 cursor-pointer rounded-xl border border-dashed theme-border hover:border-[var(--color-primario)] transition-colors">
                        <Upload className="w-6 h-6 mb-2 theme-text-muted" />
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Agregar voucher</span>
                        <input
                            type="file"
                            className="hidden"
                            accept="image/*,.pdf,application/pdf"
                            multiple
                            onChange={e => { agregarArchivos(e.target.files); e.target.value = ''; }}
                        />
                    </label>
                )}
            </div>

            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}
