<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Support\Facades\Storage;

class EliminarBultosEmpaquePedidoBmaService
{
    public function ejecutar(PedidoBma $pedido): void
    {
        $pedido->loadMissing(['bultosEmpaque.documentos']);

        foreach ($pedido->bultosEmpaque as $bulto) {
            foreach ($bulto->documentos as $documento) {
                $this->eliminarArchivo($documento);
                $documento->delete();
            }
            $bulto->delete();
        }

        $pedido->documentos()
            ->where('relacion_tipo', PedidoBmaDocumento::RELACION_BULTO_EMPAQUE)
            ->each(function (PedidoBmaDocumento $documento) {
                $this->eliminarArchivo($documento);
                $documento->delete();
            });
    }

    private function eliminarArchivo(PedidoBmaDocumento $documento): void
    {
        if ($documento->ruta_archivo && Storage::disk('public')->exists($documento->ruta_archivo)) {
            Storage::disk('public')->delete($documento->ruta_archivo);
        }
    }
}
