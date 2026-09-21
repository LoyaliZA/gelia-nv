import React from 'react';
import { Loader2, Package, UserRound } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { titularResguardo } from './resguardosUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

function CampoSoloLectura({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0 break-words">{value || '—'}</p>
        </div>
    );
}

export default function FormularioConfirmarRecepcionResguardo({
    resguardo,
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
}) {
    const cantidadBultos = resguardo?.cantidad_bultos_esperada ?? 0;
    const bultosCedis = resguardo?.bultos_empaque_cedis || [];
    const titular = titularResguardo(resguardo);
    const etiquetaRetiro = resguardo?.etiqueta_retiro
        || (resguardo?.envia_a_otra_persona
            ? `Recoge tercero autorizado: ${resguardo.envia_otra_persona}`
            : 'Retira el titular del pedido');

    useToastAlCambiar(error, 'error');

    const confirmarEnvio = async (e) => {
        e.preventDefault();
        await onEnviar?.();
    };

    return (
        <form onSubmit={confirmarEnvio} className="space-y-5">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Resumen del resguardo</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <CampoSoloLectura label="Folio" value={resguardo.snapshot_folio || `#${resguardo.id}`} />
                    <CampoSoloLectura label="Cliente" value={titular} />
                    <CampoSoloLectura label="Quién retira" value={etiquetaRetiro} />
                    <CampoSoloLectura label="Bultos esperados" value={String(cantidadBultos)} />
                </div>
            </div>

            {bultosCedis.length > 0 && (
                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <div className="flex items-center gap-2">
                        <Package className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos registrados en CEDIS</h2>
                    </div>
                    <ChipEvidenciasBultosEmpaque
                        bultos={bultosCedis}
                        folio={resguardo.snapshot_folio}
                    />
                </div>
            )}

            <div className={`${geliaCardClass()} p-4 flex items-start gap-3 border border-sky-500/20`}>
                <UserRound className="w-5 h-5 text-sky-600 shrink-0 mt-0.5" />
                <p className="text-sm theme-text-muted m-0">
                    Solo confirma que el paquete llegó a sucursal. El recepcionista revisará bultos y piezas después.
                </p>
            </div>

            <div className="flex flex-col sm:flex-row gap-2">
                <button
                    type="submit"
                    disabled={enviando}
                    className={`${THEME_BTN_PRIMARY} flex-1 min-h-[48px] inline-flex items-center justify-center gap-2`}
                >
                    {enviando ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Confirmando… {progreso > 0 ? `${progreso}%` : ''}
                        </>
                    ) : (
                        'Confirmar recepción'
                    )}
                </button>
            </div>
        </form>
    );
}
