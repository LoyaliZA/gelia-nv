document.addEventListener('DOMContentLoaded', () => {
    // 1. Blindaje nativo de los Dropzones (Drag & Drop y Selección)
    document.querySelectorAll('input[type="file"]').forEach(input => {
        const dropzone = input.closest('label'); // Contenedor principal del componente
        
        if (dropzone) {
            // Prevenir que el navegador abra el archivo en una nueva pestaña
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
            });

            // Efectos visuales al arrastrar por encima
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.add('border-cyan-500', 'bg-dark-700/50'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.remove('border-cyan-500', 'bg-dark-700/50'), false);
            });

            // Captura de archivos por Drag & Drop
            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change')); // Disparar el cambio visual
                }
            }, false);
        }

        // 2. Feedback Visual de Archivo Cargado
        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                // Buscar el contenedor de instrucciones para sobreescribirlo temporalmente
                const textContainer = dropzone.querySelector('p.text-sm') || dropzone.querySelector('.text-dark-muted');
                if (textContainer) {
                    textContainer.innerHTML = `<span class="text-emerald-400 font-bold">✓ Archivo listo:</span> <span class="text-white">${fileName}</span>`;
                }
            }
        });
    });
});

window.procesarAviso = async function () {
    const fileOrden = document.getElementById('file-orden_compra')?.files.length > 0;
    const fileAviso = document.getElementById('file-aviso_mercancia')?.files.length > 0;
    const container = document.getElementById('resultados-container');

    if (!fileOrden || !fileAviso) {
        window.mostrarToast("Debes subir ambos archivos.", "red");
        return;
    }

    const form = document.getElementById('form-avisos');
    const formData = new FormData(form);

    window.mostrarCarga("Cruzando SKUs y generando tabla...");
    document.getElementById('alertas').innerHTML = '';
    container.classList.add('hidden'); // Ocultar resultados previos

    const tokenCSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value;

    try {
        const response = await fetch(window.GeliaConfigAvisos.routes.procesar, {
            method: 'POST',
            body: formData,
            headers: { 
                'X-CSRF-TOKEN': tokenCSRF, 
                'Accept': 'application/json' 
            }
        });

        if (response.status === 419) {
            window.mostrarToast("Sesión caducada. Recargando...", "orange");
            setTimeout(() => window.location.reload(), 2000);
            return;
        }

        const data = await response.json(); // Leemos siempre JSON

        if (!response.ok) {
            if (data.errors) {
                let html = `<ul class='list-disc ml-5'>`;
                Object.values(data.errors).forEach(err => html += `<li>${err}</li>`);
                html += `</ul>`;
                window.mostrarError(html);
            } else {
                window.mostrarToast(data.error || 'Error en el procesamiento.', "orange");
            }
            window.ocultarCarga(); 
            return;
        }

        // ÉXITO: Renderizar la tabla
        window.ocultarCarga();
        renderizarTablaResultados(data.data, data.count);
        window.mostrarToast(`Cruce exitoso. Se encontraron ${data.count} SKUs.`, "green");

    } catch (error) {
        window.ocultarCarga();
        window.mostrarToast("Error crítico: " + error.message, "red");
    }
}

