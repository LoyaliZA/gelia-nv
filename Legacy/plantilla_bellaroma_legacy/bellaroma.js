document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-bellaroma');
    const fileExistencias = document.getElementById('file-existencias');
    const filePrecios = document.getElementById('file-precios');
    const buscador = document.getElementById('buscador-drive');

    // 1. Lógica de Generación de Plantilla
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!fileExistencias.files.length || !filePrecios.files.length) {
                window.mostrarToast('Sube ambos archivos primero', 'red');
                return;
            }

            const formData = new FormData(form);
            window.mostrarCarga("Cruzando datos y guardando en Drive...");

            try {
                const response = await fetch('/bellaroma/generar', {
                    method: 'POST',
                    body: formData,
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json' 
                    }
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.message || 'Error al procesar el Excel.');

                // Configuramos el Modal con la URL de descarga real
                const btnModal = document.getElementById('btn-descargar-modal');
                btnModal.href = data.download_url;
                
                // Animamos la apertura del modal
                const modal = document.getElementById('modal-descarga');
                modal.classList.remove('hidden');
                modal.querySelector('div').classList.remove('scale-95');
                modal.querySelector('div').classList.add('scale-100');
                
                window.mostrarToast('Plantilla guardada en Drive exitosamente.', 'green');

            } catch (error) {
                window.mostrarToast(error.message, 'red');
            } finally {
                window.ocultarCarga();
            }
        });
    }

    // 2. Buscador y Filtros Combinados del Drive
    const buscadorTexto = document.getElementById('buscador-drive');
    const filtroInicio = document.getElementById('filtro-fecha-inicio');
    const filtroFin = document.getElementById('filtro-fecha-fin');

    function filtrarDrive() {
        const term = buscadorTexto ? buscadorTexto.value.toLowerCase() : '';
        const fechaInicio = filtroInicio ? filtroInicio.value : ''; // Formato "YYYY-MM-DD"
        const fechaFin = filtroFin ? filtroFin.value : '';         // Formato "YYYY-MM-DD"
        
        // Seleccionamos solo las filas que tienen el atributo data-date
        const items = document.querySelectorAll('.fila-drive');
        
        items.forEach(item => {
            const text = item.innerText.toLowerCase();
            const itemDate = item.getAttribute('data-date');
            
            let mostrar = true;

            // Condición 1: Filtro de Texto
            if (term && !text.includes(term)) {
                mostrar = false;
            }
            
            // Condición 2: Fecha de Inicio (mayor o igual)
            if (fechaInicio && itemDate < fechaInicio) {
                mostrar = false;
            }
            
            // Condición 3: Fecha Fin (menor o igual)
            if (fechaFin && itemDate > fechaFin) {
                mostrar = false;
            }

            // Aplicamos la visibilidad
            item.style.display = mostrar ? 'flex' : 'none';
        });
    }

    // Escuchamos los 3 eventos para disparar el mismo filtro
    if(buscadorTexto) buscadorTexto.addEventListener('keyup', filtrarDrive);
    if(filtroInicio) filtroInicio.addEventListener('change', filtrarDrive);
    if(filtroFin) filtroFin.addEventListener('change', filtrarDrive);
});

// 3. Funciones Globales para Botones
window.cerrarModalDescarga = function() {
    const modal = document.getElementById('modal-descarga');
    modal.classList.add('hidden');
    // Recargamos para que el Drive muestre la plantilla recién creada
    window.location.reload(); 
};

window.abrirModalConfig = function() {
    window.mostrarToast('Panel de automatización y notificaciones en desarrollo (Fase 4).', 'orange');
}

window.eliminarPlantilla = async function(id) {
    if(!confirm('¿Estás seguro de eliminar este archivo permanentemente del servidor?')) return;
    
    window.mostrarCarga('Eliminando archivo...');
    try {
        const response = await fetch(`/bellaroma/eliminar/${id}`, {
            method: 'DELETE',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Error al eliminar del servidor.');
        
        window.mostrarToast('Archivo eliminado', 'green');
        setTimeout(() => window.location.reload(), 800);
    } catch(error) {
        window.ocultarCarga();
        window.mostrarToast(error.message, 'red');
    }
}

// ==========================================
// FASE 4: MINI-AUTH Y CONFIGURACIÓN
// ==========================================

window.abrirModalConfig = function() {
    const modal = document.getElementById('modal-config');
    // Reiniciar estado
    document.getElementById('step-auth').classList.remove('hidden');
    document.getElementById('step-config').classList.add('hidden');
    document.getElementById('input-pin-auth').value = '';
    
    modal.classList.remove('hidden');
    // Foco automático en el input de PIN
    setTimeout(() => document.getElementById('input-pin-auth').focus(), 100);
}

window.cerrarModalConfig = function() {
    document.getElementById('modal-config').classList.add('hidden');
}

// Permitir presionar "Enter" para verificar el PIN
document.getElementById('input-pin-auth')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') verificarPinConfig();
});

