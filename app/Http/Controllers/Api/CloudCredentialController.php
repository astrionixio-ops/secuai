<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CloudCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudCredentialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CloudCredential::query();

        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }
        if ($envId = $request->query('environment_id')) {
            $query->where('environment_id', $envId);
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'name' => 'required|string|max:255',
            'provider' => 'required|in:aws,azure,gcp,do,linode,other',
            'account_identifier' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:60',
            'secret_payload' => 'required|array',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $secret = $data['secret_payload'];
        unset($data['secret_payload']);

        $cred = new CloudCredential($data);
        $cred->setSecretPayload($secret);
        $cred->rotated_at = now();
        $cred->save();

        return response()->json($cred, 201);
    }

    public function show(CloudCredential $cloudCredential): JsonResponse
    {
        return response()->json($cloudCredential);
    }

    public function update(Request $request, CloudCredential $cloudCredential): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'name' => 'sometimes|string|max:255',
            'account_identifier' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:60',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);
        $cloudCredential->update($data);
        return response()->json($cloudCredential);
    }

    /**
     * Rotate the encrypted secret payload. Body: { "secret_payload": {...} }
     */
    public function rotate(Request $request, CloudCredential $cloudCredential): JsonResponse
    {
        $data = $request->validate([
            'secret_payload' => 'required|array',
        ]);
        $cloudCredential->setSecretPayload($data['secret_payload']);
        $cloudCredential->rotated_at = now();
        $cloudCredential->save();

        return response()->json([
            'message' => 'Credential rotated.',
            'rotated_at' => $cloudCredential->rotated_at,
            'fingerprint' => $cloudCredential->payload_fingerprint,
        ]);
    }

    public function destroy(CloudCredential $cloudCredential): JsonResponse
    {
        $cloudCredential->delete();
        return response()->json(null, 204);
    }
}
