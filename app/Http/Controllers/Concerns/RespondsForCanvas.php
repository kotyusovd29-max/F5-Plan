<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RespondsForCanvas
{
    protected function respondForCanvas(
        Request $request,
        RedirectResponse $redirect,
        string $message = 'Сохранено'
    ): RedirectResponse|JsonResponse {
        if (
            $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->header('X-Canvas-Request')
        ) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return $redirect;
    }
}
