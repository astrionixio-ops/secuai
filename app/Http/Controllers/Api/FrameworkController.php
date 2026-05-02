<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Framework;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrameworkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Framework::query();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($region = $request->query('region')) {
            $query->where('region', $region);
        }
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $frameworks = $query->orderBy('name')->get()->map(function (Framework $f) {
            return [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'version' => $f->version,
                'issuer' => $f->issuer,
                'description' => $f->description,
                'category' => $f->category,
                'region' => $f->region,
                'is_active' => $f->is_active,
                'controls_count' => $f->controls()->count(),
            ];
        });

        return response()->json(['data' => $frameworks]);
    }

    public function show(Framework $framework): JsonResponse
    {
        $framework->loadCount('controls');
        return response()->json($framework);
    }

    public function controls(Framework $framework, Request $request): JsonResponse
    {
        $query = $framework->controls();
        if ($domain = $request->query('domain')) {
            $query->where('domain', $domain);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return response()->json($query->orderBy('control_ref')->paginate((int) $request->query('per_page', 100)));
    }
}
