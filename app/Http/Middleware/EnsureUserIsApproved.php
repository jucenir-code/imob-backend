<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin sempre pode passar
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Verifica se o usuário foi aprovado
        if (!$user->is_approved) {
            return response()->json([
                'message' => 'Sua conta ainda não foi aprovada. Aguarde a aprovação do administrador.'
            ], 403);
        }

        return $next($request);
    }
}
