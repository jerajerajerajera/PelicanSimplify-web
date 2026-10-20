<?php
namespace Deckzwabbers\ServerDirectory\Console\Commands;

use App\Models\Server;
use Deckzwabbers\ServerDirectory\Services\DirectoryStore;
use Deckzwabbers\SimpleServerCreator\Services\ModpackCatalog;
use Deckzwabbers\SimpleServerCreator\Services\WebsiteMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DirectorySyncPacks extends Command
{
    protected $signature = 'directory:sync-packs';
    protected $description = 'Save catalog metadata for the installed packs of existing website servers';
    public function handle(): int
    {
        if (!class_exists(WebsiteMetadata::class)) {
            $this->warn('SSC 0.4.2 must be enabled to import existing pack metadata.');
            return self::FAILURE;
        }
        $saved = 0;
        // Pelican variables() joins on $this->id: load it on the concrete server, not by eager loading.
        foreach (Server::with(['egg','allocation','node'])->orderBy('id')->get() as $server) {
            if (!DirectoryStore::metadata($server)['visible']) { continue; }
            $egg = strtolower((string) ($server->egg?->name ?? ''));
            $provider = str_contains($egg,'modrinth') ? 'modrinth' : (str_contains($egg,'ftb') ? 'ftb' : (str_contains($egg,'curseforge') ? 'curseforge' : ''));
            $env = [];
            foreach ($server->variables ?? [] as $variable) {
                if (in_array($variable->env_variable,['PROJECT_ID','VERSION_ID','FTB_MODPACK_ID','FTB_MODPACK_VERSION_ID','MODPACK_VERSION'],true)) {
                    $env[$variable->env_variable]=(string) ($variable->server_value ?? $variable->default_value ?? '');
                }
            }
            $project=$env[$provider==='ftb'?'FTB_MODPACK_ID':'PROJECT_ID'] ?? '';
            $release=$env[$provider==='ftb'?'FTB_MODPACK_VERSION_ID':'VERSION_ID'] ?? '';
            if (!$provider) {
                foreach (ModpackCatalog::technic() as $entry) {
                    if (strtolower((string) ($entry['egg_name'] ?? ''))===$egg) {
                        $provider='technic';$project=$entry['id'];$release='egg_default';break;
                    }
                }
            }
            if (!$provider) { continue; }
            if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/',$project)) { $this->warn($server->name.': missing pack ID in installer variables ('.$provider.').'); continue; }
            try {
                // Retry failed lookups while keeping the successful data already shown in SSC.
                foreach (['ssc.modpacks.pack.'.$provider.':'.$project,'ssc.modpacks.versions.'.$provider.':'.$project,...($provider==='ftb'?['ssc.ftb.pack.'.$project]:[])] as $cacheKey) {
                    if (!Cache::get($cacheKey)) { Cache::forget($cacheKey); }
                }
                $metadata=WebsiteMetadata::fromState(['server_type'=>'modded','modpack_project'=>$provider.':'.$project,'modpack_version'=>$release],$env);
                if (empty($metadata['modpack_name'])) {
                    $this->warn($server->name.': catalog unavailable for '.$provider.':'.$project.'; existing metadata retained.');continue;
                }
                Cache::lock('directory.catalog.'.$server->uuid,10)->block(5,function () use ($server,$metadata): void {
                    if (!Storage::disk('local')->put('server-directory/catalog/'.$server->uuid.'.json',json_encode($metadata,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))) {
                        throw new \RuntimeException('Catalog metadata could not be saved.');
                    }
                });
                $saved++;
            } catch (Throwable $error) {
                report($error);
                $this->warn($server->name.': pack metadata could not be retrieved; use Edit listing or retry later.');
            }
        }
        $this->info('Saved pack information for '.$saved.' existing website server(s).');
        return self::SUCCESS;
    }
}
