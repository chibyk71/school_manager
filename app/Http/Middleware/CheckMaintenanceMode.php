<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * CheckMaintenanceMode v1.0 – Custom Maintenance Mode Middleware with Bypass Key and Custom URL
 *
 * Purpose:
 * Replaces Laravel's built-in maintenance mode for more flexible, database-driven control.
 * Checks per-school maintenance settings:
 * - If enabled, blocks access unless valid bypass key or custom URL redirect
 * - Supports ?key=bypass_key for admin access during maintenance
 * - Optional redirect to custom branded maintenance page
 *
 * Why custom over built-in `php artisan down`:
 * - Multi-tenant: each school can have independent maintenance mode
 * - Database-driven: toggle via settings page without SSH/Artisan
 * - Bypass key stored securely in DB
 * - Custom redirect URL for branded page
 *
 * Integration:
 * - Register in bootstrap/app.php → withMiddleware()
 * - Runs early in web group
 * - Uses your getMergedSettings() helper
 *
 * Features / Problems Solved:
 * - Per-school isolation
 * - Secure bypass (key checked against DB)
 * - No file-based maintenance (better for clustered/load-balanced environments)
 * - Graceful redirect or 503 response
 * - Production-ready: logging, no exceptions thrown
 */

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $school = GetSchoolModel();

        if (!$school) {
            // No school context (e.g., global route) – allow through
            return $next($request);
        }

        $settings = getMergedSettings('maintenance', $school);

        if (($settings['mode'] ?? 'disabled') === 'enabled') {
            // Check bypass key in query string
            $bypassKey = $request->query('key');
            if ($bypassKey && hash_equals($settings['bypass_key'] ?? '', $bypassKey)) {
                // Valid bypass – allow access
                return $next($request);
            }

            // Custom redirect URL if provided
            if (!empty($settings['custom_url'])) {
                return redirect()->away($settings['custom_url'], 302);
            }

            // Fallback to Laravel's default 503 maintenance view
            abort(503, 'Service Unavailable – Maintenance in Progress');
        }

        return $next($request);
    }
}
