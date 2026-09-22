const DB_NAME = 'gelia-medio-cargas';
const STORE = 'cargas';

function abrirDb() {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') {
            resolve(null);
            return;
        }
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'id' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export function claveArchivoCarga(file) {
    return `${file.name}:${file.size}:${file.lastModified}`;
}

export async function guardarProgresoCarga(registro) {
    const db = await abrirDb();
    if (!db) return;
    await new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).put(registro);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function leerProgresoCarga(id) {
    const db = await abrirDb();
    if (!db) return null;
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).get(id);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

export async function borrarProgresoCarga(id) {
    const db = await abrirDb();
    if (!db) return;
    await new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

const BACKOFF = [1000, 3000, 8000];
const MAX_CONCURRENT = 4;
const MAX_REINTENTOS = 3;

export function duracionDesdeArchivoVideo(file) {
    if (!file?.type?.startsWith('video/')) return Promise.resolve(null);
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            const d = Number(video.duration);
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(d) && d > 0 ? Math.round(d) : null);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        video.src = url;
    });
}

async function putConReintentos(url, body, contentType, signal) {
    let ultimo = null;
    for (let i = 0; i <= MAX_REINTENTOS; i += 1) {
        if (signal?.aborted) throw new DOMException('Cancelado', 'AbortError');
        try {
            const res = await fetch(url, {
                method: 'PUT',
                headers: contentType ? { 'Content-Type': contentType } : {},
                body,
                signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.headers.get('ETag') || res.headers.get('etag') || `"part-${i}"`;
        } catch (err) {
            ultimo = err;
            if (err?.name === 'AbortError') throw err;
            if (i === MAX_REINTENTOS) break;
            await new Promise((r) => setTimeout(r, BACKOFF[i] ?? 8000));
        }
    }
    throw ultimo || new Error('No se pudo subir el archivo.');
}

async function colaLimitada(tareas, limite, onProgress, pausedRef, signal) {
    let indice = 0;
    let hechas = 0;
    const total = tareas.length;
    const workers = Array.from({ length: Math.min(limite, total) }, async () => {
        while (indice < total) {
            while (pausedRef.current) {
                if (signal?.aborted) throw new DOMException('Cancelado', 'AbortError');
                await new Promise((r) => setTimeout(r, 120));
            }
            const actual = indice;
            indice += 1;
            await tareas[actual]();
            hechas += 1;
            onProgress?.(hechas, total);
        }
    });
    await Promise.all(workers);
}

/**
 * @param {{ axios: import('axios').AxiosInstance, file: File, proposito: string, onProgress?: Function, pausedRef?: { current: boolean }, signal?: AbortSignal }} opts
 */
export async function subirMedioDirecto({
    axios,
    file,
    proposito,
    onProgress,
    pausedRef = { current: false },
    signal,
}) {
    const idLocal = claveArchivoCarga(file);
    const guardado = await leerProgresoCarga(idLocal);
    let init = guardado?.init || null;

    if (!init) {
        const { data } = await axios.post(route('medios.cargas.iniciar'), {
            filename: file.name,
            size: file.size,
            mime_type: file.type || 'application/octet-stream',
            proposito,
        });
        init = data;
        await guardarProgresoCarga({
            id: idLocal,
            filename: file.name,
            file_size: file.size,
            last_modified: file.lastModified,
            media_upload_id: init.media_upload_id,
            chunk_size: init.chunk_size || null,
            completed_parts: [],
            init,
        });
    }

    const cargaId = init.media_upload_id;
    onProgress?.({ estado: 'subiendo', loaded: 0, total: file.size, pct: 0 });

    if (init.upload_type === 'single') {
        await putConReintentos(init.upload_url, file, file.type, signal);
        onProgress?.({ estado: 'completando', loaded: file.size, total: file.size, pct: 100 });
        const duracion = await duracionDesdeArchivoVideo(file);
        const { data } = await axios.post(route('medios.cargas.completar', cargaId), {
            duration_seconds: duracion || undefined,
        });
        await borrarProgresoCarga(idLocal);
        onProgress?.({ estado: 'completado', loaded: file.size, total: file.size, pct: 100 });
        return data;
    }

    const chunk = init.chunk_size || (32 * 1024 * 1024);
    const totalPartes = Math.ceil(file.size / chunk);
    let completed = Array.isArray(guardado?.completed_parts) ? [...guardado.completed_parts] : [];

    if (cargaId && completed.length === 0) {
        try {
            const { data: status } = await axios.get(route('medios.cargas.estado', cargaId));
            if (Array.isArray(status.completed_parts) && status.completed_parts.length) {
                completed = status.completed_parts.map((p) => ({
                    PartNumber: p.PartNumber,
                    ETag: p.ETag,
                }));
            }
        } catch {
            // ponytail: si no hay status, se suben todas las partes
        }
    }

    const hechas = new Set(completed.map((p) => p.PartNumber));
    const faltantes = [];
    for (let n = 1; n <= totalPartes; n += 1) {
        if (!hechas.has(n)) faltantes.push(n);
    }

    const tareas = faltantes.map((partNumber) => async () => {
        const { data } = await axios.post(route('medios.cargas.partes', cargaId), {
            part_numbers: [partNumber],
        });
        const url = data.parts?.[0]?.upload_url;
        const start = (partNumber - 1) * chunk;
        const blob = file.slice(start, Math.min(start + chunk, file.size));
        const etag = await putConReintentos(url, blob, file.type, signal);
        completed.push({ PartNumber: partNumber, ETag: etag });
        await guardarProgresoCarga({
            id: idLocal,
            filename: file.name,
            file_size: file.size,
            last_modified: file.lastModified,
            media_upload_id: cargaId,
            chunk_size: chunk,
            completed_parts: completed,
            init,
        });
        const loaded = Math.min(file.size, completed.length * chunk);
        onProgress?.({
            estado: 'subiendo',
            loaded,
            total: file.size,
            pct: Math.round((completed.length / totalPartes) * 100),
            partes: `${completed.length} / ${totalPartes}`,
        });
    });

    await colaLimitada(tareas, MAX_CONCURRENT, null, pausedRef, signal);

    onProgress?.({ estado: 'completando', loaded: file.size, total: file.size, pct: 99 });
    const duracion = await duracionDesdeArchivoVideo(file);
    const { data } = await axios.post(route('medios.cargas.completar', cargaId), {
        parts: completed.sort((a, b) => a.PartNumber - b.PartNumber),
        duration_seconds: duracion || undefined,
    });
    await borrarProgresoCarga(idLocal);
    onProgress?.({ estado: 'completado', loaded: file.size, total: file.size, pct: 100 });
    return data;
}

export async function cancelarCargaMedio(axios, mediaUploadId, file) {
    if (mediaUploadId) {
        await axios.delete(route('medios.cargas.cancelar', mediaUploadId));
    }
    if (file) await borrarProgresoCarga(claveArchivoCarga(file));
}

export const MEDIA_UPLOADER_LIMITS = { MAX_CONCURRENT, MAX_REINTENTOS, BACKOFF };
