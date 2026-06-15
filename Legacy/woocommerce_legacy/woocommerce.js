/**
 * GELIA - WooCommerce Sync Hub
 * Script Principal para la Integración de Inventario y API
 */

// 1. Controladores de Interfaz (UI)
window.cambiarPestanaWoo = function(pestana) {
    const tabs = {
        'diario': { btn: 'tab-diario', form: 'form-diario', color: 'bg-purple-600' },
        'sync': { btn: 'tab-sync', form: 'form-sync', color: 'bg-blue-600' }
    };

    Object.keys(tabs).forEach(key => {
        const btn = document.getElementById(tabs[key].btn);
        const form = document.getElementById(tabs[key].form);
        
        if (!btn || !form) return;

        if (key === pestana) {
            btn.classList.add(tabs[key].color, 'text-white');
            btn.classList.remove('text-gray-400');
            form.classList.remove('hidden');
        } else {
            btn.classList.remove('bg-purple-600', 'bg-blue-600', 'text-white');
            btn.classList.add('text-gray-400');
            form.classList.add('hidden');
        }
    });
};

// Funciones para el Modal de Previsualización
window.cerrarModalPrevisualizacion = () => document.getElementById('modal-previsualizacion').classList.add('hidden');

function renderizarTablaPrevisualizacion(detalles) {
    const tbody = document.getElementById('tabla-previsualizacion');
    tbody.innerHTML = '';

    if (detalles.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-dark-muted font-bold">No se detectaron diferencias con los precios actuales.</td></tr>`;
        return;
    }

    detalles.forEach(item => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-dark-700/30 transition-colors';
        
        row.innerHTML = `
            <td class="px-6 py-4 font-bold text-gray-300">${item.sku}</td>
            <td class="px-6 py-4 text-white font-medium truncate max-w-xs">${item.nombre}</td>
            <td class="px-6 py-4 text-center">
                <span class="line-through text-dark-muted text-xs mr-2">$${Number(item.precio_normal_anterior).toFixed(2)}</span>
                <span class="text-white font-bold">$${Number(item.precio_normal_nuevo).toFixed(2)}</span>
            </td>
            <td class="px-6 py-4 text-center">
                <span class="line-through text-dark-muted text-xs mr-2">$${Number(item.precio_rebaja_anterior).toFixed(2)}</span>
                <span class="text-green-400 font-bold">$${Number(item.precio_rebaja_nuevo).toFixed(2)}</span>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('texto-resumen-cambios').innerText = `Se detectaron ${detalles.length} productos que requieren actualización en WooCommerce.`;
    document.getElementById('modal-previsualizacion').classList.remove('hidden');
}

// 2. Procesamiento de Inventario Modificado
window.ejecutarProceso = async (modo) => {
    const inputExcel = document.getElementById('listado-aromas') || document.getElementById('file-listado-aromas');
    const tokenInput = document.querySelector('input[name="_token"]');
    
    if (!inputExcel || !inputExcel.files.length) {
        return window.mostrarToast('Selecciona el Excel de Wizerp primero.', 'red');
    }

    const formData = new FormData();
    formData.append('listado_aromas', inputExcel.files[0]);
    formData.append('_token', tokenInput.value);

    try {
        if (modo === 'local') {
            window.mostrarCarga('Generando archivo de resurtido...');
            const response = await fetch('/woocommerce/procesar', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' }});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Error del servidor.');
            
            window.mostrarToast('Archivo generado exitosamente.', 'green');
            const link = document.createElement('a'); link.href = data.download_url; link.click();
            setTimeout(() => window.location.reload(), 1500);

        } else if (modo === 'previsualizar') {
            window.mostrarCarga('Analizando diferencias de precios...');
            const response = await fetch('/woocommerce/api/previsualizar', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' }});
            const data = await response.json();
            window.ocultarCarga();
            
            if (!response.ok) throw new Error(data.message || 'Error al analizar el documento.');
            renderizarTablaPrevisualizacion(data.detalles);

        } else if (modo === 'nube') {
            cerrarModalPrevisualizacion();
            window.mostrarCarga('Iniciando carga estructurada por lotes...');
            
            const response = await fetch('/woocommerce/api/carga-masiva', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' }});
            const data = await response.json();
            window.ocultarCarga();
            
            if (!response.ok) throw new Error(data.message || 'Error de conexión.');

            document.getElementById('status-api-container').classList.remove('hidden');
            trackearProgreso(data.log_id);
        }
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

// 3. Sincronización Interna (Catálogo WooCommerce -> GELIA)
window.sincronizarCatalogo = async () => {
    const inputCsv = document.getElementById('woocommerce-csv') || document.getElementById('file-woocommerce-csv');
    const tokenInput = document.querySelector('input[name="_token"]');

    if (!inputCsv || !inputCsv.files.length) {
        return window.mostrarToast('Sube el CSV de WooCommerce.', 'red');
    }

    const formData = new FormData();
    formData.append('woocommerce_csv', inputCsv.files[0]);
    formData.append('_token', tokenInput.value);

    window.mostrarCarga('Sincronizando base de datos local...');
    try {
        const response = await fetch('/woocommerce/productos/sincronizar', { 
            method: 'POST', 
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Fallo en la sincronización.');

        window.mostrarToast('Catálogo actualizado.', 'green');
        setTimeout(() => window.location.reload(), 1500);
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

// 3.1 Sincronización de Precios Locales (Gelia -> WooCommerce)
window.sincronizarPreciosLocales = async () => {
    const inputCsv = document.getElementById('archivo-precios-locales');
    const tokenInput = document.querySelector('input[name="_token"]');

    if (!inputCsv || !inputCsv.files.length) {
        return window.mostrarToast('Sube el CSV de precios exportado por Gelia.', 'red');
    }

    const formData = new FormData();
    formData.append('archivo_precios_locales', inputCsv.files[0]);
    formData.append('_token', tokenInput.value);

    window.mostrarCarga('Actualizando base de datos local de precios...');
    try {
        const response = await fetch('/woocommerce/precios/locales', { 
            method: 'POST', 
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Fallo en la actualización local.');

        window.mostrarToast(data.message, 'green');
        setTimeout(() => window.location.reload(), 1500);
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

// 4. Tracker de Tareas en Segundo Plano (Seguro)
function trackearProgreso(logId) {
    const intervalo = setInterval(async () => {
        try {
            const res = await fetch(`/woocommerce/api/progreso/${logId}`);
            
            // Freno de emergencia: Si el servidor responde 404 o 500, matamos el bucle.
            if (!res.ok) {
                clearInterval(intervalo);
                window.ocultarCarga();
                throw new Error('Error de conexión con el servidor: ' + res.status);
            }

            const log = await res.json();
            if (!log) return;

            const porcentaje = log.total_productos > 0 
                ? Math.round((log.procesados / log.total_productos) * 100) 
                : 0;
            
            const barra = document.getElementById('barra-progreso');
            const textoPorc = document.getElementById('porcentaje-texto');
            const textoCont = document.getElementById('conteo-productos');

            if (barra) barra.style.width = porcentaje + '%';
            if (textoPorc) textoPorc.innerText = porcentaje + '%';
            if (textoCont) textoCont.innerText = `${log.procesados} / ${log.total_productos}`;

            if (log.estado === 'completado') {
                clearInterval(intervalo);
                window.mostrarToast('Sincronización masiva finalizada.', 'green');
                setTimeout(() => window.location.reload(), 2000);
            } else if (log.estado === 'error') {
                clearInterval(intervalo);
                window.mostrarToast('El proceso falló en el servidor.', 'red');
            }
        } catch (error) { 
            clearInterval(intervalo); // Aseguramos que el bucle muera si hay error de sintaxis/JSON
            console.error('Error al consultar progreso:', error); 
            window.mostrarToast('Se interrumpió la conexión con el servidor.', 'red');
        }
    }, 3000);
}

// 5. Gestión de Sistema de Archivos
window.eliminarTemplateWoo = async (id) => {
    if (!confirm('¿Deseas eliminar este archivo del historial?')) return;
    
    window.mostrarCarga('Eliminando...');
    try {
        const res = await fetch(`/woocommerce/eliminar/${id}`, {
            method: 'DELETE',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, 
                'Accept': 'application/json' 
            }
        });

        if (res.ok) {
            window.mostrarToast('Archivo eliminado.', 'green');
            setTimeout(() => window.location.reload(), 800);
        } else {
            throw new Error('No se pudo borrar el archivo.');
        }
    } catch (e) { 
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red'); 
    }
};

// 6. Modales de Configuración y Seguridad
window.abrirModalPin = () => {
    const input = document.getElementById('input-pin');
    if (input) input.value = '';
    document.getElementById('modal-pin')?.classList.remove('hidden');
    setTimeout(() => document.getElementById('input-pin')?.focus(), 100);
};

window.cerrarModalPin = () => document.getElementById('modal-pin')?.classList.add('hidden');
window.cerrarModalConfig = () => document.getElementById('modal-config')?.classList.add('hidden');
window.cerrarModalProgreso = () => document.getElementById('modal-progreso')?.classList.add('hidden');

window.verificarPin = async () => {
    const pin = document.getElementById('input-pin')?.value;
    if (!pin) return;

    window.mostrarCarga('Verificando credenciales...');
    try {
        const res = await fetch('/woocommerce/verificar', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ pin })
        });
        const data = await res.json();
        window.ocultarCarga();

        if (res.ok && data.success) {
            cerrarModalPin();
            document.getElementById('modal-config')?.classList.remove('hidden');
        } else {
            window.mostrarToast(data.message || 'PIN de seguridad incorrecto', 'red');
            document.getElementById('input-pin').value = '';
        }
    } catch (e) { 
        window.ocultarCarga();
        window.mostrarToast('Error de conexión con el servidor', 'red'); 
    }
};

window.guardarConfiguracion = async () => {
    const form = document.getElementById('form-config');
    window.mostrarCarga('Guardando parámetros...');
    
    try {
        const res = await fetch('/woocommerce/configuracion/guardar', { 
            method: 'POST', 
            body: new FormData(form),
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });
        
        const data = await res.json();
        window.ocultarCarga();

        if (res.ok) {
            window.mostrarToast(data.message || 'Algoritmo actualizado.', 'green');
            cerrarModalConfig();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.mostrarToast(data.message || 'Error al guardar.', 'red');
        }
    } catch (e) { 
        window.ocultarCarga();
        window.mostrarToast('Fallo de red al intentar guardar.', 'red'); 
    }
};

// Descarga de Precios en Vivo (Sincronización Inversa)
window.descargarPreciosWoo = async () => {
    if (!confirm('¿Deseas extraer todos los precios actuales de WooCommerce? Este proceso se ejecutará en segundo plano.')) return;

    window.mostrarCarga('Iniciando tarea de extracción...');
    try {
        const response = await fetch('/woocommerce/api/descargar-precios', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'No se pudo iniciar la descarga.');

        // Reutilizamos la barra de progreso de la UI
        document.getElementById('status-api-container').classList.remove('hidden');
        document.getElementById('status-texto').innerText = 'Descargando catálogo en vivo...';
        
        window.ocultarCarga();
        trackearProgreso(data.log_id); 
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

// 7. Persistencia Automática de UI al recargar la página
document.addEventListener('DOMContentLoaded', () => {
    const activeLogInput = document.getElementById('active-log-id');
    if (activeLogInput && activeLogInput.value) {
        document.getElementById('status-api-container').classList.remove('hidden');
        trackearProgreso(activeLogInput.value);
    }
});

// Edición Individual Manual
window.abrirModalEdicion = (id, sku, normal, rebaja) => {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-sku').innerText = sku;
    document.getElementById('edit-normal').value = normal;
    document.getElementById('edit-rebaja').value = rebaja;
    document.getElementById('modal-edicion').classList.remove('hidden');
};

window.cerrarModalEdicion = () => document.getElementById('modal-edicion').classList.add('hidden');

window.guardarEdicionIndividual = async () => {
    const id = document.getElementById('edit-id').value;
    const normal = document.getElementById('edit-normal').value;
    const rebaja = document.getElementById('edit-rebaja').value;
    
    window.mostrarCarga('Sincronizando con WooCommerce...');
    
    try {
        const res = await fetch(`/woocommerce/api/producto/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ precio_normal: normal, precio_rebajado: rebaja })
        });
        
        const data = await res.json();
        window.ocultarCarga();

        if (res.ok) {
            window.mostrarToast(data.message, 'green');
            cerrarModalEdicion();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message); // El backend ahora devuelve el error real
        }
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

