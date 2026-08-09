<?php

namespace App\Http\Middleware;

use App\Services\VaultService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVaultUnlocked
{
    public function __construct(private readonly VaultService $vault) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $request->header('X-Vault-Token');

        if (! $user || ! $this->vault->isUnlocked($user, $token)) {
            abort(403, 'Vault is locked. Verify your two-factor code to unlock it.');
        }

        return $next($request);
    }
}
