<?php
namespace Deckzwabbers\ServerDirectory\Providers;

use Deckzwabbers\ServerDirectory\Http\Controllers\DirectoryController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DirectoryProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::get('/api/server-directory/servers', DirectoryController::class)
            ->middleware('throttle:60,1')->name('server-directory.feed');
    }
}
