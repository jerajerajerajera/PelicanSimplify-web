<?php
namespace Deckzwabbers\ServerDirectory\Providers;

use Deckzwabbers\ServerDirectory\Http\Controllers\DirectoryController;
use Deckzwabbers\ServerDirectory\Services\DirectoryStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DirectoryProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::get('/api/server-directory/servers', DirectoryController::class)
            ->middleware('throttle:60,1')->name('server-directory.feed');
        // New servers are listed on the website right away; hide one with "Show on website" in Server directory.
        \App\Models\Server::created(function (\App\Models\Server $server): void {
            if (config('server-directory.show_new_servers', true) !== true) { return; }
            try { DirectoryStore::listNew($server); } catch (\Throwable $e) { report($e); }
        });
    }
}
