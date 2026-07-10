<?php

namespace App\Http\Controllers;

use App\Models\WccRecord;
use App\Services\AuditLogger;
use App\Services\WccWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class RecordController extends Controller
{
    public function __construct(
        private readonly WccWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WccRecord::class);

        $records = WccRecord::query()
            ->with('owner:id,name,email')
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('dept'), fn ($q, $d) => $q->where('dept', $d))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('pages.records', [
            'records' => $records,
            'total' => WccRecord::count(),
            'filters' => $request->only('q', 'status', 'dept'),
        ]);
    }

    /**
     * Advance a record through the workflow. The policy decides who may make
     * which move; the workflow service enforces which moves are legal at all.
     */
    public function transition(Request $request, WccRecord $record): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', Rule::in(config('costflow.statuses'))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->authorize('transition', [$record, $validated['to']]);

        try {
            $this->workflow->transition($record, $validated['to'], $request->user(), $validated['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "{$record->quo_no} → {$validated['to']}");
    }

    public function destroy(WccRecord $record): RedirectResponse
    {
        $this->authorize('delete', $record);

        $quo = $record->quo_no;
        $record->delete();

        $this->audit->log('WCC deleted', $quo);

        return redirect()->route('records.index')->with('status', "Deleted {$quo}");
    }
}
