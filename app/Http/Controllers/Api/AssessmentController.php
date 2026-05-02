<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Assessment::query()->with('frameworks:id,code,name');

        foreach (['status', 'assessment_type', 'organization_id'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,in_progress,review,completed,archived',
            'assessment_type' => 'nullable|in:internal,external,self,third_party',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'target_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'framework_ids' => 'nullable|array',
            'framework_ids.*' => 'integer|exists:frameworks,id',
        ]);

        $frameworkIds = $data['framework_ids'] ?? [];
        unset($data['framework_ids']);

        $data['owner_id'] = optional($request->user())->id;

        $assessment = Assessment::create($data);

        if (!empty($frameworkIds)) {
            // assessment_frameworks pivot has no updated_at — attach with created_at only.
            $rows = [];
            foreach ($frameworkIds as $fid) {
                $rows[$fid] = ['scope' => 'full', 'created_at' => now()];
            }
            $assessment->frameworks()->attach($rows);
        }

        return response()->json($assessment->fresh('frameworks'), 201);
    }

    public function show(Assessment $assessment): JsonResponse
    {
        return response()->json($assessment->load(['frameworks', 'organization', 'owner']));
    }

    public function update(Request $request, Assessment $assessment): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,in_progress,review,completed,archived',
            'assessment_type' => 'nullable|in:internal,external,self,third_party',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'target_date' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if (isset($data['status']) && $data['status'] === 'completed' && !$assessment->completed_at) {
            $data['completed_at'] = now();
        }

        $assessment->update($data);
        return response()->json($assessment);
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        $assessment->delete();
        return response()->json(null, 204);
    }

    /**
     * Attach/detach frameworks for an assessment.
     */
    public function syncFrameworks(Request $request, Assessment $assessment): JsonResponse
    {
        $data = $request->validate([
            'framework_ids' => 'required|array',
            'framework_ids.*' => 'integer|exists:frameworks,id',
        ]);

        $rows = [];
        foreach ($data['framework_ids'] as $fid) {
            $rows[$fid] = ['scope' => 'full', 'created_at' => now()];
        }
        $assessment->frameworks()->sync($rows);

        return response()->json($assessment->fresh('frameworks'));
    }
}
