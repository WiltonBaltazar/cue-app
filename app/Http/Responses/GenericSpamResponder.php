<?php

namespace App\Http\Responses;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Honeypot\SpamResponder\SpamResponder;

/**
 * Blocked submissions get a generic message that reveals nothing about the check (FR-033).
 */
class GenericSpamResponder implements SpamResponder
{
    public const MESSAGE = 'Não foi possível enviar o cadastro. Aguarde um momento e tente novamente.';

    public function respond(Request $request, Closure $next): RedirectResponse
    {
        return back()->withErrors(['form' => self::MESSAGE]);
    }
}
