<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query();

        if ($industry = $request->query('industry')) {
            $query->where('industry', $industry);
        }
        if ($search = $request->query('q')) {
            $query->where('name', 'like', "%$search%");
        }

        return response()->json($query->orderBy('name')->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:120',
            'size' => 'nullable|string|max:60',
            'country' => 'nullable|string|size:2',
            'website' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);
        $org = Organization::create($data);
        return response()->json($org, 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:120',
            'size' => 'nullable|string|max:60',
            'country' => 'nullable|string|size:2',
            'website' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);
        $organization->update($data);
        return response()->json($organization);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();
        return response()->json(null, 204);
    }
}
