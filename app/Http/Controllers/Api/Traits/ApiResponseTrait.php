<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Http\JsonResponse;

/**
 * API Response Trait
 *
 * Egységes JSON response formátum minden API controllerhez.
 *
 * Használat:
 * ```php
 * class MyController extends Controller
 * {
 *     use ApiResponseTrait;
 *
 *     public function index()
 *     {
 *         return $this->successResponse($data, 'Lista sikeresen lekérve');
 *     }
 *
 *     public function store(Request $request)
 *     {
 *         try {
 *             // ...
 *             return $this->successResponse($item, 'Létrehozva', 201);
 *         } catch (\Exception $e) {
 *             return $this->errorResponse($e->getMessage());
 *         }
 *     }
 * }
 * ```
 *
 * Response formátum:
 * ```json
 * {
 *     "success": true|false,
 *     "message": "string",
 *     "data": mixed (opcionális),
 *     "errors": array (opcionális, validation error esetén)
 * }
 * ```
 */
trait ApiResponseTrait
{
    /**
     * Sikeres válasz egységes formátumban.
     *
     * @param mixed $data Visszaadandó adat (opcionális)
     * @param string $message Sikeres üzenet
     * @param int $code HTTP státusz kód (default: 200)
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Sikeres',
        int $code = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Sikeres válasz adat nélkül.
     *
     * @param string $message Sikeres üzenet
     * @param int $code HTTP státusz kód (default: 200)
     */
    protected function successMessageResponse(
        string $message = 'Sikeres',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $code);
    }

    /**
     * Hiba válasz egységes formátumban.
     *
     * @param string $message Hiba üzenet
     * @param int $code HTTP státusz kód (default: 400)
     * @param array $errors Részletes hibák (opcionális)
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * 404 Not Found válasz.
     */
    protected function notFoundResponse(string $message = 'Nem található'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * 401 Unauthorized válasz.
     */
    protected function unauthorizedResponse(string $message = 'Azonosítás szükséges'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * 403 Forbidden válasz.
     */
    protected function forbiddenResponse(string $message = 'Nincs jogosultság'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * 422 Validation error válasz.
     */
    protected function validationErrorResponse(string $message, array $errors = []): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * 201 Created válasz.
     */
    protected function createdResponse(mixed $data = null, string $message = 'Létrehozva'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * 204 No Content válasz (DELETE után).
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Paginated response wrapper.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @param string $message
     */
    protected function paginatedResponse($paginator, string $message = 'Lista sikeresen lekérve'): JsonResponse
    {
        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $message);
    }
}
