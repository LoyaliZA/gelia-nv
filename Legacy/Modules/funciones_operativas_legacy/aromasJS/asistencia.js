document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('archivo_asistencia');
    const form = document.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');

    // 1. Mostrar el nombre del archivo seleccionado de forma robusta
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            
            if (fileName) {
                // Buscamos el contenedor padre más cercano (usualmente el <label> en Tailwind)
                const labelContainer = this.closest('label');
                
                if (labelContainer) {
                    // Verificamos si ya habíamos agregado el letrero de éxito
                    let nameDisplay = labelContainer.querySelector('.file-name-success');
                    
                    if (!nameDisplay) {
                        // Si no existe, creamos una etiqueta bonita para el archivo
                        nameDisplay = document.createElement('div');
                        nameDisplay.className = 'file-name-success mt-4 p-3 bg-indigo-900/40 border border-indigo-500 rounded-lg text-indigo-300 font-bold text-center w-full z-10 relative';
                        labelContainer.appendChild(nameDisplay);
                    }
                    
                    // Actualizamos el texto
                    nameDisplay.innerHTML = `✅ Archivo listo: <span class="text-white">${fileName}</span>`;
                }
            }
        });
    }

    // 2. Prevenir doble clic y mostrar estado de carga
    if (form) {
        form.addEventListener('submit', function() {
            if (submitBtn && fileInput.files.length > 0) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Procesando Archivo...
                `;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = `
                        Procesar y Descargar Limpio
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    `;
                }, 3000);
            }
        });
    }
});