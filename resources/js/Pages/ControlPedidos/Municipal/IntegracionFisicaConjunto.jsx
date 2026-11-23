import React, { useEffect, useRef, useState } from 'react';
import { PackageCheck } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import ConjuntoRemisionesMunicipal from './ConjuntoRemisionesMunicipal';
import EstadosResguardoMunicipal from './EstadosResguardoMunicipal';

const RUTAS = {
    resguardo_envio: { ruta: 'control_pedidos.municipal.remisiones.integrar_cedis', boton: 'Confirmar integración en CEDIS', ubicacion: 'CEDIS', exito: 'Integración en CEDIS confirmada.' },
    custodia_local: { ruta: 'control_pedidos.municipal.remisiones.integrar_local', boton: 'Confirmar integración en sucursal', ubicacion: 'sucursal donde está la custodia', exito: 'Integración en sucursal confirmada.' },
};

/**
 * Superficie del área que tiene la mercancía: confirma el hecho físico por remisión.
 * `mecanismos` limita qué remisiones puede confirmar esta área; el servidor vuelve a validar el permiso y la sucursal.
 */
export default function IntegracionFisicaConjunto({ pedido, mecanismos = ['resguardo_envio'], onActualizado }) {
    const [ocupado, setOcupado] = useState(false);
    const [aviso, setAviso] = useState('');
    const [porConfirmar, setPorConfirmar] = useState(null);
    const enCurso = useRef(false);
    const montado = useRef(true);
    useEffect(() => { montado.current = true; return () => { montado.current = false; }; }, []);
    const resguardo = pedido?.resguardo_envio;
    const vinculos = resguardo?.vinculos || [];
    // Con o sin resguardo activo: mientras haya remisiones anexadas por integrar, el área que las tiene debe poder confirmarlas.
    if (!vinculos.length) return null;
    const pendientes = vinculos.filter((item) => !item.integrado).length;

    const confirmar = async (item) => {
        const definicion = RUTAS[item.mecanismo];
        // Bloqueo de doble clic: la referencia cierra la puerta antes de que React pinte el estado ocupado.
        if (!definicion || ocupado || enCurso.current) return;
        enCurso.current = true;
        setOcupado(true);
        setAviso('');
        try {
            const { data } = await window.axios.post(route(definicion.ruta, [pedido.id, item.origen_id]), {}, { headers: { Accept: 'application/json' } });
            // Solo se marca integrado con la respuesta real del servidor.
            onActualizado?.({ ...pedido, ...data });
            if (montado.current) { setAviso(definicion.exito); setPorConfirmar(null); }
        } catch (error) {
            // El error es recuperable: la confirmación sigue abierta para reintentar o cancelar.
            if (montado.current) setAviso(error.response?.data?.message || 'No se pudo confirmar la integración. Revise e intente de nuevo.');
        } finally {
            enCurso.current = false;
            if (montado.current) setOcupado(false);
        }
    };

    return (
        <section className="registrar-tienda-panel grid gap-3" aria-label="Integración del conjunto de remisiones">
            <div className="registrar-tienda-seccion-titulo"><PackageCheck size={18} aria-hidden="true" /><h3>Integración del conjunto</h3></div>
            <p className="text-xs theme-text-muted m-0">
                {pendientes > 0
                    ? `Este envío incluye remisiones anexadas. ${pendientes === 1 ? 'Falta 1 por confirmar' : `Faltan ${pendientes} por confirmar`}: el pedido no se empaca hasta que cada una esté identificada y junta.`
                    : 'Todas las remisiones anexadas están integradas.'}
                {resguardo.activo ? ' Liberar el resguardo es decisión de la persona auxiliar.' : ''}
            </p>
            <EstadosResguardoMunicipal estados={resguardo.estados} />
            <ConjuntoRemisionesMunicipal
                vinculos={vinculos}
                acciones={(item) => (!item.integrado && mecanismos.includes(item.mecanismo) && RUTAS[item.mecanismo]
                    && (item.mecanismo !== 'resguardo_envio' || item.estado === 'anexado') ? (
                        porConfirmar === item.origen_id ? (
                            <div role="group" aria-label={`Confirmar integración de ${item.folio}`} className="grid gap-2 border theme-border rounded-xl px-3 py-2 w-full">
                                <p className="text-sm theme-text-main m-0">
                                    <strong>{item.folio}</strong> · Remisión {item.remision || 'sin número'} · Envío principal {pedido.folio}<br />
                                    Ubicación: {RUTAS[item.mecanismo].ubicacion}.<br />
                                    Confirma que las piezas de esta remisión ya están físicamente identificadas e integradas al envío indicado.
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={() => confirmar(item)}>{ocupado ? 'Confirmando…' : 'Confirmar integración'}</button>
                                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={() => setPorConfirmar(null)}>Cancelar</button>
                                </div>
                            </div>
                        ) : (
                            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={() => { setAviso(''); setPorConfirmar(item.origen_id); }}>
                                {RUTAS[item.mecanismo].boton}
                            </button>
                        )
                    ) : null)}
            />
            {aviso && <p className="text-sm theme-text-main m-0" role="status" aria-live="polite">{aviso}</p>}
        </section>
    );
}
