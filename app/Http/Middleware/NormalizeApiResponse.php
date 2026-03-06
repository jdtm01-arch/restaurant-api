<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiResponse
{
    /**
     * Normaliza respuestas API al contrato:
     * success/data/message/meta (éxito) y success/error (error).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldNormalize($request, $response)) {
            return $response;
        }

        $status = $response->getStatusCode();

        // 204 no content se conserva intacto
        if ($status === 204) {
            return $response;
        }

        $payload = $response instanceof JsonResponse
            ? $response->getData(true)
            : json_decode((string) $response->getContent(), true);

        if (! is_array($payload)) {
            $payload = ['data' => $payload];
        }

        if (array_key_exists('success', $payload)) {
            return $response;
        }

        if ($status >= 400) {
            $normalized = $this->normalizeError($payload, $status);

            $response->setContent(json_encode($normalized, JSON_UNESCAPED_UNICODE));

            return $response;
        }

        $normalized = $this->normalizeSuccess($payload);

        $response->setContent(json_encode($normalized, JSON_UNESCAPED_UNICODE));

        return $response;
    }

    private function shouldNormalize(Request $request, Response $response): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json') || $response instanceof JsonResponse;
    }

    private function normalizeSuccess(array $payload): array
    {
        // Caso paginado nativo de Laravel
        if (array_key_exists('data', $payload)
            && array_key_exists('current_page', $payload)
            && array_key_exists('per_page', $payload)
            && array_key_exists('last_page', $payload)
            && array_key_exists('total', $payload)) {

            return [
                'success' => true,
                'data'    => $payload['data'],
                'meta'    => [
                    'current_page' => $payload['current_page'],
                    'per_page'     => $payload['per_page'],
                    'last_page'    => $payload['last_page'],
                    'total'        => $payload['total'],
                    'from'         => $payload['from'] ?? null,
                    'to'           => $payload['to'] ?? null,
                ],
                // Compatibilidad temporal con tests y clientes legados
                'current_page' => $payload['current_page'],
                'per_page'     => $payload['per_page'],
                'last_page'    => $payload['last_page'],
                'total'        => $payload['total'],
                'from'         => $payload['from'] ?? null,
                'to'           => $payload['to'] ?? null,
            ];
        }

        $normalized = [
            'success' => true,
            'data'    => array_key_exists('data', $payload) ? $payload['data'] : $payload,
        ];

        // Compatibilidad temporal: si el payload era plano (ej. login con token/user),
        // se conservan sus claves al nivel raíz además del contrato estandarizado.
        if (! array_key_exists('data', $payload)
            && ! array_key_exists('message', $payload)
            && ! array_key_exists('meta', $payload)) {
            $normalized = array_merge($normalized, $payload);
        }

        if (array_key_exists('message', $payload)) {
            $normalized['message'] = $payload['message'];
        }

        if (array_key_exists('meta', $payload) && is_array($payload['meta'])) {
            $normalized['meta'] = $payload['meta'];
        }

        return $normalized;
    }

    private function normalizeError(array $payload, int $status): array
    {
        if (isset($payload['error']) && is_array($payload['error'])) {
            return [
                'success' => false,
                'error'   => $payload['error'],
                'errors'  => $payload['errors'] ?? null,
            ];
        }

        $message = $payload['message'] ?? 'Error en la solicitud.';

        return [
            'success' => false,
            'error'   => [
                'message' => $message,
                'code'    => $payload['code'] ?? $status,
            ],
            'errors' => $payload['errors'] ?? null,
        ];
    }
}
