/** Contrato Markdown de asesoría, también compatible con las fichas ya guardadas. */
const limpiarTitulo = (linea) => linea.trim().replace(/^#{1,6}\s+/, '').replace(/^[*_]+|[*_]+$/g, '').replace(/\*\*/g, '').replace(/:\s*$/, '').trim();

function campos(texto, tipos) {
    const valores = {};
    const resto = [];
    let actual = null;
    for (const linea of texto.split('\n')) {
        const limpia = linea.trim().replace(/^[-*]\s+/, '').replace(/\*\*/g, '');
        const match = limpia.match(/^([^:]+):\s*(.*)$/);
        const tipo = match && tipos.find(([, patron]) => patron.test(match[1].trim()));
        if (tipo) {
            actual = tipo[0];
            valores[actual] = [valores[actual], match[2]].filter(Boolean).join('\n');
        } else if (actual && limpia && !/^[-*#]/.test(linea.trim())) {
            valores[actual] += `\n${linea.trim()}`;
        } else if (linea.trim()) {
            actual = null;
            resto.push(linea);
        }
    }
    return { valores, resto: resto.join('\n') };
}

export function parsearFichaPerfume(texto) {
    const partes = { intro: [], speech: [], notas: [], usos: [], fuentes: [], aviso: [] };
    let seccion = 'intro';
    let tieneSpeech = false;
    let tieneNotas = false;
    for (const linea of String(texto || '').split(/\r?\n/)) {
        const titulo = limpiarTitulo(linea);
        if (/^(para (explic[aá]rselo|dec[ií]rselo) al cliente|para decir al cliente|d[ií]selo as[ií])$/i.test(titulo)) {
            seccion = 'speech'; tieneSpeech = true; continue;
        }
        if (/^notas(?:\s*\([^)]*\))?$/i.test(titulo)) {
            seccion = 'notas'; tieneNotas = true; continue;
        }
        if (/^(c[oó]mo recomendarlo|recomendaci[oó]n r[aá]pida)$/i.test(titulo)) {
            seccion = 'usos'; continue;
        }
        if (/^fuentes(?:\s*\([^)]*\))?$/i.test(titulo)) {
            seccion = 'fuentes'; continue;
        }
        const aviso = titulo.match(/^(?:advertencia|por confirmar|ten en cuenta)\s*:?\s*(.*)$/i);
        if (aviso) {
            seccion = 'aviso';
            if (aviso[1]) partes.aviso.push(aviso[1]);
            continue;
        }
        partes[seccion].push(linea);
    }
    // Un mensaje común, comparación o respuesta incompleta sigue usando el renderizador general.
    if (!tieneSpeech || !tieneNotas || !partes.speech.join('').trim()) return null;
    const intro = partes.intro.filter((linea) => linea.trim());
    const notas = campos(partes.notas.join('\n'), [['salida', /^salida$/i], ['corazon', /^coraz[oó]n$/i], ['fondo', /^fondo$/i]]);
    const usos = campos(partes.usos.join('\n'), [
        ['preferencia', /^(?:para qui[eé]n|le puede gustar a quien busca|perfil)$/i],
        ['ocasiones', /^ocasi[oó]n(?:es)?$/i],
        ['clima', /^(?:clima(?:\s*\/\s*temporada)?|temporada)$/i],
        ['uso', /^uso(?: orientativo)?$/i],
    ]);
    return {
        titulo: limpiarTitulo(intro.shift() || 'Tu ficha de perfume'),
        identificacion: intro.join('\n'),
        speech: partes.speech.join('\n').trim(),
        notas: notas.valores,
        notasExtra: notas.resto,
        usos: usos.valores,
        usosExtra: usos.resto,
        fuentes: partes.fuentes.join('\n').trim(),
        aviso: partes.aviso.join('\n').trim(),
    };
}
