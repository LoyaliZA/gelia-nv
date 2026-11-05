import React, { useState } from 'react';
import axios from 'axios';
import { CheckCircle2, PackageX } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_LABEL, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';

function Alternativa({ pedido, anexo, indice, onActualizar }) {
    const [producto, setProducto] = useState(null);
    const [nota, setNota] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    const responder = async decision => {
        if (!nota.trim()) return setError('Registre el resultado de la revisión de esta alternativa.');
        if (decision === 'disponible' && !producto && !anexo.producto_id) return setError('Identifique la pieza disponible por SKU.');
        setOcupado(true); setError('');
        try { const { data } = await axios.post(`/control-pedidos/${pedido.id}/disponibilidad-tienda/alternativa`, { indice, decision, nota, producto_id: producto?.id || anexo.producto_id }); onActualizar(data); }
        catch (e) { setError(Object.values(e?.response?.data?.errors || {}).flat()[0] || e?.response?.data?.message || 'No se pudo responder la alternativa.'); }
        finally { setOcupado(false); }
    };
    return <div className="registrar-tienda-panel grid gap-3"><h4 className="text-sm font-bold theme-text-main m-0">Revisar alternativa · {anexo.descripcion}</h4>{!anexo.producto_id && <SelectorProducto almacenId={pedido.almacen_id} incluirSinInventario value={producto?.id || ''} onChange={(_,p) => setProducto(p)} disabled={ocupado} />}<label className={THEME_LABEL}>Resultado de la revisión<textarea className={`${THEME_TEXTAREA} w-full mt-1`} rows={2} value={nota} maxLength={500} onChange={e => setNota(e.target.value)} /></label>{error && <p role="alert" className="text-sm theme-text-peligro m-0">{error}</p>}<div className="flex flex-wrap gap-2"><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => responder('disponible')}><CheckCircle2 size={16} />Confirmar disponibilidad</button><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => responder('faltante')}><PackageX size={16} />Reportar sin existencias</button></div></div>;
}
export default function AlternativasDisponibilidadCedis({ pedido, onVer }) {
    const [lectura, setLectura] = useState(null);
    const actual = lectura || pedido;
    const solicitud = actual.solicitud_preparacion || actual.solicitud_preparacion_json || {};
    return <div className="grid gap-3"><ResumenCambiosDisponibilidad solicitud={solicitud} documentos={actual.documentos || []} onVer={onVer} />{(solicitud.anexo_sin_remision || []).map((a,i) => !a.respondida_at ? <Alternativa key={i} pedido={actual} anexo={a} indice={i} onActualizar={setLectura} /> : null)}</div>;
}
