<?php

namespace Tests\Support\PuntoVenta;

use Illuminate\Contracts\Broadcasting\Broadcaster;

final class CapturadorBroadcastPdv implements Broadcaster
{
    /** @var list<array{channels: array<int, string>, event: string, payload: array<string, mixed>}> */
    public array $emisiones = [];

    public function auth($request)
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $this->emisiones[] = [
            'channels' => array_map(
                fn ($channel) => is_object($channel) && property_exists($channel, 'name')
                    ? (string) $channel->name
                    : (string) $channel,
                $channels,
            ),
            'event' => (string) $event,
            'payload' => $payload,
        ];
    }

    public function reiniciar(): void
    {
        $this->emisiones = [];
    }

    /**
     * @return list<array{channels: array<int, string>, event: string, payload: array<string, mixed>}>
     */
    public function filtrarPorEvento(string $evento): array
    {
        return array_values(array_filter(
            $this->emisiones,
            fn (array $emision) => $emision['event'] === $evento,
        ));
    }

    public function contieneCanal(string $nombreCanal): bool
    {
        foreach ($this->emisiones as $emision) {
            if (in_array($nombreCanal, $emision['channels'], true)) {
                return true;
            }
        }

        return false;
    }
}