// NUEVO: Función constructora de la tabla UI
function renderizarTablaResultados(productos, total) {
    const container = document.getElementById('resultados-container');
    
    // Iconos SVG reutilizables
    const iconCopy = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>';
    const iconCheck = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';

    let html = `
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="bg-cyan-500 w-2 h-7 rounded"></span>
                Mercancía Encontrada en Orden de Compra
            </h2>
            <span class="px-4 py-1.5 rounded-full bg-cyan-950 text-cyan-300 border border-cyan-800 text-xs font-bold shadow-inner">
                ${total} coincidencias
            </span>
        </div>

        <div class="bg-dark-800 border border-dark-700 rounded-2xl shadow-2xl overflow-hidden">
            <div class="overflow-x-auto custom-scroll">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-dark-900/50 text-dark-muted uppercase text-[10px] font-bold tracking-wider">
                        <tr>
                            <th class="px-5 py-4 text-center w-16">Acción</th>
                            <th class="px-5 py-4 w-40">SKU / UPC</th>
                            <th class="px-5 py-4">Descripción del Producto (Gelia)</th>
                            <th class="px-5 py-4 text-center w-32">Cant. Llegó</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-700">
    `;

    // Construcción optimizada de filas (N escape de caracteres para seguridad)
    productos.forEach((prod, index) => {
        const skuEscapado = prod['SKU'].replace(/'/g, "\\'"); // Escapar comillas simples para JS
        html += `
            <tr class="hover:bg-dark-700/50 transition duration-150 ${index % 2 === 0 ? '' : 'bg-dark-900/20'}">
                <td class="px-5 py-3 text-center">
                    <button type="button" 
                            onclick="copiarSku(this, '${skuEscapado}')" 
                            title="Copiar SKU"
                            class="group p-2 rounded-lg bg-dark-900 border border-dark-700 hover:border-cyan-600 hover:bg-cyan-950 text-dark-muted hover:text-cyan-300 transition-all duration-300 shadow-md">
                        <span class="icon-container">${iconCopy}</span>
                    </button>
                </td>
                <td class="px-5 py-3 font-mono text-cyan-400 font-medium tracking-tight bg-dark-950/30 rounded-lg shadow-inner">${prod['SKU']}</td>
                <td class="px-5 py-3 truncate max-w-xl" title="${prod['Descripción']}">${prod['Descripción']}</td>
                <td class="px-5 py-3 text-center font-bold text-lg text-emerald-400">${prod['Piezas Recibidas']}</td>
            </tr>
        `;
    });

    html += `
                    </tbody>
                </table>
            </div>
            <div class="p-4 bg-dark-900/50 border-t border-dark-700 text-right text-xs text-dark-muted italic">
                Resumen generado automáticamente por Gelia Hub.
            </div>
        </div>
    `;

    container.innerHTML = html;
    container.classList.remove('hidden');
    
    // Scroll suave hacia los resultados
    setTimeout(() => {
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

// NUEVO: Función de copiado inteligente con feedback visual
window.copiarSku = function (btn, sku) {
    const iconContainer = btn.querySelector('.icon-container');
    const iconCopy = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>';
    const iconCheck = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';

    // Lógica de copiado (Moderna con Fallback Legacy para HTTP)
    let copiadoExitoso = false;
    
    if (navigator.clipboard && window.isSecureContext) {
        // Entorno seguro (HTTPS)
        navigator.clipboard.writeText(sku);
        copiadoExitoso = true;
    } else {
        // Fallback (HTTP - Servidor de pruebas)
        try {
            const textArea = document.createElement("textarea");
            textArea.value = sku;
            textArea.style.position = "fixed"; textArea.style.opacity = "0"; // Invisible
            document.body.appendChild(textArea);
            textArea.focus(); textArea.select();
            copiadoExitoso = document.execCommand('copy');
            document.body.removeChild(textArea);
        } catch (err) { copiadoExitoso = false; }
    }

    if (copiadoExitoso) {
        // FEEDBACK VISUAL: Cambiar icono a palomita y estilo
        btn.classList.add('border-emerald-600', 'bg-emerald-950', 'text-emerald-300');
        btn.classList.remove('hover:border-cyan-600', 'hover:bg-cyan-950', 'text-dark-muted', 'hover:text-cyan-300');
        iconContainer.innerHTML = iconCheck;

        // Revertir a estado original tras 2 segundos
        setTimeout(() => {
            btn.classList.remove('border-emerald-600', 'bg-emerald-950', 'text-emerald-300');
            btn.classList.add('hover:border-cyan-600', 'hover:bg-cyan-950', 'text-dark-muted', 'hover:text-cyan-300');
            iconContainer.innerHTML = iconCopy;
        }, 2000);
    } else {
        window.mostrarToast("Error al copiar al portapapeles.", "red");
    }
}