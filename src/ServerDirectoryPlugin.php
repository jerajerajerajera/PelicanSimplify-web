<?php
namespace Deckzwabbers\ServerDirectory;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class ServerDirectoryPlugin implements Plugin
{
    public function getId(): string { return 'server-directory'; }
    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->discoverPages(plugin_path($this->getId(), 'src/Filament/Admin/Pages'), 'Deckzwabbers\\ServerDirectory\\Filament\\Admin\\Pages');
        }
    }
    public function boot(Panel $panel): void
    {
        app('view')->addNamespace($this->getId(), plugin_path($this->getId(), 'resources/views'));
    }
}
