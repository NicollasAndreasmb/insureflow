<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Padroniza as respostas de erro da API no formato {message, errors?}.
 *
 * As mensagens padrão do framework expõem detalhes internos (ex.: "No query
 * results for model [App\Models\Client] 7") e estão em inglês; aqui elas são
 * substituídas por mensagens genéricas em português. Erros de validação mantêm
 * o formato nativo do Laravel, apenas com a mensagem geral traduzida.
 *
 * Erros 500 só exibem detalhes quando APP_DEBUG=true (desenvolvimento); com
 * APP_DEBUG=false o cliente recebe apenas uma mensagem genérica.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->json(
                __('http.validation_failed'), $exception->status, $exception->errors(),
            ),
            $exception instanceof AuthenticationException => $this->json(__('http.unauthenticated'), 401),
            $exception instanceof AuthorizationException => $this->json(__('http.forbidden'), 403),
            $exception instanceof ModelNotFoundException => $this->json(__('http.not_found'), 404),
            $exception instanceof TokenMismatchException => $this->json(__('http.session_expired'), 419),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            default => config('app.debug') ? null : $this->json(__('http.server_error'), 500),
        };
    }

    private function fromHttpException(HttpExceptionInterface $exception): ?JsonResponse
    {
        $status = $exception->getStatusCode();

        $message = match ($status) {
            400 => __('http.bad_request'),
            401 => __('http.unauthenticated'),
            403 => __('http.forbidden'),
            404 => __('http.not_found'),
            405 => __('http.method_not_allowed'),
            419 => __('http.session_expired'),
            429 => __('http.too_many_requests'),
            default => $status >= 500 ? __('http.server_error') : null,
        };

        return $message === null ? null : $this->json($message, $status, headers: $exception->getHeaders());
    }

    private function json(string $message, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }
}
