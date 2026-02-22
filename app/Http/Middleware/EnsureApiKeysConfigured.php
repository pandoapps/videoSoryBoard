<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyVault;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeysConfigured
{
    public function __construct(private ApiKeyVault $vault) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->vault->allConfigured($request->user()->id)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'API keys are not fully configured. Please visit the admin settings page.',
                ], 403);
            }

            return redirect()->route('admin.settings')
                ->with('warning', 'Please configure all API keys before proceeding.');
        }

        return $next($request);
    }
}
