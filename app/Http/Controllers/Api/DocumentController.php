<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Document::query();

        foreach (['document_type', 'status', 'organization_id', 'category'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        if ($search = $request->query('q')) {
            $query->where('title', 'like', "%$search%");
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'title' => 'required|string|max:255',
            'document_type' => 'required|in:policy,procedure,standard,report,sop,other',
            'category' => 'nullable|string|max:120',
            'status' => 'nullable|in:draft,review,approved,published,archived',
            'version' => 'nullable|string|max:32',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'file_path' => 'nullable|string',
            'file_hash' => 'nullable|string|size:64',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:120',
            'effective_date' => 'nullable|date',
            'next_review_date' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);
        $data['owner_id'] = optional($request->user())->id;

        $doc = Document::create($data);
        return response()->json($doc, 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'document_type' => 'nullable|in:policy,procedure,standard,report,sop,other',
            'category' => 'nullable|string|max:120',
            'status' => 'nullable|in:draft,review,approved,published,archived',
            'version' => 'nullable|string|max:32',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'next_review_date' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);
        $document->update($data);
        return response()->json($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();
        return response()->json(null, 204);
    }
}
