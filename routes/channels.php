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

Broadcast::channel('solicitudes.traspasos', function ($user) {
    return $user->hasPermissionTo('traspasos.ver_listado');
});

Broadcast::channel('solicitudes.operativas', function ($user) {
    return $user->hasPermissionTo('cancelaciones_cotizaciones.ver_listado');
});

Broadcast::channel('cobranza.ejecucion', function ($user) {
    return $user->can('cobranza.ejecutar_llamadas')
        || $user->can('cobranza.ver_admin')
        || $user->can('cobranza.ver');
});

Broadcast::channel('soporte.agentes', function ($user) {
    return $user->hasPermissionTo('soporte.gestionar');
});

Broadcast::channel('soporte.ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\SoporteTicket::find($ticketId);
    if (!$ticket) {
        return false;
    }

    if ((int) $ticket->user_id === (int) $user->id) {
        return true;
    }

    return $user->can('soporte.gestionar');
});
