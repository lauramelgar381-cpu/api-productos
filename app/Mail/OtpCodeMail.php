<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu código de acceso: '.$this->code);
    }

    public function content(): Content
    {
        $nombre = e(config('app.name'));

        return new Content(
            htmlString: <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 420px; margin: 0 auto; padding: 24px;">
                <h2 style="margin: 0 0 12px;">{$nombre}</h2>
                <p>Usa este código para iniciar sesión:</p>
                <p style="font-size: 32px; font-weight: bold; letter-spacing: 6px; margin: 16px 0;">{$this->code}</p>
                <p style="color: #64748b; font-size: 14px;">
                    Vence en 10 minutos. Si no lo solicitaste, ignora este mensaje.
                </p>
            </div>
            HTML
        );
    }
}