/**
 * Petición asíncrona para traer el precio de un solo producto desde la API.
 */
window.consultarPrecioIndividualWoo = async (id) => {
    window.mostrarCarga('Consultando precio en WooCommerce...');
    
    try {
        const res = await fetch(`/woocommerce/api/producto/${id}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        const data = await res.json();
        window.ocultarCarga();

        if (res.ok && data.success) {
            window.mostrarToast(data.message, 'green');
            // Recargamos para que la tabla refleje el nuevo dato. 
            // Para mayor optimización a futuro, podríamos actualizar el DOM directamente.
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Error al consultar la API.');
        }
    } catch (e) {
        window.ocultarCarga();
        window.mostrarToast(e.message, 'red');
    }
};

/**
 * Listener global para Componentes de Subida de Archivos (upload-area)
 * Detecta cambios en cualquier input con la clase .file-input-custom
 * y actualiza dinámicamente el label original sin alterar el DOM del componente.
 */
document.addEventListener('DOMContentLoaded', () => {
    const fileInputs = document.querySelectorAll('.file-input-custom');

    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            // El ID del input tiene el formato "file-[id]", extraemos el [id]
            const id = this.id.replace('file-', '');
            const labelElement = document.getElementById(`label-${id}`);

            if (labelElement) {
                if (this.files && this.files.length > 0) {
                    const fileName = this.files[0].name;

                    // Salvaguardar el texto original del label la primera vez
                    if (!labelElement.hasAttribute('data-original-text')) {
                        labelElement.setAttribute('data-original-text', labelElement.innerText);
                    }
                    
                    // Inyectar el nombre del archivo y aplicar clases para feedback visual
                    labelElement.textContent = '📄 ' + fileName;
                    labelElement.classList.remove('text-dark-muted');
                    labelElement.classList.add('text-green-400', 'font-bold');
                } else {
                    // Restaurar el componente si el usuario cancela la selección
                    if (labelElement.hasAttribute('data-original-text')) {
                        labelElement.textContent = labelElement.getAttribute('data-original-text');
                        labelElement.classList.remove('text-green-400', 'font-bold');
                        labelElement.classList.add('text-dark-muted');
                    }
                }
            }
        });
    });
});