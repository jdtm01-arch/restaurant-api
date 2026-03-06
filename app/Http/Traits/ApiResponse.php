<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait estandarizado para respuestas API consistentes.
 *
 * Uso:
 *   return $this->success($data);
 *   return $this->created($data, 'Recurso creado');
 *   return $this->error('Algo falló', 500);
 *   return $this->noContent();
 */
trait ApiResponse
{
    /**
     * Respuesta exitosa (200).
     */
    protected function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * Recurso creado (201).
     */
    protected function created(mixed $data = null, string $message = 'Recurso creado exitosamente'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Sin contenido (204).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Respuesta paginada estandarizada.
     */
    protected function paginated($paginator, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * Error (genérico).
     */
    protected function error(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code'    => $code,
            ],
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
