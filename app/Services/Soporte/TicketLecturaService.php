<?php

namespace App\Services\Soporte;

use App\Models\User;
use App\Models\SoporteTicket;
use App\Models\SoporteTicketLectura;
use App\Models\SoporteTicketInteraccion;
use Illuminate\Support\Collection;

class TicketLecturaService
{
    public function hasUnread(SoporteTicket $ticket, User $user): bool
    {
        $lastRead = SoporteTicketLectura::where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        return SoporteTicketInteraccion::where('ticket_id', $ticket->id)
            ->where('user_id', '!=', $user->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->exists();
    }

    public function markAsRead(SoporteTicket $ticket, User $user): void
    {
        SoporteTicketLectura::updateOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $user->id],
            ['last_read_at' => now()]
        );
    }

    public function annotateCollection(Collection $tickets, User $user): Collection
    {
        return $tickets->map(function (SoporteTicket $ticket) use ($user) {
            $ticket->has_unread = $this->hasUnread($ticket, $user);

            return $ticket;
        });
    }
}
