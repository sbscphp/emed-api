<?php

namespace App\Responser;

use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;

class JsonResponser
{

    /**
     * Return a new JSON response with paginated data
     *
     * @param int $status
     * @param mixed $data
     * @param string|null $message
     * @return Illuminate\Http\JsonResponse
     */
    public static function sendPaginated(
        int $status,
        $data = [],
        string $message = ""
    ): JsonResponse {
        $data = $data->toArray();
        $response = [
            'status' => $status,
            'data' => $data['data'],
            'meta' => $data['meta'],
            "message" => ucwords($message),
        ];
        return response()->json($response, $status);
    }

    /**
     * Return a new JSON response with paginated data
     *
     * @param bool $error
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param \Throwable|null $th
     * @return Illuminate\Http\JsonResponse
     */
    public static function send(
        bool $error = true,
        string $message = "",
        $data = [],
        int $statusCode = 200,
        ?\Throwable $th = null
    ): JsonResponse {
        if ($th && $statusCode === 500) {
            ErrorLog::create([
                'causer' => optional(auth()->user())->id ?? 'Guest',
                'model' => get_class($th),
                'error_message' => $th->getMessage(),
                'error_line' => $th->getLine(),
                'error_trace' => $th->getTraceAsString(),
                'request_url' => request()->fullUrl() ?? 'N/A',
                'request_method' => request()->method() ?? 'N/A',
                'request_data' => !empty(request()->all()) ? json_encode(request()->all()) : null,
                'request_ip' => request()->ip() ?? 'N/A',
                'user_agent' => request()->header('User-Agent') ?? 'N/A',
            ]);
        }

        $response = [
            "error" => $error,
            "message" => $error ? $message : ucwords($message),
            "data" => $data,
        ];

        // Include exception details if available
        if ($th) {
            $response['exception'] = [
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                // 'trace' => $th->getTrace(),
            ];
        }

        return response()->json($response, $statusCode);
    }
}

//     public static function send(
//         bool $error = true,
//         string $message = "",
//         $data = [],
//         $statusCode = 200,
//         $th = null
//     ): JsonResponse {
//         if($th && $statusCode == 500){
//             ErrorLog::create([
//                 'causer' => optional(auth()->user())->id ?? 'Guest',
//                 'model' => get_class($th),
//                 'error_message' => $th->getMessage(),
//                 'error_line' => $th->getLine(),
//                 'error_trace' => $th->getTraceAsString(),
//             ]);
//         }
//         return response()->json([
//             "error" => $error,
//             "message" => $message,
//             "data" => $data,
//         ], $statusCode);
//     }
// }
