<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Control;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Control::query()->with('framework:id,code,name');

        if ($fw = $request->query('framework_id')) {
            $query->where('framework_id', $fw);
        }
        if ($code = $request->query('framework_code')) {
            $query->whereHas('framework', fn ($q) => $q->where('code', $code));
        }
        if ($domain = $request->query('domain')) {
            $query->where('domain', $domain);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('control_ref', 'like', "%$search%");
            });
        }

        return response()->json($query->orderBy('framework_id')->orderBy('control_ref')->paginate((int) $request->query('per_page', 50)));
    }

    public function show(Control $control): JsonResponse
    {
        return response()->json($control->load('framework'));
    }
}
