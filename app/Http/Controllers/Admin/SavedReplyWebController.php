<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedReply;
use Illuminate\Http\Request;

class SavedReplyWebController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'tenant'   => SavedReply::where('tenant_id', $user->tenant_id)->where('scope', 'tenant')->count(),
            'personal' => SavedReply::where('tenant_id', $user->tenant_id)->where('scope', 'personal')->count(),
        ];

        return view('admin.saved-replies.index', compact('stats'));
    }
}
