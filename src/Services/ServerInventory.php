<?php
namespace Deckzwabbers\ServerDirectory\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ServerInventory
{
    public static function pluginNames(array $entries): array
    {
        $names=[];
        foreach (array_slice($entries,0,1000) as $entry) {
            if (!is_array($entry) || !($entry['is_file'] ?? false) || ($entry['is_symlink'] ?? false)) { continue; }
            $name=(string) ($entry['name'] ?? '');
            if (strlen($name)>180 || preg_match('/[\x00-\x1f\/\\\\]/',$name) || !preg_match('/\.jar$/i',$name)) { continue; }
            $names[]=substr($name,0,-4);
        }
        $names=array_values(array_unique($names));sort($names,SORT_NATURAL|SORT_FLAG_CASE);
        return array_slice($names,0,100);
    }
    public static function manifest(array $data, string $file): array
    {
        if ($file==='modrinth.index.json' && (($data['game'] ?? '')!=='minecraft' || (int) ($data['formatVersion'] ?? 0)!==1)) { return []; }
        if ($file==='manifest.json' && ($data['manifestType'] ?? '')!=='minecraftModpack') { return []; }
        $out=[];
        foreach (['name'=>'modpack_name','version'=>'modpack_version','summary'=>'modpack_description'] as $key=>$field) {
            if (is_string($data[$key] ?? null)) { $out[$field]=mb_substr(strip_tags($data[$key]),0,$field==='modpack_description'?1200:150); }
        }
        $version=$file==='modrinth.index.json'?($data['dependencies']['minecraft'] ?? ''):($data['minecraft']['version'] ?? '');
        if (is_string($version) && preg_match('/^\d+\.\d+(?:\.\d+)?$/',$version)) { $out['version']=$version; }
        return $out;
    }
    public static function sync(Server $server, DaemonFileRepository $files): bool
    {
        $files->setServer($server);
        $inventory=['plugins'=>'','plugins_status'=>'unavailable','checked_at'=>time()];$reachable=false;
        try {
            $inventory['plugins']=implode("\n",self::pluginNames($files->getDirectory('/plugins')));
            $inventory['plugins_status']='ok';$reachable=true;
        } catch (Throwable) { /* Missing plugin folder and unreachable Wings are not an empty verified list. */ }
        foreach (['modrinth.index.json','manifest.json'] as $file) {
            try {
                $raw=$files->getContent('/'.$file,1048576);
                if (strlen($raw)>1048576) { continue; }
                $data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
                if (is_array($data)) { $pack=self::manifest($data,$file);if ($pack) { $inventory=array_replace($inventory,$pack);$reachable=true;break; } }
            } catch (Throwable) { }
        }
        // A fresh unavailable result clears old plugin lists instead of presenting stale installed files.
        if (!Storage::disk('local')->put('server-directory/inventory/'.$server->uuid.'.json',json_encode($inventory,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('Could not save file inventory.');
        }
        return $reachable;
    }
}
