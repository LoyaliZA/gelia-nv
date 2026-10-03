import React from 'react';
import { createPortal } from 'react-dom';
import { Accessibility, PersonStanding, UserRound } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';
import { etiquetasPrioridadDesdeTurno } from '@/Pages/PuntoVenta/Turnos/Partials/tableroVentasUtils';
import { badgePrioridadTurno } from '@/Pages/PuntoVenta/Turnos/Partials/turnosStyles';

function etiquetasSala(turno) {
    return turno?.prioridad_diamante ? ['Diamante'] : [];
}

function IconoEtiqueta({ etiqueta }) {
    if (etiqueta === 'Discapacidad') {
        return <Accessibility className="w-4 h-4" aria-hidden />;
    }
    if (etiqueta === 'Adulto mayor') {
        return <PersonStanding className="w-4 h-4" aria-hidden />;
    }
    return null;
}

export default function ModalLlamadoTurnoPdv({
    abierto = false,
    turno = null,
    variante = 'sala',
    onCerrar = null,
}) {
    if (!abierto || !turno || typeof document === 'undefined') return null;

    const cliente = turno.snapshot_nombre_llamado || turno.snapshot_cliente_nombre || 'Cliente';
    const vendedor = turno.atencion_nombre || turno.atencion_primer_nombre || turno.atencion?.primer_nombre || '—';
    const etiquetas = variante === 'vendedor'
        ? etiquetasPrioridadDesdeTurno(turno)
        : etiquetasSala(turno);
    const diamante = Boolean(turno.prioridad_diamante);

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center p-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            data-pdv-modal-llamado={variante}
            onClick={() => onCerrar?.()}
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-xl p-8 text-center modal-pop ${diamante && variante === 'vendedor' ? 'pdv-llamado-diamante' : ''}`}
                role="dialog"
                aria-modal="true"
                aria-label={`Turno ${turno.folio || ''}`}
                onClick={(event) => event.stopPropagation()}
            >
                <p className="m-0 text-xs font-black uppercase tracking-[0.2em] theme-text-muted">Turno</p>
                <p className="m-0 mt-2 text-5xl font-black theme-text-main">{turno.folio || '—'}</p>
                <p className="m-0 mt-4 text-2xl font-bold theme-text-main">{cliente}</p>
                {etiquetas.length > 0 && (
                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {etiquetas.map((etiqueta) => (
                            <span
                                key={etiqueta}
                                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black uppercase ${badgePrioridadTurno(etiqueta)}`}
                            >
                                <IconoEtiqueta etiqueta={etiqueta} />
                                {etiqueta}
                            </span>
                        ))}
                    </div>
                )}
                <div className="mt-6 flex items-center justify-center gap-2 theme-text-main">
                    <UserRound className="w-5 h-5" aria-hidden />
                    <p className="m-0 text-lg font-bold">{vendedor}</p>
                </div>
            </div>
        </div>,
        document.body,
    );
}
