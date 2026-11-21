<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /** API routes always answer in JSON, whatever the Accept header. */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    public function register(): void
    {
        // Do not leak model class names in 404 messages.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });
    }
}
