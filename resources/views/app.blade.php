<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@400;700&family=Montserrat:wght@400;700;900&family=Nunito:wght@400;700;900&family=Poppins:wght@400;700;900&family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <title>GELIA</title>

    <script>
        (function () {
            var KEY = 'theme_font_scale', MIN = 0.875, MAX = 1.5, STEP = 0.0625, DEF = 1;
            var raw = localStorage.getItem(KEY);
            var n = raw !== null && raw !== '' ? parseFloat(raw) : DEF;
            if (!Number.isFinite(n)) n = DEF;
            n = Math.round(n / STEP) * STEP;
            n = Math.max(MIN, Math.min(MAX, Number(n.toFixed(4))));
            document.documentElement.style.setProperty('--font-scale', String(n));
        })();
    </script>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg" id="dynamic-favicon">
    <link rel="apple-touch-icon" href="/favicon-192.png">
    <link rel="manifest" href="/manifest.json">

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Esta función captura el color actual de React y actualiza el icono
            const actualizarFaviconDinamico = () => {
                const faviconLink = document.getElementById('dynamic-favicon');
                if (!faviconLink) return;

                // Capturar el valor de la variable CSS --color-primario definida en el body/html
                let colorTema = window.getComputedStyle(document.documentElement).getPropertyValue('--color-primario').trim();
                
                // Si React aún no carga o no hay color, usar Rosa Gelia por defecto
                if (!colorTema) colorTema = '#ec4899'; 

                // Dibujar el XML del SVG inyectando el color detectado (${colorTema})
                const svgXML = `<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <g fill="${colorTema}">
                        <polygon points="30,10 70,10 30,30" opacity="1.0" />
                        <polygon points="10,30 30,30 10,70" opacity="1.0" />
                        <polygon points="30,90 30,70 70,90" opacity="1.0" />
                        <polygon points="90,70 70,70 90,50" opacity="1.0" />
                        <polygon points="70,50 70,70 50,50" opacity="1.0" />
                        <polygon points="70,10 70,30 30,30" opacity="0.6" />
                        <polygon points="30,30 30,70 10,70" opacity="0.6" />
                        <polygon points="30,70 70,70 70,90" opacity="0.6" />
                        <polygon points="70,70 70,50 90,50" opacity="0.6" />
                        <polygon points="70,10 90,30 70,30" opacity="0.3" />
                        <polygon points="30,10 30,30 10,30" opacity="0.3" />
                        <polygon points="10,70 30,70 30,90" opacity="0.3" />
                        <polygon points="70,90 70,70 90,70" opacity="0.3" />
                    </g>
                </svg>`;

                // Convertir el XML a una URI Base64 segura para el navegador
                const blob = new Blob([svgXML], {type: 'image/svg+xml'});
                const url = URL.createObjectURL(blob);
                
                // Actualizar el href del link para cambiar el icono al instante
                faviconLink.href = url;
            };

            // Ejecutar una vez al cargar la página
            actualizarFaviconDinamico();

            // Opcional Avanzado: Si tienes un cambiador de tema en tiempo real en React, 
            // necesitamos escuchar los cambios en el atributo 'style' o 'class' del html.
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                        actualizarFaviconDinamico();
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });
        });
    </script>
    
    <!-- Aquí inyectamos las rutas de Laravel para que React las pueda usar -->
    @routes

    <!-- Directivas esenciales de Vite e Inertia -->
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <!-- Aquí es donde React será montado -->
    @inertia
</body>
</html>