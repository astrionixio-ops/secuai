<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Environment::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($orgId = $request->query('organization_id')) {
            $query->where('organization_id', $orgId);
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:production,staging,development,test',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        $env = Environment::create($data);
        return response()->json($env, 201);
    }

    public function show(Environment $environment): JsonResponse
    {
        return response()->json($environment->load(['organization', 'cloudCredentials']));
    }

    public function update(Request $request, Environment $environment): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|in:production,staging,development,test',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        $environment->update($data);
        return response()->json($environment);
    }

    public function destroy(Environment $environment): JsonResponse
    {
        $environment->delete();
        return response()->json(null, 204);
    }
}
