<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMembersFromSettings
{
    /**
     * Members have no Settings pages: they manage their account on "Account info".
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isMember = $user !== null
            && $user->hasRole(RoleEnum::USER->value)
            && ! $user->hasAnyRole([RoleEnum::SUPER_ADMIN->value, RoleEnum::ADMIN->value]);

        if (! $isMember) {
            return $next($request);
        }

        abort_unless($request->isMethodSafe(), 403);

        return to_route('account.index');
    }
}
