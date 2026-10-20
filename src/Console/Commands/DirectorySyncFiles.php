<?php
namespace Deckzwabbers\ServerDirectory\Console\Commands;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Deckzwabbers\ServerDirectory\Services\DirectoryStore;
use Deckzwabbers\ServerDirectory\Services\ServerInventory;
use Illuminate\Console\Command;
use Throwable;
final class DirectorySyncFiles extends Command
{
    protected $signature='directory:sync-files';
    protected $description='Read actual plugin files and installed modpack manifests through Wings';
    public function handle(): int
    {
        $count=0;
        foreach (Server::with(['egg','allocation','node'])->orderBy('id')->get() as $server) {
            $meta=DirectoryStore::metadata($server);
            if (!$meta['visible'] || $meta['game']!=='minecraft') { continue; }
            try {
                if (ServerInventory::sync($server,app(DaemonFileRepository::class))) { $count++; }
                else { $this->warn($server->name.': Wings file inventory not available.'); }
            } catch (Throwable $error) { report($error);$this->warn($server->name.': file inventory failed.'); }
        }
        $this->info('Retrieved file inventory for '.$count.' server(s).');
        return self::SUCCESS;
    }
}
