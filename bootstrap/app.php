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
        $middleware->redirectUsersTo('/dashboard/late');
<<<<<<< HEAD
		$middleware->redirectGuestsTo('/login');
=======
        $middleware->redirectGuestsTo('/login');
>>>>>>> 2085cb4241a99dd50846ea10f3e25378cb887386
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
