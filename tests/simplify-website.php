<?php
// With Simplify installed, its "Show on the website" switch is the only switch for the website.
declare(strict_types=1);
namespace Illuminate\Support\Facades {
    final class Storage {
        public static array $files = [];
        public static function disk(string $d): object { return new class {
            public function exists(string $p): bool { return isset(Storage::$files[$p]); }
            public function get(string $p): string { return Storage::$files[$p]; }
            public function put(string $p, string $c): bool { Storage::$files[$p] = $c; return true; }
        }; }
    }
    final class Cache { public static function lock(string $k, int $s): object { return new class { public function block(int $s, callable $f) { return $f(); } }; } }
    final class Validator { public static function make(array $in, array $rules): object { return new class($in) { public function __construct(private array $in) {} public function validate(): array { return $this->in; } }; } }
}
namespace App\Models { final class Server { public function __construct(array $a) { foreach ($a as $k => $v) { $this->$k = $v; } } } }
namespace Simplify\Core\Embed\Services {
    final class EmbedServers {
        public static array $shown = ['oak' => true];
        public static function visible(\App\Models\Server $s): bool { return self::$shown[$s->uuid] ?? false; }
        public static function setVisible(\App\Models\Server $s, bool $v): void { self::$shown[$s->uuid] = $v; }
    }
}
namespace {
    function config(string $key, $default = null) { return $default; }
    foreach (glob(__DIR__.'/../src/Services/Simplify*.php') as $file) { require $file; }
    require __DIR__.'/../src/Services/DirectoryStore.php';
    use App\Models\Server;
    use Deckzwabbers\ServerDirectory\Services\DirectoryStore as D;
    use Simplify\Core\Embed\Services\EmbedServers;
    function check($ok, $m) { if (!$ok) { throw new RuntimeException('FAIL '.$m); } echo "PASS $m\n"; }
    function server(string $uuid): Server {
        return new Server(['uuid' => $uuid, 'name' => ucfirst($uuid), 'variables' => [],
            'allocation' => (object) ['ip' => '159.195.130.60', 'port' => 25570, 'ip_alias' => null],
            'node' => (object) ['fqdn' => 'node.example.com'], 'egg' => (object) ['name' => 'Paper']]);
    }
    $oak = D::metadata(server('oak'));
    check($oak['visible'] === true && $oak['source'] === 'Simplify', 'a server shown in Simplify is on the website');
    D::save(server('oak'), [...$oak, 'visible' => false]);
    check(EmbedServers::$shown['oak'] === false && D::metadata(server('oak'))['visible'] === false, 'switching it off here switches it off in Simplify');
    D::save(server('oak'), [...$oak, 'visible' => true]);
    check(EmbedServers::$shown['oak'] === true, 'switching it on here switches it on in Simplify');
    D::listNew(server('elm'));
    check(D::metadata(server('elm'))['visible'] === false, 'Simplify decides whether a new server is on the website');
    echo "All Simplify website checks passed.\n";
}
