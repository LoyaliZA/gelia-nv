<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@400;700&family=Montserrat:wght@400;700;900&family=Nunito:wght@400;700;900&family=Poppins:wght@400;700;900&family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <title>GELIA</title>
    
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