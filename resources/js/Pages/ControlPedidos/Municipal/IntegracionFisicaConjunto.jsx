import React, { useState } from 'react';
import { PackageCheck } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import ConjuntoRemisionesMunicipal from './ConjuntoRemisionesMunicipal';
import EstadosResguardoMunicipal from './EstadosResguardoMunicipal';

const RUTAS = {
    resguardo_envio: { ruta: 'control_pedidos.municipal.remisiones.integrar_cedis', boton: 'Confirmar integración en CEDIS', exito: 'Integración en CEDIS confirmada.' },
    custodia_local: { ruta: 'control_pedidos.municipal.remisiones.integrar_local', boton: 'Confirmar integración en sucursal', exito: 'Integración en sucursal confirmada.' },
};

/**
 * Superficie del área que tiene la mercancía: confirma el hecho físico por remisión.
 * `mecanismos` limita qué remisiones puede confirmar esta área; el servidor vuelve a validar el permiso y la sucursal.
 */
export default function IntegracionFisicaConjunto({ pedido, mecanismos = ['resguardo_envio'], onActualizado }) {
    const [ocupado, setOcupado] = useState(false);
    const [aviso, setAviso] = useState('');
    const resguardo = pedido?.resguardo_envio;
    const vinculos = resguardo?.vinculos || [];
    if (!resguardo?.activo || !vinculos.length) return null;

    const confirmar = async (item) => {
        const definicion = RUTAS[item.mecanismo];
        if (!definicion || ocupado) return;
        setOcupado(true);
        setAviso('');
        try {
            const { data } = await window.axios.post(route(definicion.ruta, [pedido.id, item.origen_id]), {}, { headers: { Accept: 'application/json' } });
            onActualizado?.({ ...pedido, ...data });
            setAviso(definicion.exito);
        } catch (error) {
            setAviso(error.response?.data?.message || 'No se pudo confirmar la integración. Revise e intente de nuevo.');
        } finally {
            setOcupado(false);
        }
    };

    return (
        <section className="registrar-tienda-panel grid gap-3" aria-label="Integración del conjunto de remisiones">
            <div className="registrar-tienda-seccion-titulo"><PackageCheck size={18} aria-hidden="true" /><h3>Integración del conjunto</h3></div>
            <p className="text-xs theme-text-muted m-0">Este envío espera la mercancía de varias remisiones. Confirme solo lo que ya tiene identificado y junto. Liberar el resguardo es decisión de la persona auxiliar.</p>
            <EstadosResguardoMunicipal estados={resguardo.estados} />
            <ConjuntoRemisionesMunicipal
                vinculos={vinculos}
                acciones={(item) => (!item.integrado && mecanismos.includes(item.mecanismo) && RUTAS[item.mecanismo]
                    && (item.mecanismo !== 'resguardo_envio' || item.estado === 'anexado') ? (
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={() => confirmar(item)}>
                            {RUTAS[item.mecanismo].boton}
                        </button>
                    ) : null)}
            />
            {aviso && <p className="text-sm theme-text-main m-0" role="status" aria-live="polite">{aviso}</p>}
        </section>
    );
}
