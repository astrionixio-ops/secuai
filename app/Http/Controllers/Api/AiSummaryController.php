<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiSummary::query();

        foreach (['subject_type', 'subject_id', 'summary_type'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }

        return response()->json($query->latest('generated_at')->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => 'required|string|max:120',
            'subject_id' => 'required|integer',
            'summary_type' => 'nullable|in:overview,remediation,risk,exec',
            'prompt' => 'nullable|string',
            'content' => 'required|string',
            'model' => 'nullable|string|max:120',
            'input_tokens' => 'nullable|integer',
            'output_tokens' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);
        $data['generated_at'] = now();

        $summary = AiSummary::create($data);
        return response()->json($summary, 201);
    }

    public function show(AiSummary $aiSummary): JsonResponse
    {
        return response()->json($aiSummary);
    }

    public function destroy(AiSummary $aiSummary): JsonResponse
    {
        $aiSummary->delete();
        return response()->json(null, 204);
    }
}
