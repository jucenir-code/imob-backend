<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\RegisterUserFromInvite;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InviteRegistrationController extends Controller
{
    public function __construct(
        private readonly RegisterUserFromInvite $registerUserFromInvite
    ) {
    }

    public function create(Request $request): View
    {
        $inviteToken = (string) $request->query('invite_token', '');
        $prefilledEmail = (string) $request->query('email', '');
        $invite = $inviteToken !== ''
            ? $this->registerUserFromInvite->findValidInvite($inviteToken)
            : null;

        return view('auth.register', [
            'inviteToken' => $inviteToken,
            'prefilledEmail' => $prefilledEmail,
            'invite' => $invite,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(RegisterUserFromInvite::rules());

        $this->registerUserFromInvite->handle($validated);

        return redirect()
            ->route('register.success')
            ->with('registered_email', $validated['email']);
    }

    public function success(Request $request): View
    {
        return view('auth.register-success', [
            'registeredEmail' => (string) $request->session()->get('registered_email', ''),
        ]);
    }
}
