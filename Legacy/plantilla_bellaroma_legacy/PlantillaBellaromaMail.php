<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlantillaBellaromaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $rutaFisica;
    public $nombreArchivo;

    public function __construct($rutaFisica, $nombreArchivo)
    {
        $this->rutaFisica = $rutaFisica;
        $this->nombreArchivo = $nombreArchivo;
    }

    public function build()
    {
        return $this->subject('Nueva Plantilla de Bellaroma Generada')
                    ->view('emails.plantilla-bellaroma') // La vista que crearemos ahora
                    ->attach(storage_path('app/public/' . $this->rutaFisica), [
                        'as' => $this->nombreArchivo,
                    ]);
    }
}