<?php

namespace App\Http\Middleware;

use App\Modules\Administration\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditTrailMiddleware
{
    /**
     * Log mutating API requests for compliance and traceability.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $user = $request->user();
        $route = $request->route();
        $routeName = $route?->getName();
        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');
        $module = $this->resolveModule($path);

        $payload = $this->sanitizePayload($request->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            '_token',
        ]));

        $responsePayload = $this->summarizeResponse($response->getContent());

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $this->resolveAction($method, $routeName, $path),
            'module' => $module,
            'route_name' => $routeName,
            'method' => $method,
            'path' => $path,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'request_payload' => $payload ?: null,
            'response_payload' => $responsePayload,
        ]);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function resolveModule(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (empty($segments)) {
            return 'system';
        }

        return $segments[0] === 'my' && isset($segments[1]) ? $segments[1] : $segments[0];
    }

    private function resolveAction(string $method, ?string $routeName, string $path): string
    {
        if ($routeName) {
            return $routeName;
        }

        return strtolower($method) . ' ' . $path;
    }

    private function sanitizePayload(array $payload): array
    {
        array_walk_recursive($payload, function (&$value): void {
            if (is_object($value)) {
                $value = method_exists($value, 'getClientOriginalName')
                    ? [
                        'name' => $value->getClientOriginalName(),
                        'size' => $value->getSize(),
                        'mime' => $value->getClientMimeType(),
                    ]
                    : get_class($value);
            }
        });

        return $payload;
    }

    private function summarizeResponse(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return null;
        }

        return [
            'success' => $decoded['success'] ?? null,
            'message' => $decoded['message'] ?? null,
        ];
    }
}
