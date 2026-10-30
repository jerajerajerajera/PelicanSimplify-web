<?php
namespace Deckzwabbers\ServerDirectory\Services;

use App\Models\Server;
use Simplify\Core\Subdomains\Subdomains;
use Throwable;

/** A server's automatic subdomain from Simplify (core 1.5+), through its public Subdomains class. Empty without Simplify. */
final class SimplifySubdomain
{
    /** The join address when the subdomain works, such as oak.example.com. */
    public static function address(Server $server): string
    {
        return self::ask(fn () => (string) (Subdomains::address($server) ?? ''));
    }

    /** The subdomain, else the game address with this server's port (such as play.example.com:25570). Needs Simplify 1.5.2+. */
    public static function gameAddress(Server $server): string
    {
        return method_exists(Subdomains::class, 'gameAddress') ? self::ask(fn () => (string) (Subdomains::gameAddress($server) ?? '')) : '';
    }

    /** What the admin page shows, such as "oak.example.com · connected". */
    public static function label(Server $server): string
    {
        return self::ask(function () use ($server): string {
            $state = Subdomains::state($server);
            if ($state['name'] === '') { return ''; }
            return $state['name'] . '.' . Subdomains::domain() . ' · ' . ($state['status'] === 'active' ? 'connected' : ($state['message'] ?: $state['status']));
        });
    }

    private static function ask(callable $read): string
    {
        if (!class_exists(Subdomains::class)) { return ''; }
        try { return Subdomains::ready() ? $read() : ''; } catch (Throwable $e) { report($e); return ''; }
    }
}
