<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvidenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Evidence::query();

        foreach (['evidence_type', 'status', 'control_id', 'assessment_id', 'source'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assessment_id' => 'nullable|exists:assessments,id',
            'control_id' => 'nullable|exists:controls,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'evidence_type' => 'required|in:document,screenshot,log,config,attestation,scan_result',
            'source' => 'nullable|in:manual,automated,api,scan',
            'file_path' => 'nullable|string',
            'file_hash' => 'nullable|string|size:64',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:120',
            'payload' => 'nullable|array',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ]);
        $data['uploaded_by'] = optional($request->user())->id;

        $evidence = Evidence::create($data);
        return response()->json($evidence, 201);
    }

    public function show(Evidence $evidence): JsonResponse
    {
        return response()->json($evidence->load(['control', 'assessment', 'uploader']));
    }

    public function update(Request $request, Evidence $evidence): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,expired,superseded,rejected',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ]);
        $evidence->update($data);
        return response()->json($evidence);
    }

    public function destroy(Evidence $evidence): JsonResponse
    {
        $evidence->delete();
        return response()->json(null, 204);
    }
}
