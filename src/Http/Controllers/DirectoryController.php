<?php
namespace Deckzwabbers\ServerDirectory\Http\Controllers;

use Deckzwabbers\ServerDirectory\Services\DirectoryStore;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

final class DirectoryController
{
    public function __invoke(Request $request): JsonResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists('server-directory/api-token'), 503, 'Directory token is not configured.');
        $expected = trim($disk->get('server-directory/api-token'));
        abort_unless(strlen($expected) >= 32 && hash_equals($expected, (string) $request->bearerToken()), 401);
        return response()->json(DirectoryStore::feed())->header('Cache-Control', 'no-store');
    }
}
