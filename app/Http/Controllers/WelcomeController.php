<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Presenters\WelcomePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function __construct(private readonly WelcomePresenter $welcomePresenter) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isAuthenticated = Auth::check();

        $applications = Application::where('enabled', true)
            ->orderBy('name')
            ->get()
            ->map(fn($app) => $this->welcomePresenter->presentApplication($app));

        $userData = null;
        $userInitials = '';
        if ($isAuthenticated && $user) {
            $userData = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->welcomePresenter->getUserPrimaryRole($user),
            ];

            $userInitials = $this->welcomePresenter->getUserInitials($user->name);
        }

        $devAutofill = null;
        if (app()->environment('local')) {
            $devAutofill = [
                'nip' => '0000.00000',
                'password' => 'adminpassword',
            ];
        }

        return view('welcome', [
            'isAuthenticated' => $isAuthenticated,
            'user' => $userData,
            'userInitials' => $userInitials,
            'applications' => $applications,
            'devAutofill' => $devAutofill,
        ]);
    }
}
