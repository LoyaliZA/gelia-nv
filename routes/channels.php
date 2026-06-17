<?php

use App\Models\ConversacionParticipante;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversacion.{conversacionId}', function ($user, $conversacionId) {
    return ConversacionParticipante::where('conversacion_id', $conversacionId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('solicitudes.facturas', function ($user) {
    return $user->hasPermissionTo('facturas.ver_listado');
});

Broadcast::channel('solicitudes.operativas', function ($user) {
    return $user->hasPermissionTo('cancelaciones_cotizaciones.ver_listado');
});

