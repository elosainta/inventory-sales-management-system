<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare is the only thing that can reach origin:443 (the DO Cloud
        // Firewall drops everything else), so every real request arrives
        // proxied. Without this, $request->ip() is a Cloudflare edge node —
        // login history records the wrong address and IP rate limiters bucket
        // unrelated users together.
        $middleware->trustProxies(at: '*');

        // The remembered UI language. Left unencrypted on purpose: it holds no
        // secret, it has to be readable before anyone signs in, and SetLocale
        // already refuses any value outside its own allowlist — which is the
        // real protection, since a locale ends up resolving files on disk.
        // Encryption here would only make a public preference harder to debug.
        $middleware->encryptCookies(except: ['app_locale']);

        $middleware->web(append: [
            \App\Http\Middleware\MaintenanceMode::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\UseDemoDatabase::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
