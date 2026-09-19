<?php
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLoginController extends Controller {
    public function show(Request $request): View|RedirectResponse {
        if ($request->session()->get('league_admin_authenticated', false)) return redirect()->route('groups.index');
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse {
        $data = $request->validate(['username' => ['required','string'], 'password' => ['required','string']]);
        $user = config('league.admin_user');
        $password = config('league.admin_password');
        if (!is_string($user) || !is_string($password) || $password === '' ||
            !hash_equals($user, $data['username']) || !hash_equals($password, $data['password'])) {
            return back()->withErrors(['username' => 'ユーザー名またはパスワードが正しくありません。'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $request->session()->put('league_admin_authenticated', true);
        return redirect()->intended(route('groups.index'));
    }

    public function logout(Request $request): RedirectResponse {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('ranking');
    }
}
