<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $q = $request->query('q');

        $logs = AuditLog::query()
            ->when($q, fn ($query, string $term) => Search::across($query, ['actor', 'action', 'detail'], $term))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('pages.audit', ['logs' => $logs, 'filters' => $request->only('q')]);
    }

    /**
     * IT-only. Wipes the trail, then immediately records that it was wiped —
     * the log can be emptied, but never silently.
     */
    public function destroy(): RedirectResponse
    {
        $this->authorize('clear-audit-log');

        AuditLog::query()->delete();

        $this->audit->log('Audit log cleared', 'All prior entries removed');

        return redirect()->route('audit.index')->with('status', 'Audit log cleared.');
    }
}
