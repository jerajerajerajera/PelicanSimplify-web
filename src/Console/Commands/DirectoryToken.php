<?php
namespace Deckzwabbers\ServerDirectory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class DirectoryToken extends Command
{
    protected $signature = 'directory:token {--rotate : Replace the existing relay token}';
    protected $description = 'Create a private API token for the Deckzwabbers website relay';
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $path = 'server-directory/api-token';
        if (!$disk->exists($path) || $this->option('rotate')) {
            if (!$disk->put($path, bin2hex(random_bytes(32)))) { $this->error('Could not save the token.'); return self::FAILURE; }
            @chmod($disk->path($path), 0600);
        }
        // Print only for the local administrator to transfer into the root-owned relay environment.
        $this->line(trim($disk->get($path)));
        return self::SUCCESS;
    }
}
