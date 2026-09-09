<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']) + ['status' => 'active'])) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos, ou conta inativa.']);
        }

        $request->session()->regenerate();

        return new UserResource($request->user()->load('groups'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
