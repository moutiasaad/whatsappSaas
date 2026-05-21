<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeEntry;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $entries = KnowledgeEntry::when($request->type, fn ($q, $t) => $q->ofType($t))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        $counts = KnowledgeEntry::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return view('admin.knowledge.index', compact('entries', 'counts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'      => 'required|in:faq,product,policy,company_profile,custom_instruction',
            'title'     => 'required|string|max:200',
            'body'      => 'required|string',
            'is_active' => 'boolean',
        ]);

        $entry = KnowledgeEntry::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'is_active' => $request->boolean('is_active', true),
        ]);
        AuditLog::record('knowledge.created', $entry);

        return redirect()->route('admin.knowledge.index')
            ->with('success', __('ui.controller_messages.knowledge_created'));
    }

    public function edit(KnowledgeEntry $entry)
    {
        return view('admin.knowledge.edit', compact('entry'));
    }

    public function update(Request $request, KnowledgeEntry $entry)
    {
        $data = $request->validate([
            'type'      => 'required|in:faq,product,policy,company_profile,custom_instruction',
            'title'     => 'required|string|max:200',
            'body'      => 'required|string',
            'is_active' => 'boolean',
        ]);

        $entry->update($data + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('knowledge.updated', $entry);

        return redirect()->route('admin.knowledge.index')
            ->with('success', __('ui.controller_messages.knowledge_updated'));
    }

    public function destroy(KnowledgeEntry $entry)
    {
        AuditLog::record('knowledge.deleted', $entry, ['title' => $entry->title]);
        $entry->delete();
        return redirect()->route('admin.knowledge.index')
            ->with('success', __('ui.controller_messages.knowledge_deleted'));
    }
}
