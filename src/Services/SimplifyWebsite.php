<?php
namespace Deckzwabbers\ServerDirectory\Services;

use App\Models\Server;
use Simplify\Core\Embed\Services\EmbedServers;

/** Simplify's "Show on the website" switch (core 1.5+). With Simplify installed it is the only switch for the website. */
final class SimplifyWebsite
{
    public static function active(): bool
    {
        return class_exists(EmbedServers::class) && method_exists(EmbedServers::class, 'setVisible');
    }

    public static function visible(Server $server): bool
    {
        return EmbedServers::visible($server);
    }

    public static function setVisible(Server $server, bool $visible): void
    {
        EmbedServers::setVisible($server, $visible);
    }
}
