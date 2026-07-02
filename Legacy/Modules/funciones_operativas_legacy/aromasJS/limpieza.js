document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-limpieza');
    if (form) {
        form.addEventListener('submit', procesarLimpieza);
    }
});

async function procesarLimpieza(e) {
    e.preventDefault();

    const inputFile = document.getElementById('file-archivo_sucio');
    if (!inputFile || inputFile.files.length === 0) {
        window.mostrarToast("Debes seleccionar un archivo para limpiar.", "red");
        return;
    }

    const formData = new FormData(e.target);
    const btn = document.getElementById('btn-procesar-limpieza');
    const textoOriginal = btn.innerHTML;
    
    // Cambiamos estado del botón a "Cargando"
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Limpiando...`;
    
    window.mostrarCarga("Limpiando datos y generando archivo Excel...");
    document.getElementById('alertas').innerHTML = '';

    const tokenCSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                   || document.querySelector('input[name="_token"]')?.value;

    try {
        const response = await fetch(window.GeliaLimpieza.routes.procesar, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': tokenCSRF,
                'Accept': 'application/json'
            }
        });

        // Verificamos si la respuesta es JSON (usualmente un error de validación o del catch)
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
            const data = await response.json();
            if (!response.ok) {
                if (data.errors) {
                    let msg = Object.values(data.errors).flat().join('\n');
                    throw new Error(msg);
                }
                throw new Error(data.error || "Error al procesar el archivo");
            }
        }

        // Si todo va bien, recibimos un Blob (el archivo Excel)
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = downloadUrl;

        // Recuperar el nombre original propuesto por el backend (PRODUCTOS-LIMPIOS-fecha.xlsx)
        let fileName = 'PRODUCTOS-LIMPIOS.xlsx';
        const contentDisposition = response.headers.get('Content-Disposition');
        if (contentDisposition) {
            const match = contentDisposition.match(/filename="?([^"]+)"?/);
            if (match && match[1]) fileName = match[1];
        }

        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(downloadUrl);

        window.mostrarToast("Archivo limpio generado y descargado correctamente.", "green");
        e.target.reset(); // Limpiar el formulario

    } catch (error) {
        window.mostrarToast("Error: " + error.message, "red");
    } finally {
        window.ocultarCarga();
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    }
}