<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Finding::query();

        foreach (['status', 'severity', 'category', 'asset_id', 'scan_job_id', 'assigned_to'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        if ($search = $request->query('q')) {
            $query->where('title', 'like', "%$search%");
        }
        if ($request->boolean('open_only')) {
            $query->where('status', 'open');
        }

        return response()->json($query->latest('detected_at')->paginate((int) $request->query('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scan_job_id' => 'nullable|exists:scan_jobs,id',
            'asset_id' => 'nullable|exists:assets,id',
            'rule_id' => 'nullable|string|max:120',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'remediation' => 'nullable|string',
            'severity' => 'required|in:low,medium,high,critical',
            'status' => 'nullable|in:open,in_progress,resolved,suppressed,false_positive',
            'category' => 'nullable|string|max:120',
            'evidence' => 'nullable|array',
            'external_ref' => 'nullable|string|max:120',
            'assigned_to' => 'nullable|exists:users,id',
            'control_ids' => 'nullable|array',
            'control_ids.*' => 'integer|exists:controls,id',
        ]);

        $controlIds = $data['control_ids'] ?? [];
        unset($data['control_ids']);
        $data['detected_at'] = now();

        $finding = Finding::create($data);

        if (!empty($controlIds)) {
            // Pivot has no updated_at — attach manually with created_at only.
            $rows = [];
            foreach ($controlIds as $cid) {
                $rows[$cid] = ['relevance' => 'direct', 'created_at' => now()];
            }
            $finding->controls()->attach($rows);
        }

        return response()->json($finding->fresh('controls'), 201);
    }

    public function show(Finding $finding): JsonResponse
    {
        return response()->json($finding->load(['asset', 'scanJob', 'controls']));
    }

    public function update(Request $request, Finding $finding): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'remediation' => 'nullable|string',
            'severity' => 'nullable|in:low,medium,high,critical',
            'status' => 'nullable|in:open,in_progress,resolved,suppressed,false_positive',
            'assigned_to' => 'nullable|exists:users,id',
            'suppressed_until' => 'nullable|date',
        ]);

        if (isset($data['status']) && $data['status'] === 'resolved' && !$finding->resolved_at) {
            $data['resolved_at'] = now();
        }

        $finding->update($data);
        return response()->json($finding);
    }

    public function destroy(Finding $finding): JsonResponse
    {
        $finding->delete();
        return response()->json(null, 204);
    }
}
