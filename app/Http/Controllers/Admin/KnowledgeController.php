<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KnowledgeController extends Controller
{
    private const ALLOWED_TYPES = ['faq', 'product', 'policy', 'company_profile', 'custom_instruction'];

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

        $prefix = auth()->user()->routeNamePrefix();
        return redirect()->route($prefix . '.knowledge.index')
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

        $prefix = auth()->user()->routeNamePrefix();
        return redirect()->route($prefix . '.knowledge.index')
            ->with('success', __('ui.controller_messages.knowledge_updated'));
    }

    public function destroy(KnowledgeEntry $entry)
    {
        AuditLog::record('knowledge.deleted', $entry, ['title' => $entry->title]);
        $entry->delete();
        $prefix = auth()->user()->routeNamePrefix();
        return redirect()->route($prefix . '.knowledge.index')
            ->with('success', __('ui.controller_messages.knowledge_deleted'));
    }

    public function importJson(Request $request)
    {
        $request->validate([
            // mimes:json fails on Windows because .json isn't in the default mime map;
            // fall back to a size/extension check via validation rules.
            'file' => 'required|file|max:4096|mimetypes:application/json,text/plain,text/json',
        ], [
            'file.mimetypes' => __('ui.knowledge_page.json_parse_error'),
        ]);

        $prefix = auth()->user()->routeNamePrefix();

        try {
            $raw = file_get_contents($request->file('file')->getRealPath());
            $decoded = json_decode($raw, true, 20, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return redirect()->route($prefix . '.knowledge.index')
                ->withErrors(['file' => __('ui.knowledge_page.json_parse_error') . ' (' . $e->getMessage() . ')']);
        }

        // Accept either a bare array or an object like {"entries": [...]}
        $rows = is_array($decoded) ? (isset($decoded['entries']) && is_array($decoded['entries']) ? $decoded['entries'] : $decoded) : [];

        if (empty($rows) || !array_is_list($rows)) {
            return redirect()->route($prefix . '.knowledge.index')
                ->withErrors(['file' => __('ui.knowledge_page.no_entries_found')]);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $tenantId = auth()->user()->tenant_id;

        DB::transaction(function () use ($rows, $tenantId, &$imported, &$skipped, &$errors) {
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    $skipped++;
                    $errors[] = __('ui.knowledge_page.import_row_error', ['row' => $index + 1, 'error' => 'not an object']);
                    continue;
                }

                $validator = Validator::make($row, [
                    'type'       => 'required|in:' . implode(',', self::ALLOWED_TYPES),
                    'title'      => 'required|string|max:200',
                    'body'       => 'required|string',
                    'is_active'  => 'nullable|boolean',
                    'sort_order' => 'nullable|integer',
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = __('ui.knowledge_page.import_row_error', [
                        'row'   => $index + 1,
                        'error' => $validator->errors()->first(),
                    ]);
                    continue;
                }

                $data = $validator->validated();

                KnowledgeEntry::create([
                    'tenant_id'  => $tenantId,
                    'type'       => $data['type'],
                    'title'      => $data['title'],
                    'body'       => $data['body'],
                    'is_active'  => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);
                $imported++;
            }
        });

        if ($imported > 0) {
            AuditLog::record('knowledge.imported', null, [
                'imported' => $imported,
                'skipped'  => $skipped,
                'source'   => $request->file('file')->getClientOriginalName(),
            ]);
        }

        $redirect = redirect()->route($prefix . '.knowledge.index');

        if ($imported === 0) {
            return $redirect->withErrors(['file' => __('ui.knowledge_page.import_failed')])
                ->with('import_errors', $errors);
        }

        if ($skipped > 0) {
            return $redirect->with('success', __('ui.knowledge_page.import_partial', [
                'ok'      => $imported,
                'skipped' => $skipped,
            ]))->with('import_errors', $errors);
        }

        return $redirect->with('success', __('ui.knowledge_page.import_success', ['count' => $imported]));
    }

    public function importJsonTemplate(): JsonResponse
    {
        $sample = [
            'entries' => [
                [
                    'type'      => 'faq',
                    'title'     => 'What are your delivery hours?',
                    'body'      => 'We deliver Monday to Saturday, 9:00 AM to 8:00 PM. Sundays are closed.',
                    'is_active' => true,
                ],
                [
                    'type'      => 'product',
                    'title'     => 'Wireless Earbuds Pro',
                    'body'      => 'Bluetooth 5.3, 30h battery, active noise cancelling. Available in black and white. $129.',
                    'is_active' => true,
                ],
                [
                    'type'      => 'policy',
                    'title'     => 'Return Policy',
                    'body'      => 'Items may be returned unused within 14 days of purchase. Original packaging required.',
                ],
                [
                    'type'      => 'company_profile',
                    'title'     => 'About Us',
                    'body'      => 'We have served customers since 2018 with a focus on quality electronics and fast support.',
                ],
                [
                    'type'      => 'custom_instruction',
                    'title'     => 'Tone of voice',
                    'body'      => 'Always greet the customer by name if available. Keep replies concise and friendly.',
                ],
            ],
        ];

        return response()->json($sample, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="knowledge-template.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
