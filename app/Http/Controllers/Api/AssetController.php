<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query();

        foreach (['provider', 'asset_type', 'environment_id', 'criticality', 'status', 'region'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('external_id', 'like', "%$search%");
            });
        }

        return response()->json($query->orderByDesc('last_seen_at')->paginate((int) $request->query('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'cloud_credential_id' => 'nullable|exists:cloud_credentials,id',
            'external_id' => 'nullable|string|max:255',
            'provider' => 'required|string|max:60',
            'asset_type' => 'required|string|max:120',
            'name' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:60',
            'status' => 'nullable|string|max:60',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'tags' => 'nullable|array',
            'configuration' => 'nullable|array',
        ]);
        $data['first_seen_at'] = now();
        $data['last_seen_at'] = now();
        $asset = Asset::create($data);
        return response()->json($asset, 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return response()->json($asset->load(['environment', 'findings']));
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'cloud_credential_id' => 'nullable|exists:cloud_credentials,id',
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:60',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'tags' => 'nullable|array',
            'configuration' => 'nullable|array',
        ]);
        $asset->update($data);
        return response()->json($asset);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();
        return response()->json(null, 204);
    }
}
