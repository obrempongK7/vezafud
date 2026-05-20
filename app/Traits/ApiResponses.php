<?php

namespace App\Traits;

trait ApiResponses
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, $message = null, $code = 200)
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($message, $code = 400, $data = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Handle exceptions consistently.
     *
     * @param \Exception $e
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleException(\Exception $e, $code = 400)
    {
        return $this->errorResponse($e->getMessage(), $code);
    }
}
