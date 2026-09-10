<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WebPushSubscription;
use App\Services\WebPushKeys;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WebPushController extends Controller
{
    public function config(WebPushKeys $keys)
    {
        return response()->json([
            'enabled' => config('webpush.enabled'),
            'public_key' => config('webpush.enabled') ? $keys->credentials()['publicKey'] : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        abort_unless(config('webpush.enabled'), 503, 'As notificações estão temporariamente indisponíveis.');
        abort_unless($request->user()->status === 'active', 403);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}=?$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}(==)?$/'],
        ]);
        $parts = parse_url($data['endpoint']);
        $host = strtolower($parts['host'] ?? '');
        $allowed = collect(config('webpush.allowed_hosts'))->contains(fn ($domain) => $host === $domain || str_ends_with($host, '.'.$domain));
        if (($parts['scheme'] ?? '') !== 'https' || ! $allowed || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw ValidationException::withMessages(['endpoint' => 'Serviço de notificações não suportado.']);
        }
        WebPushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id, 'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
        ]);
        $request->session()->put('web_push_endpoint_hash', hash('sha256', $data['endpoint']));

        return response()->json(['enabled' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        WebPushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->noContent();
    }
}
