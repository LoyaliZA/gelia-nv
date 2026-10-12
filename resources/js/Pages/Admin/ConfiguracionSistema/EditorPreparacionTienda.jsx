import { useState } from 'react';
import Interruptor from './Interruptor';
import { parseObjeto } from './editoresJson';

export const CLAVE_FLAG_PREPARACION_TIENDA = 'control_pedidos.preparacion_tienda';

const MODALIDADES = [
    {
        codigo: 'RECOGE_TIENDA',
        titulo: 'Recoge hoy',
        detalle: 'Apartado el mismo día en un almacén de tipo PDV. Sin pago ni transferencia.',
    },
    {
        codigo: 'RECOGE_TIENDA_TRANSFERENCIA',
        titulo: 'Resguardo con transferencia',
        detalle: 'El pago es por transferencia. La mercancía queda resguardada hasta la recolección.',
    },
    {
        codigo: 'ENVIO_BODEGA_NORMAL',
        titulo: 'Envío con piezas de tienda',
        detalle: 'Piezas en tienda que se trasladan a CEDIS para integrar el envío.',
    },
    {
        codigo: 'ENVIO_BODEGA_COMPLEMENTO',
        titulo: 'Complemento de envío',
        detalle: 'Traslado de piezas de tienda ligadas a un pedido complementario.',
    },
    {
        codigo: 'ENVIO_MUNICIPIO',
        titulo: 'Envío municipal',
        detalle: 'Tienda prepara el paquete e imprime la carátula.',
    },
];

const enteros = (lista) => (Array.isArray(lista) ? lista : [])
    .map((n) => parseInt(n, 10))
    .filter((n) => Number.isFinite(n) && n > 0);

const listaIds = (texto) => String(texto || '')
    .split(/[^0-9]+/)
    .map((n) => parseInt(n, 10))
    .filter((n) => Number.isFinite(n) && n > 0);

export function parseFlagPreparacionTienda(valor) {
    let raw = {};
    try {
        raw = typeof valor === 'string' ? JSON.parse(valor || '{}') : (valor || {});
    } catch {
        raw = {};
    }
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) raw = {};

    return {
        activo: Boolean(raw.activo),
        modalidades: Array.isArray(raw.modalidades_habilitadas) ? raw.modalidades_habilitadas.map(String) : [],
        departamentos: enteros(raw.departamentos_habilitados).join(', '),
        almacenes: enteros(raw.almacenes_habilitados).join(', '),
        usuarios: enteros(raw.usuarios_piloto).join(', '),
    };
}

export function serializarFlagPreparacionTienda(flag, valorOriginal) {
    return JSON.stringify({
        ...parseObjeto(valorOriginal),
        activo: Boolean(flag.activo),
        modalidades_habilitadas: flag.modalidades,
        departamentos_habilitados: listaIds(flag.departamentos),
        almacenes_habilitados: listaIds(flag.almacenes),
        usuarios_piloto: listaIds(flag.usuarios),
    });
}

export default function EditorPreparacionTienda({ valor, onChange }) {
    const [flag, setFlag] = useState(() => parseFlagPreparacionTienda(valor));

    const publicar = (siguiente) => {
        setFlag(siguiente);
        onChange(serializarFlagPreparacionTienda(siguiente, valor));
    };

    const alternarModalidad = (codigo) => {
        const modalidades = flag.modalidades.includes(codigo)
            ? flag.modalidades.filter((item) => item !== codigo)
            : [...flag.modalidades, codigo];
        publicar({ ...flag, modalidades });
    };

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-3 p-3 rounded-xl border theme-border">
                <div>
                    <p className="text-sm font-bold theme-text-main m-0">Módulo de preparación en tienda</p>
                    <p className="text-xs theme-text-muted m-0 mt-1">
                        Con el módulo activo, un pedido de tienda solicita preparación en tienda en lugar de la consulta a CEDIS.
                    </p>
                </div>
                <Interruptor
                    activo={flag.activo}
                    etiqueta="Activar preparación en tienda"
                    onChange={(activo) => publicar({ ...flag, activo })}
                />
            </div>

            <div className="space-y-2">
                <p className="text-sm font-bold theme-text-main m-0">Modalidades disponibles</p>
                {MODALIDADES.map((modalidad) => {
                    const activo = flag.modalidades.includes(modalidad.codigo);
                    return (
                        <div key={modalidad.codigo} className="flex items-start justify-between gap-3 p-3 rounded-xl border theme-border">
                            <div>
                                <p className="text-sm font-bold theme-text-main m-0">{modalidad.titulo}</p>
                                <p className="text-xs theme-text-muted m-0 mt-1">{modalidad.detalle}</p>
                            </div>
                            <Interruptor
                                activo={activo}
                                etiqueta={modalidad.titulo}
                                onChange={() => alternarModalidad(modalidad.codigo)}
                            />
                        </div>
                    );
                })}
            </div>

            <details className="rounded-xl border theme-border p-3">
                <summary className="text-xs font-bold theme-text-muted cursor-pointer">Restricciones opcionales (identificadores)</summary>
                <p className="text-xs theme-text-muted mt-2 mb-3">
                    Deje el campo vacío para no restringir. Varios identificadores se separan con coma.
                </p>
                <label className="block text-xs font-semibold theme-text-main mb-1">Departamentos</label>
                <input
                    className="theme-input w-full px-3 py-2 text-sm mb-3"
                    value={flag.departamentos}
                    onChange={(e) => publicar({ ...flag, departamentos: e.target.value })}
                    placeholder="Ej. 4, 12"
                />
                <label className="block text-xs font-semibold theme-text-main mb-1">Almacenes</label>
                <input
                    className="theme-input w-full px-3 py-2 text-sm mb-3"
                    value={flag.almacenes}
                    onChange={(e) => publicar({ ...flag, almacenes: e.target.value })}
                    placeholder="Vacío: todos los almacenes de tipo PDV"
                />
                <label className="block text-xs font-semibold theme-text-main mb-1">Personas en prueba</label>
                <input
                    className="theme-input w-full px-3 py-2 text-sm"
                    value={flag.usuarios}
                    onChange={(e) => publicar({ ...flag, usuarios: e.target.value })}
                    placeholder="Vacío: cualquiera con permiso"
                />
            </details>
        </div>
    );
}
