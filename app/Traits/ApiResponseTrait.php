<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Standard success JSON response.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Clean paginated JSON response without double-wrapping.
     * Exposes 'data', 'meta', and 'links' at the top level.
     */
    protected function paginated(mixed $resource, string $message = 'Success', int $code = 200): JsonResponse
    {
        $payload = $resource->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $payload['data'] ?? [],
            'meta'    => $payload['meta'] ?? null,
            'links'   => $payload['links'] ?? null,
        ], $code);
    }

    /**
     * Standard error JSON response.
     */
    protected function error(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
