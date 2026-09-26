<?php

namespace Modules\AI\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AI\app\Core\AiModule;
use Symfony\Component\HttpFoundation\Response;

class AiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AiModule::isChatActive()) {
            return response()->json([
                'errors' => [[
                    'code'    => 'ai_disabled',
                    'message' => 'AI chat is currently disabled.',
                ]],
            ], 503);
        }

        return $next($request);
    }
}
