import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Cog } from 'lucide-react';
import GeliaLogo from '@/Components/GeliaLogo';

export default function PantallaActualizacionGelia({ isVisible, nota }) {
    const raiz = useRef(null);

    useEffect(() => {
        if (isVisible) {
            raiz.current?.focus();
        }
    }, [isVisible]);

    if (!isVisible || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            ref={raiz}
            tabIndex={-1}
            className="gelia-actualizacion fixed inset-0 z-[100000] flex flex-col items-center justify-center gap-8 overflow-hidden overscroll-contain px-6 animate-fade-in"
            style={{ background: 'var(--bg-app)' }}
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div className="gelia-actualizacion-halo" aria-hidden="true" />
            <GeliaLogo variant="sparkle" className="relative z-10 h-20 w-20" />
            <div className="relative z-10 flex items-center" aria-hidden="true">
                <span className="gelia-engranaje gelia-engranaje--horario">
                    <Cog className="size-8" />
                </span>
                <span className="gelia-engranaje gelia-engranaje--antihorario -mx-1.5">
                    <Cog className="size-14" />
                </span>
                <span className="gelia-engranaje gelia-engranaje--horario">
                    <Cog className="size-8" />
                </span>
            </div>
            <div className="relative z-10 max-w-sm text-center">
                <h2 className="m-0 text-balance text-xl font-semibold theme-text-main">
                    Actualizando tu <span translate="no">GELIA</span>
                </h2>
                {nota ? <p className="m-0 mt-2 text-pretty text-sm theme-text-muted">{nota}</p> : null}
            </div>
        </div>,
        document.body
    );
}
