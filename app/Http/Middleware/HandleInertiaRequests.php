<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    // Add other safe fields as needed
                ] : null,

                // Laratrust: Get all permissions (direct + via roles)
                'permissions' => $user?->allPermissions()->pluck('name')->unique()->values()->toArray() ?? [],

                // Laratrust: Get all roles
                'roles' => $user?->roles()->pluck('name')->toArray() ?? [],
            ],

            // Optional: Current school context (from your SchoolService)
            'school' => [
                'current' => app('schoolManager')->getActiveSchool()?->only(['id', 'name', 'logo']),
            ],
        ]);
    }
}