window.verificarPinConfig = async function() {
    const pin = document.getElementById('input-pin-auth').value;
    if(!pin) { window.mostrarToast('Ingresa el PIN de seguridad.', 'red'); return; }

    window.mostrarCarga('Verificando credenciales...');
    try {
        const response = await fetch('/bellaroma/configuracion/verificar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ pin: pin })
        });

        const data = await response.json();
        
        if (!response.ok) throw new Error(data.message || 'PIN incorrecto.');

        // Credenciales válidas: Guardamos el PIN actual y llenamos el formulario
        document.getElementById('config-pin-actual').value = pin;
        document.getElementById('config-hora').value = data.config.hora_notificacion || '';
        document.getElementById('config-correo').value = data.config.correo_destino || '';
        
        // Transición de vistas dentro del modal
        document.getElementById('step-auth').classList.add('hidden');
        document.getElementById('step-config').classList.remove('hidden');

    } catch(error) {
        window.mostrarToast(error.message, 'red');
        document.getElementById('input-pin-auth').value = '';
        document.getElementById('input-pin-auth').focus();
    } finally {
        window.ocultarCarga();
    }
}

window.guardarConfiguracion = async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    window.mostrarCarga('Guardando parámetros...');
    try {
        const response = await fetch('/bellaroma/configuracion/guardar', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Error al guardar la configuración.');

        window.mostrarToast('Ajustes guardados correctamente.', 'green');
        cerrarModalConfig();
        form.reset();
    } catch(error) {
        window.mostrarToast(error.message, 'red');
    } finally {
        window.ocultarCarga();
    }
}

// ==========================================
// FASE 5: MOTOR DE NOTIFICACIONES PUSH
// ==========================================

function iniciarMotorNotificaciones() {
    const horaObjetivo = window.BellaromaConfig?.horaNotificacion;
    if (!horaObjetivo) return; 

    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }

    let ultimaNotificacionMs = 0;

    setInterval(() => {
        if (Notification.permission !== 'granted') return;
        
        // Si el backend nos dijo que ya se generó hoy, cancelamos las alarmas por el resto del día.
        if (window.BellaromaConfig?.generadoHoy) return; 

        const ahora = new Date();
        const horaActualStr = ahora.getHours().toString().padStart(2, '0') + ':' + ahora.getMinutes().toString().padStart(2, '0');

        // Si la hora actual es MAYOR O IGUAL a la hora objetivo (Ej: Ya son las 08:05 y la meta era 08:00)
        if (horaActualStr >= horaObjetivo) {
            
            const tiempoTranscurrido = ahora.getTime() - ultimaNotificacionMs;
            
            // Si pasaron 10 minutos (600,000 milisegundos) desde la última vez que le avisamos
            if (tiempoTranscurrido >= 600000 || ultimaNotificacionMs === 0) {
                
                const notificacion = new Notification('🔔 Recordatorio Bellaroma', {
                    body: 'Aún no has generado la plantilla de hoy. Recuerda que las chicas están esperando. ¡Hazlo ahora!',
                    icon: 'https://cdn-icons-png.flaticon.com/512/732/732220.png',
                    tag: 'bellaroma-alerta', // IMPORTANTE: Esto evita el spam. Reemplaza la alerta previa.
                    requireInteraction: true 
                });

                notificacion.onclick = function() {
                    window.focus();
                    this.close();
                };

                // Guardamos el timestamp de este aviso para que el próximo sea en 10 min
                ultimaNotificacionMs = ahora.getTime(); 
            }
        }
    }, 30000); 
}

// Inicializar el motor cuando carga la página
document.addEventListener('DOMContentLoaded', () => {
    iniciarMotorNotificaciones();
});
