<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    /**
     * Redirect the user to the screen for their role after logging in with a passkey.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $redirect = redirect()->intended($request->user()->role->homePath());

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $redirect->getTargetUrl()]);
        }

        return $redirect;
    }
}
