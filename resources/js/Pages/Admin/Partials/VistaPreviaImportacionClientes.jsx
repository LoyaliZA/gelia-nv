const TEXTOS = {
    inactivacion: 'Hay inactivaciones propuestas. GELIA no las escribe hasta definir una señal explícita de baja.',
    codigo_vacio: 'Un código de lista vacío cambiaría el estado o la lista.',
    lista_bloqueada: 'El archivo reemplazaría una lista protegida.',
    archivo_repetido: 'Este archivo ya fue importado.',
    archivo_anterior: 'Hay montos más recientes que la fecha del archivo.',
    archivo_invalido: 'El archivo completo no se puede confirmar. Hay que corregirlo y revisarlo de nuevo.',
    descenso_lista: 'Hay descensos de lista. Quedan a la vista en esta revisión.',
    diferencia_monto: 'Hay diferencias de monto de 1000 o más. No se corrigen solas.',
};

const CONTEOS = [
    ['filas', 'Filas'],
    ['nuevos', 'Clientes nuevos'],
    ['actualizaciones', 'Actualizaciones'],
    ['sin_cambios', 'Sin cambios'],
    ['errores', 'Filas con error'],
    ['cambios_lista', 'Cambios de lista'],
    ['descensos', 'Descensos de lista'],
    ['activaciones', 'Activaciones'],
    ['inactivaciones', 'Inactivaciones propuestas'],
    ['cambios_monto', 'Cambios de monto'],
];

export default function VistaPreviaImportacionClientes({ vista, onConfirmar, confirmando }) {
    if (!vista) return null;
    const conteos = vista.conteos || {};

    return (
        <section aria-label="Revisión de importación" className="theme-element border theme-border rounded-xl p-4 space-y-4">
            <h3 className="text-sm font-bold theme-text-main m-0">Revisión antes de escribir</h3>
            <dl className="grid grid-cols-2 gap-3 m-0">
                {CONTEOS.map(([clave, etiqueta]) => (
                    <div key={clave}>
                        <dt className="text-xs theme-text-muted">{etiqueta}</dt>
                        <dd className="text-lg font-bold theme-text-main m-0">{conteos[clave] ?? 0}</dd>
                    </div>
                ))}
            </dl>
            {(vista.bloqueos || []).map((bloqueo) => (
                <p key={bloqueo.codigo} className="text-sm theme-text-main m-0">{TEXTOS[bloqueo.codigo] || bloqueo.codigo} ({bloqueo.filas})</p>
            ))}
            {(vista.avisos || []).map((aviso) => (
                <p key={aviso.codigo} className="text-sm theme-text-main m-0">{TEXTOS[aviso.codigo] || aviso.codigo} ({aviso.filas})</p>
            ))}
            {(vista.muestras || []).length > 0 && (
                <div className="space-y-2">
                    <p className="text-sm font-bold theme-text-main m-0">Clientes afectados</p>
                    {vista.muestras.map((muestra) => (
                        <p key={`${muestra.numero_cliente}-${muestra.motivo}`} className="text-sm theme-text-main break-words m-0">
                            <strong>{muestra.numero_cliente}</strong> {muestra.nombre ? `· ${muestra.nombre}` : ''} · {muestra.motivo}
                        </p>
                    ))}
                    {conteos.afectados > vista.muestras.length && (
                        <p className="text-xs theme-text-muted m-0">Se muestran {vista.muestras.length} de {conteos.afectados}.</p>
                    )}
                </div>
            )}
            <button
                type="button"
                onClick={onConfirmar}
                disabled={vista.bloqueado || confirmando}
                className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all shadow-xl disabled:opacity-50 outline-none"
                style={{ backgroundColor: 'var(--color-primario)' }}
            >
                {vista.bloqueado ? 'Escritura detenida' : confirmando ? 'Confirmando…' : 'Confirmar esta revisión'}
            </button>
        </section>
    );
}
