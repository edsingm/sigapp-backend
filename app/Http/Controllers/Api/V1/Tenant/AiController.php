<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Ai\Agents\SIG_IA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        config(['ai.default' => 'gemini']);

        $agent = new SIG_IA;

        $response = $agent
            ->forUser(Auth::user())
            ->stream($request->string('message')->toString());

        return $response;
    }
}
