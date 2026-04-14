<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->query('token');
        if (!$token) abort(403, 'Token is required');

        $ssoToken = DB::connection('superapp')
            ->table('sso_tokens')
            ->where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$ssoToken) abort(403, 'Token invalid or expired');

        // Mark token as used (one-time use)
        DB::connection('superapp')
            ->table('sso_tokens')
            ->where('id', $ssoToken->id)
            ->update(['used' => true]);

        // Get user data from superapp database
        $superappUser = DB::connection('superapp')
            ->table('users')
            ->where('id', $ssoToken->user_id)
            ->first();

        if (!$superappUser) abort(403, 'User not found');

        // Find or create user in soc database
        $user = User::where('email', 'adminsecurity@ewindo.com')->first();

        if (!$user) {
            $user = User::create([
                'name'     => $superappUser->fullname,
                'email'    => $superappUser->email,
                'password' => Str::random(32),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard/late');
    }
}
