<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidencePack;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvidencePackController extends Controller
{
    public function __construct(private readonly EvidenceService $evidence)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = EvidencePack::query();

        foreach (['status', 'assessment_id', 'framework_id'] as $f) {
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
            'framework_id' => 'nullable|exists:frameworks,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);
        $data['built_by'] = optional($request->user())->id;
        $data['status'] = 'building';

        $pack = EvidencePack::create($data);
        return response()->json($pack, 201);
    }

    public function show(EvidencePack $evidencePack): JsonResponse
    {
        return response()->json($evidencePack->load(['assessment', 'framework']));
    }

    /**
     * Build / assemble the pack contents.
     */
    public function build(Request $request, EvidencePack $evidencePack): JsonResponse
    {
        $pack = $this->evidence->buildPack($evidencePack);
        return response()->json([
            'evidence_pack' => $pack->fresh(),
            'message' => 'Pack built.',
        ]);
    }

    public function destroy(EvidencePack $evidencePack): JsonResponse
    {
        $evidencePack->delete();
        return response()->json(null, 204);
    }
}
