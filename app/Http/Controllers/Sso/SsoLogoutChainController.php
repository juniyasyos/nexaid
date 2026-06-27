<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsoLogoutChainController extends Controller
{
    /**
     * Renders a page with hidden iframes to perform front-channel Single Sign-Out
     * to all registered client applications in parallel.
     */
    public function __invoke(Request $request)
    {
        $apps = Application::enabled()
            ->get()
            ->filter(fn(Application $a) => ! empty($a->logout_uri))
            ->values();

        // If no apps to log out from, immediately redirect to login page
        if ($apps->isEmpty()) {
            return redirect('/login');
        }

        $logoutUris = $apps->map(function ($app) {
            $separator = str_contains($app->logout_uri, '?') ? '&' : '?';
            // Provide a dummy redirect url just to satisfy the client, 
            // though the iframe will be hidden and discarded anyway.
            return $app->logout_uri . $separator . 'request_id=' . uniqid('sso_chain_');
        })->all();

        return view('sso.logout', [
            'logoutUris' => $logoutUris,
            'redirectUrl' => url('/login'),
        ]);
    }
}
