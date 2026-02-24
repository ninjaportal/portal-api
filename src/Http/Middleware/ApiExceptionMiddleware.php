<?php

namespace NinjaPortal\Api\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * API Exception Middleware
 *
 * Catches exceptions and returns consistent JSON error responses.
 * Uses the improved response mixin methods.
 */
class ApiExceptionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            return response()->errors('Validation failed.', $e->errors(), 422);
        } catch (AuthenticationException | UnauthorizedHttpException $e) {
            return response()->unauthorized('Unauthenticated.');
        } catch (JWTException $e) {
            return response()->unauthorized('Unauthenticated.');
        } catch (RouteNotFoundException $e) {
            if (str_contains($e->getMessage(), 'Route [login] not defined')) {
                return response()->unauthorized('Unauthenticated.');
            }

            return response()->errors('Route not found.', [], 404);
        } catch (AuthorizationException | SpatieUnauthorizedException $e) {
            return response()->forbidden('Forbidden.');
        } catch (ModelNotFoundException $e) {
            return response()->notFound('Resource not found.');
        } catch (HttpExceptionInterface $e) {
            $message = $e->getMessage() ?: 'Request failed.';
            return response()->errors($message, [], $e->getStatusCode());
        } catch (Throwable $e) {
            logger()->error('API Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->errors(
                config('app.debug') ? $e->getMessage() : 'Internal server error.',
                config('app.debug') ? ['trace' => $e->getTraceAsString()] : [],
                500
            );
        }
    }
}
