<?php
declare(strict_types=1);
require __DIR__.'/../src/Services/DirectoryStore.php';
use Deckzwabbers\ServerDirectory\Services\DirectoryStore as D;
function check($ok,$m){if(!$ok)throw new RuntimeException('FAIL '.$m);echo "PASS $m\n";}
check(D::publicAddress('play.deckzwabbers.nl:25566')&&D::publicAddress('oak.deckzwabbers.nl')&&D::publicAddress('159.195.130.60:25565'),'public host names and IPs are shown');
check(!D::publicAddress('100.72.58.65:25565')&&!D::publicAddress('deckzwabbers.tail1234.ts.net:25565')&&!D::publicAddress('[fd7a:115c:a1e0::1]:25565'),'Tailscale addresses are never shown');
check(!D::publicAddress('192.168.1.3:25565')&&!D::publicAddress('10.77.0.2:25565')&&!D::publicAddress('127.0.0.1:25565')&&!D::publicAddress('localhost:25565')&&!D::publicAddress('0.0.0.0:25565'),'private, WireGuard, loopback and wildcard addresses are never shown');
check(D::publicAddress('[2a03:4000:b:11ae::1]:25565')&&!D::publicAddress(''),'public IPv6 is shown, empty is not');
echo "All address checks passed.\n";
