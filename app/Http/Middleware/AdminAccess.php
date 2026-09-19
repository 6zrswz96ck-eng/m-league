<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AdminAccess {
    public function handle(Request $request, Closure $next): Response {
        if (!$request->session()->get('league_admin_authenticated', false)) {
            return redirect()->guest(route('login'));
        }
        return $next($request);
    }
}
