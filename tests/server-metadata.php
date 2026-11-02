<?php
// Servers made with PelicanSimplify 0.7.0 keep their category, modpack and alias in simplify/server-metadata/.
declare(strict_types=1);
namespace Illuminate\Support\Facades {
    final class Storage {
        public static array $files = [];
        public static function disk(string $d): object { return new class {
            public function exists(string $p): bool { return isset(Storage::$files[$p]); }
            public function get(string $p): string { return Storage::$files[$p]; }
        }; }
    }
    final class Cache {}
    final class Validator {}
}
namespace App\Models { final class Server { public function __construct(array $a) { foreach ($a as $k => $v) { $this->$k = $v; } } } }
namespace {
    function config(string $key, $default = null) { return $default; }
    foreach (glob(__DIR__.'/../src/Services/Simplify*.php') as $file) { require $file; }
    require __DIR__.'/../src/Services/DirectoryStore.php';
    use App\Models\Server;
    use Deckzwabbers\ServerDirectory\Services\DirectoryStore as D;
    use Illuminate\Support\Facades\Storage;
    function check($ok, $m) { if (!$ok) { throw new RuntimeException('FAIL '.$m); } echo "PASS $m\n"; }
    $server = new Server(['uuid' => 'oak', 'name' => 'Oak', 'variables' => [],
        'allocation' => (object) ['ip' => '159.195.130.60', 'port' => 25570, 'ip_alias' => null],
        'node' => (object) ['fqdn' => 'node.example.com'], 'egg' => (object) ['name' => 'Paper']]);
    Storage::$files['ssc/website-visibility/oak.json'] = json_encode(['server_flavour' => 'plus', 'server_alias' => 'Old']);
    Storage::$files['simplify/server-metadata/oak.json'] = json_encode(['server_flavour' => 'modded', 'server_alias' => 'Oak Survival', 'modpack' => ['modpack_name' => 'All the Mods 9']]);
    $meta = D::metadata($server);
    check($meta['server_flavour'] === 'modded' && $meta['server_alias'] === 'Oak Survival', 'the 0.7.0 metadata wins over the older file');
    check($meta['modpack_name'] === 'All the Mods 9', 'the modpack chosen in the 0.7.0 Server Maker is shown');
    unset(Storage::$files['simplify/server-metadata/oak.json']);
    check(D::metadata($server)['server_flavour'] === 'plus', 'the older file is still read');
    echo "All server metadata checks passed.\n";
}
