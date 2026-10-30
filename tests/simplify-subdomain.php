<?php
// The website shows a server's working Simplify subdomain (core 1.5+) as its join address.
declare(strict_types=1);
namespace Illuminate\Support\Facades {
    final class Storage { public static function disk(string $d): object { return new class { public function exists(string $p): bool { return false; } public function get(string $p): string { return ''; } }; } }
    final class Cache {}
    final class Validator {}
}
namespace App\Models { final class Server { public function __construct(array $a) { foreach ($a as $k => $v) { $this->$k = $v; } } } }
namespace Simplify\Core\Subdomains {
    final class Subdomains {
        public static bool $ready = true;
        public static function ready(): bool { return self::$ready; }
        public static function domain(): string { return 'example.com'; }
        public static function address(\App\Models\Server $s): ?string { return $s->uuid === 'oak' ? 'oak.example.com' : null; }
        public static function gameAddress(\App\Models\Server $s): ?string { return self::address($s) ?? 'play.example.com:'.$s->allocation->port; }
        public static function state(\App\Models\Server $s): array { return $s->uuid === 'oak' ? ['name' => 'oak', 'status' => 'active', 'message' => ''] : ['name' => '', 'status' => '', 'message' => '']; }
    }
}
namespace {
    function config(string $key, $default = null) { return $default; }
    foreach (glob(__DIR__.'/../src/Services/Simplify*.php') as $file) { require $file; }
    require __DIR__.'/../src/Services/DirectoryStore.php';
    use App\Models\Server;
    use Deckzwabbers\ServerDirectory\Services\DirectoryStore as D;
    use Simplify\Core\Subdomains\Subdomains;
    function check($ok, $m) { if (!$ok) { throw new RuntimeException('FAIL '.$m); } echo "PASS $m\n"; }
    function server(string $uuid): Server {
        return new Server(['uuid' => $uuid, 'name' => ucfirst($uuid), 'variables' => [],
            'allocation' => (object) ['ip' => '159.195.130.60', 'port' => 25570, 'ip_alias' => null],
            'node' => (object) ['fqdn' => 'node.example.com'], 'egg' => (object) ['name' => 'Paper']]);
    }
    $oak = D::metadata(server('oak'));
    check($oak['effective_address'] === 'oak.example.com' && $oak['subdomain_address'] === 'oak.example.com', 'a working Simplify subdomain is the join address');
    check($oak['requested_subdomain'] === 'oak.example.com · connected', 'the admin page shows the connected subdomain');
    check(D::metadata(server('elm'))['effective_address'] === 'play.example.com:25570', 'without a subdomain the game address is used');
    Subdomains::$ready = false;
    check(D::metadata(server('oak'))['effective_address'] === '159.195.130.60:25570', 'subdomains that are not set up are ignored');
    echo "All Simplify subdomain checks passed.\n";
}
