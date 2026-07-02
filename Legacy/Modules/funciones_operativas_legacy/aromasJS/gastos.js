document.addEventListener('DOMContentLoaded', () => {
    // Listener opcional para feedback visual rápido al subir el Excel
    const fileInput = document.getElementById('file-gastos');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if(fileInput.files.length > 0) {
                window.mostrarToast("Archivo de gastos cargado.", "green");
            }
        });
    }
});

// Procesamiento principal de la solicitud de gastos
window.procesarSolicitudGastos = async function () {
    const fileInput = document.getElementById('file-gastos');
    
    // 1. Validación frontend
    if (!fileInput || fileInput.files.length === 0) { 
        window.mostrarToast("Sube el archivo Excel de Gastos", "red"); 
        return; 
    }

    // 2. Preparar los datos
    const form = document.getElementById('form-principal');
    const formData = new FormData(form);
    
    // Asegurarnos de mandar el filtro seleccionado
    const filtro = document.getElementById('filtro-tipo').value;
    formData.set('filtro_tipo', filtro);

    // 3. Ejecutar Petición
    window.mostrarCarga(`Generando reporte de ${filtro === 'TODOS' ? 'Gastos' : filtro}...`);
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
                throw new Error(data.error || 'Error en el servidor al procesar el archivo de gastos.'); 
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
        let fileName = `REPORTE-GASTOS.xlsx`;
        if (contentDisposition) {
            const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
            if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
        }

        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.ocultarCarga();
        window.mostrarToast("¡Reporte de Gastos Generado Exitosamente!", "green");

    } catch (error) {
        console.error(error);
        window.ocultarCarga();
        window.mostrarToast("Error: " + error.message, "red");
    }
}