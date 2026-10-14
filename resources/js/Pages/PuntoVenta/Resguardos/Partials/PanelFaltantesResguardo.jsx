import { AlertTriangle } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_INPUT } from './resguardosStyles';

export default function PanelFaltantesResguardo({
    detalle,
    motivo,
    onMotivo,
    error = null,
    deshabilitado = false,
    children = null,
}) {
    return (
        <div className={`${geliaCardClass()} p-5 space-y-4 border border-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)]`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-[var(--color-aviso)] shrink-0 mt-0.5" aria-hidden />
                <div className="space-y-1">
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Faltan datos para continuar
                    </h2>
                    <p className="text-sm theme-text-muted m-0">{detalle}</p>
                </div>
            </div>
            {children}
            <label className="space-y-1.5 block">
                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                    Motivo para omitir y continuar
                </span>
                <textarea
                    value={motivo}
                    onChange={(e) => onMotivo(e.target.value)}
                    className={`${THEME_INPUT} min-h-[88px]`}
                    maxLength={500}
                    disabled={deshabilitado}
                    placeholder="Ej. el pedido se registró sin este dato"
                />
            </label>
            <p className="text-xs theme-text-muted m-0">
                Si registra el dato, el motivo no hace falta. La omisión queda en la bitácora.
            </p>
            {error && <p className="text-xs font-semibold theme-text-peligro m-0">{error}</p>}
        </div>
    );
}
