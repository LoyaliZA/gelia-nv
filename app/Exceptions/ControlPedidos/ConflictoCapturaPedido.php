<?php
namespace App\Exceptions\ControlPedidos;

class ConflictoCapturaPedido extends \RuntimeException
{
    public function __construct(string $message = 'El pedido cambió o la captura no tiene una base confirmada. Conserve sus cambios y recargue para revisarlos.')
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        return response()->json(['message' => $this->getMessage(), 'codigo' => 'conflicto_captura'], 409);
    }
}
