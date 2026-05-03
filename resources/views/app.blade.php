<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GELIA</title>
    
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