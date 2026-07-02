document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('file-transacciones');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if(fileInput.files.length > 0) {
                window.mostrarToast("Archivo de transacciones cargado.", "orange");
            }
        });
    }
});

// Procesamiento principal de la solicitud de transacciones
window.procesarSolicitudTransacciones = async function () {
    const fileInput = document.getElementById('file-transacciones');
    
    // 1. Validación frontend
    if (!fileInput || fileInput.files.length === 0) { 
        window.mostrarToast("Sube el archivo Excel o CSV de Transacciones", "red"); 
        return; 
    }

    // 2. Preparar los datos
    const form = document.getElementById('form-principal');
    const formData = new FormData(form);

    // 3. Ejecutar Petición
    window.mostrarCarga(`Limpiando y formateando transacciones...`);
    document.getElementById('alertas').innerHTML = '';

    try {
        const urlGenerar = window.GeliaConfig.routes.generar;
        const response = await fetch(urlGenerar, {
            method: 'POST',
            body: formData,
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, 
                'Accept': 'application/json' 
            }
        });

        if (!response.ok) {
            const data = await response.json();
            if (data.errors) {
                let html = `<ul class='list-disc ml-5'>`;
                Object.values(data.errors).forEach(err => html += `<li>${err}</li>`);
                html += `</ul>`;
                window.mostrarError(html);
            } else { 
                throw new Error(data.error || 'Error en el servidor al procesar las transacciones.'); 
            }
            window.ocultarCarga(); 
            return;
        }

        // 4. Descargar el Excel generado
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = downloadUrl;

        // Intentar obtener el nombre del archivo desde los headers
        const contentDisposition = response.headers.get('Content-Disposition');
        let fileName = `TRANSACCIONES-BANCARIAS.xlsx`;
        if (contentDisposition) {
            const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
            if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
        }

        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.ocultarCarga();
        window.mostrarToast("¡Transacciones Limpias Exitosamente!", "green");

    } catch (error) {
        console.error(error);
        window.ocultarCarga();
        window.mostrarToast("Error: " + error.message, "red");
    }
}