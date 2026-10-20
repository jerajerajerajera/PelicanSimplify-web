<?php
namespace Deckzwabbers\ServerDirectory\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class DirectoryStore
{
    private static function path(Server $server): string { return 'server-directory/servers/' . $server->uuid . '.json'; }

    private static function read(string $path): ?array
    {
        $disk = Storage::disk('local');
        if (!$disk->exists($path)) { return null; }
        try {
            $data = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : ['visible' => false];
        } catch (Throwable) { return ['visible' => false]; }
    }

    public static function metadata(Server $server): array
    {
        $ssc = self::read('ssc/website-visibility/' . $server->uuid . '.json') ?? [];
        $stored = self::read(self::path($server));
        $ip = (string) ($server->allocation?->ip ?? '');
        if (in_array($ip, ['0.0.0.0', '::', ''], true)) {
            $fqdn = (string) ($server->node?->fqdn ?? '');
            $ip = str_contains($fqdn, '://') ? (string) parse_url($fqdn, PHP_URL_HOST) : $fqdn;
        }
        $port = (int) ($server->allocation?->port ?? 25565);
        $alias = (string) ($server->allocation?->ip_alias ?? '');
        // Allocation aliases can also be friendly labels, such as "Minecraft servers".
        $joinHost = $alias && (filter_var($alias, FILTER_VALIDATE_IP) || filter_var($alias, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) ? $alias : $ip;
        $join = (str_contains($joinHost, ':') ? '[' . $joinHost . ']' : $joinHost) . ':' . $port;
        $eggName = strtolower((string) ($server->egg?->name ?? ''));
        $mc = preg_match('/minecraft|vanilla|paper|purpur|spigot|modrinth|curseforge|ftb|tekkit|hexxit|blightfall|b-team|1\.12\.2 pack|1\.7\.10 pack/i', $eggName) === 1;
        $defaults = [
            'visible' => ($ssc['show_on_website'] ?? false) === true,
            'display_name' => $server->name,
            'server_alias' => trim((string)($ssc['server_alias']??'')),
            'description' => '',
            'game' => $mc ? 'minecraft' : 'other',
            'type' => $server->egg?->name ?? 'Game server',
            'version' => '',
            'server_flavour' => in_array($ssc['server_flavour'] ?? '', ['vanilla','plus','modded'], true) ? $ssc['server_flavour'] : (preg_match('/paper|purpur|spigot|bukkit/', $eggName) ? 'plus' : (preg_match('/mod|pack|ftb|forge|fabric|tekkit/', $eggName) ? 'modded' : 'vanilla')),
            'plugins' => '',
            'modpack_name' => '',
            'modpack_version' => '',
            'modpack_description' => '',
            'modpack_icon_url' => '',
            'modpack_url' => '',
            'client_url' => '',
            'screenshot_url' => '',
            'background_id' => in_array($ssc['background_id'] ?? '', ['valley','harbor','village','survival','town'], true) ? $ssc['background_id'] : 'auto',
            'icon_url' => '',
            'motd' => '',
            'bedrock_address' => '',
            'published_address' => '',
            'target_host' => $ip,
            'target_port' => $port,
            'sort_order' => 0,
        ];
        $meta = array_replace($defaults, array_intersect_key($stored ?? [], $defaults));
        // The native server name is authoritative, including older website overrides.
        $meta['display_name']=$server->name;
        $meta['server_alias']=$defaults['server_alias'];
        // Persisted SSC selection remains available after cache clearing and catalog outages.
        $snapshot = [];
        $current = [];
        foreach ($server->variables ?? [] as $variable) {
            if (in_array($variable->env_variable, ['PROJECT_ID','VERSION_ID','FTB_MODPACK_ID','FTB_MODPACK_VERSION_ID'], true)) {
                $current[$variable->env_variable] = (string) ($variable->server_value ?? $variable->default_value ?? '');
            }
        }
        foreach ([self::read('server-directory/catalog/' . $server->uuid . '.json'), $ssc['modpack'] ?? null] as $candidate) {
            if (!is_array($candidate) || empty($candidate['modpack_name'])) { continue; }
            if (isset($candidate['provider'], $candidate['project_id'], $candidate['version_id'])) {
                $projectKey = $candidate['provider'] === 'ftb' ? 'FTB_MODPACK_ID' : 'PROJECT_ID';
                $versionKey = $candidate['provider'] === 'ftb' ? 'FTB_MODPACK_VERSION_ID' : 'VERSION_ID';
                if ((isset($current[$projectKey]) && $current[$projectKey] !== $candidate['project_id']) ||
                    (isset($current[$versionKey]) && $current[$versionKey] !== $candidate['version_id'])) { continue; }
            }
            $snapshot = $candidate; break;
        }
        foreach (['modpack_name', 'modpack_version', 'modpack_description', 'modpack_icon_url', 'modpack_url', 'client_url'] as $field) {
            if (!$meta[$field] && is_string($snapshot[$field] ?? null)) { $meta[$field] = $snapshot[$field]; }
        }
        $inventory=self::read('server-directory/inventory/'.$server->uuid.'.json') ?? [];
        $fresh=(int) ($inventory['checked_at'] ?? 0)>time()-180;
        $meta['plugins']=$fresh && is_string($inventory['plugins'] ?? null)?$inventory['plugins']:'';
        $meta['plugins_status']=$fresh?($inventory['plugins_status'] ?? 'unavailable'):'unavailable';
        foreach (['modpack_name','modpack_version','modpack_description','version'] as $field) {
            if (!$meta[$field] && is_string($inventory[$field] ?? null)) { $meta[$field]=$inventory[$field]; }
        }
        $meta['visible'] = $meta['visible'] === true;
        // PelicanSimplify's automatic subdomains: a connected subdomain is the join address unless one is set here.
        $simplify = class_exists(\Simplify\Panel\Services\Subdomains::class) && \Simplify\Panel\Services\Subdomains::active();
        $subdomain = $simplify ? \Simplify\Panel\Services\Subdomains::state($server) : null;
        $meta['requested_subdomain'] = $subdomain ? ($subdomain['name'] !== '' ? $subdomain['name'] . '.' . $subdomain['domain'] . ' · ' . ($subdomain['status'] === 'active' ? 'connected' : ($subdomain['message'] ?: $subdomain['status'])) : '') : ($ssc['requested_subdomain'] ?? '');
        $meta['subdomain_address'] = $simplify ? (string) (\Simplify\Panel\Services\Subdomains::address($server) ?? '') : '';
        $meta['source'] = $stored === null ? 'SSC / default' : 'Directory';
        // Simplify's game address (e.g. play.example.com) when this port is forwarded there.
        $game = '';
        if (class_exists(\Simplify\Panel\Services\Subdomains::class)) {
            $target = (string) (\Simplify\Panel\Services\Subdomains::settings()['target'] ?? '');
            if ($target !== '' && \Simplify\Panel\Services\Subdomains::forwarded($port)) { $game = $target . ':' . $port; }
        }
        // Visitors only ever get a public address: private, Tailscale and loopback addresses are skipped.
        $candidates = [$meta['published_address'], $meta['subdomain_address'], $game, config('server-directory.mode', 'test') === 'test' ? $join : ''];
        $meta['effective_address'] = '';
        foreach ($candidates as $candidate) { if ($candidate !== '' && self::publicAddress($candidate)) { $meta['effective_address'] = $candidate; break; } }
        return $meta;
    }

    /** False for addresses players outside the network cannot reach: private, CGNAT/Tailscale, loopback, .ts.net, .local. */
    public static function publicAddress(string $address): bool
    {
        $host = preg_match('/^\[([^\]]+)\]/', $address, $m) ? $m[1] : (substr_count($address, ':') > 1 ? $address : explode(':', $address)[0]);
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.ts.net') || str_ends_with($host, '.local')) { return false; }
        if (!filter_var($host, FILTER_VALIDATE_IP)) { return true; }
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
        // 100.64.0.0/10 (carrier-grade NAT, used by Tailscale) is not covered by the flags above.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $n = ip2long($host); return !($n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255')); }
        return !str_starts_with($host, 'fd7a:115c:a1e0');
    }

    /** Marks a just-created server as shown on the website; an existing listing is left alone. */
    public static function listNew(Server $server): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists(self::path($server))) { return; }
        if (!$disk->put(self::path($server), json_encode(['schema_version' => 2, 'server_uuid' => $server->uuid, 'visible' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('Could not save the website listing.');
        }
    }

    public static function save(Server $server, array $input): void
    {
        // Website settings never rename a server or keep an independent public title.
        $input['display_name']=$server->name;
        $values = Validator::make($input, [
            'visible' => ['required', 'boolean'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1200'],
            'game' => ['required', 'in:minecraft,other'],
            'type' => ['required', 'string', 'max:100'],
            'server_flavour' => ['required', 'in:vanilla,plus,modded'],
            'plugins' => ['nullable', 'string', 'max:4000'],
            'version' => ['nullable', 'string', 'max:100'],
            'modpack_name' => ['nullable', 'string', 'max:150'],
            'modpack_version' => ['nullable', 'string', 'max:100'],
            'modpack_description' => ['nullable', 'string', 'max:1200'],
            'modpack_icon_url' => ['nullable', 'url:http,https', 'max:2048'],
            'modpack_url' => ['nullable', 'url:http,https', 'max:2048'],
            'client_url' => ['nullable', 'url:http,https', 'max:2048'],
            'background_id' => ['required', 'in:auto,valley,harbor,village,survival,town'],
            'screenshot_url' => ['nullable', 'url:http,https', 'max:2048'],
            'icon_url' => ['nullable', 'url:http,https', 'max:2048'],
            'motd' => ['nullable', 'string', 'max:500'],
            'bedrock_address' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-zA-Z0-9.\-]+|\[[a-fA-F0-9:]+\]):(?:[1-9][0-9]{0,4})$/'],
            'published_address' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-:\[\]]+$/'],
            'target_host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.\-:]+$/'],
            'target_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ])->validate();
        $values['visible'] = (bool) $values['visible'];
        Cache::lock('directory.save.' . $server->uuid, 10)->block(5, function () use ($server, $values): void {
            if (!Storage::disk('local')->put(self::path($server), json_encode([
                'schema_version' => 2, 'server_uuid' => $server->uuid, ...$values,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                throw new \RuntimeException('Could not save the website listing.');
            }
        });
    }

    public static function backgroundFor(Server $server): ?string
    {
        $ids=['valley','harbor','village','survival','town'];
        $saved=self::read('server-directory/backgrounds/'.$server->uuid.'.json');
        if (in_array($saved['background_id']??null,$ids,true)) return $saved['background_id'];
        // Allocation must not depend on someone visiting the public website feed.
        $records=Server::with(['allocation','node','egg'])->orderBy('id')->get();
        $assigned=self::backgrounds($records);
        return $assigned[$server->uuid]??null;
    }

    private static function backgrounds(iterable $records): array
    {
        $servers = is_array($records) ? $records : iterator_to_array($records);
        return Cache::lock('directory.backgrounds', 10)->block(5, function () use ($servers): array {
            $ids = ['valley','harbor','village','survival','town'];
            $assigned = []; $used = [];
            // Only actual Pelican server records reserve IDs. Deleted UUIDs free their image.
            // Hidden servers still exist, so their choices remain reserved.
            foreach ($servers as $server) {
                $meta = self::metadata($server);
                if ($meta['game'] !== 'minecraft') { continue; }
                $chosen = $meta['background_id'];
                if (!in_array($chosen, $ids, true)) {
                    $saved = self::read('server-directory/backgrounds/' . $server->uuid . '.json') ?? [];
                    $chosen = $saved['background_id'] ?? '';
                }
                if (in_array($chosen, $ids, true)) { $assigned[$server->uuid] = $chosen; $used[$chosen] = true; }
            }
            foreach ($servers as $server) {
                $meta = self::metadata($server);
                if ($meta['game'] !== 'minecraft' || isset($assigned[$server->uuid])) { continue; }
                $free = array_values(array_filter($ids, fn ($id) => !isset($used[$id])));
                $chosen = $free[0] ?? $ids[count($assigned) % count($ids)];
                if (!Storage::disk('local')->put('server-directory/backgrounds/' . $server->uuid . '.json', json_encode([
                    'server_uuid' => $server->uuid, 'background_id' => $chosen,
                ], JSON_THROW_ON_ERROR))) { throw new \RuntimeException('Could not save the background selection.'); }
                $assigned[$server->uuid] = $chosen; $used[$chosen] = true;
            }
            return $assigned;
        });
    }

    public static function feed(): array
    {
        $items = [];
        $records = Server::with(['allocation', 'node', 'egg'])->orderBy('id')->get();
        $backgrounds = self::backgrounds($records);
        foreach ($records as $server) {
            $meta = self::metadata($server);
            if (!$meta['visible'] || !$meta['effective_address']) { continue; }
            $env = [];
            foreach ($server->variables ?? [] as $variable) {
                // Read only public pack IDs. API keys and other egg variables never leave the panel.
                if (in_array($variable->env_variable, ['PROJECT_ID', 'VERSION_ID', 'FTB_MODPACK_ID', 'FTB_MODPACK_VERSION_ID'], true)) {
                    $env[$variable->env_variable] = (string) ($variable->server_value ?? $variable->default_value ?? '');
                }
            }
            $eggName = strtolower((string) ($server->egg?->name ?? ''));
            $provider = str_contains($eggName, 'modrinth') ? 'modrinth' : (str_contains($eggName, 'ftb') ? 'ftb' : '');
            $project = $env[$provider === 'ftb' ? 'FTB_MODPACK_ID' : 'PROJECT_ID'] ?? '';
            $release = $env[$provider === 'ftb' ? 'FTB_MODPACK_VERSION_ID' : 'VERSION_ID'] ?? '';
            // Reuse SSC's already retrieved public catalog data without making panel HTTP requests.
            if ($provider && preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $project)) {
                $key = $provider . ':' . $project;
                $pack = Cache::get('ssc.modpacks.pack.' . $key, []);
                $versions = Cache::get('ssc.modpacks.versions.' . $key, []);
                $pack = is_array($pack) ? $pack : [];
                $versions = is_array($versions) ? $versions : [];
                foreach (['modpack_name' => 'title', 'modpack_description' => 'description', 'modpack_icon_url' => 'icon_url', 'modpack_url' => 'url'] as $field => $catalogField) {
                    if (!$meta[$field] && is_string($pack[$catalogField] ?? null)) { $meta[$field] = $pack[$catalogField]; }
                }
                if (!$meta['modpack_version'] && is_string($versions[$release] ?? null)) { $meta['modpack_version'] = $versions[$release]; }
                if (!$meta['client_url'] && isset($versions[$release]) && is_string($pack['url'] ?? null)) {
                    $meta['client_url'] = $pack['url'] . ($provider === 'modrinth' ? '/version/' . rawurlencode($release) : '');
                }
            }
            $items[] = [
                'id' => $server->uuid, 'name' => $server->name,
                'description' => $meta['description'], 'game' => $meta['game'],
                'type' => $meta['type'], 'version' => $meta['version'],
                'serverFlavour' => $meta['server_flavour'], 'plugins' => $meta['plugins'], 'pluginsStatus'=>$meta['plugins_status'],
                'modpackName' => $meta['modpack_name'], 'modpackVersion' => $meta['modpack_version'],
                'modpackDescription' => $meta['modpack_description'], 'modpackUrl' => $meta['modpack_url'],
                'modpackIconUrl' => $meta['modpack_icon_url'],
                'clientUrl' => $meta['client_url'], 'screenshotUrl' => $meta['screenshot_url'],
                'backgroundId' => $backgrounds[$server->uuid] ?? '',
                'iconUrl' => $meta['icon_url'], 'motd' => $meta['motd'],
                'bedrockAddress' => $meta['bedrock_address'],
                'address' => $meta['effective_address'], 'sortOrder' => (int) $meta['sort_order'],
                // Internal probing data is stripped by the website relay, never sent to its browser.
                'targetHost' => $meta['target_host'], 'targetPort' => (int) $meta['target_port'],
                'packProvider' => $provider,
                'packId' => $project,
                'packVersionId' => $release,
            ];
        }
        usort($items, fn ($a, $b) => [$a['sortOrder'], $a['name']] <=> [$b['sortOrder'], $b['name']]);
        return $items;
    }
}
