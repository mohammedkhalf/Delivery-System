<?php

namespace Modules\AI\app\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;

class AIServiceException extends Exception implements ShouldntReport
{
    protected $code = 422;

    public function render(Request $request)
    {
        if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
            return response()->json([
                'errors' => [
                    ['code' => 'ai_service', 'message' => $this->getMessage()],
                ],
            ], 422);
        }

        return response($this->getMessage(), 422);
    }

    public function report()
    {
        return false;
    }
}
