export default function Interruptor({ activo, onChange, etiqueta, disabled = false }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={activo}
            aria-label={etiqueta}
            disabled={disabled}
            onClick={() => onChange(!activo)}
            className="inline-flex items-center justify-center min-h-11 min-w-11 p-2 rounded-full disabled:opacity-50"
        >
            <span className="gelia-switch pointer-events-none" data-active={activo ? 'true' : 'false'}>
                <span className="gelia-switch-thumb" />
            </span>
        </button>
    );
}
