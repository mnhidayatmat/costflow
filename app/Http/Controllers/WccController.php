<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWccRequest;
use App\Models\WccRecord;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WccController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The workspace with a blank template.
     */
    public function create(): View
    {
        $this->authorize('create', WccRecord::class);

        return view('pages.wcc', [
            'record' => null,
            'snapshot' => null,
            'readonly' => false,
        ]);
    }

    /**
     * The workspace, rehydrated from a saved record's snapshot.
     */
    public function open(WccRecord $record): View
    {
        $this->authorize('view', $record);

        $this->audit->log('WCC opened', $record->quo_no);

        return view('pages.wcc', [
            'record' => $record,
            'snapshot' => $record->snapshot,
            'readonly' => ! request()->user()->can('update', $record),
        ]);
    }

    /**
     * Create a record from the current template state.
     */
    public function store(SaveWccRequest $request): JsonResponse
    {
        // Authorized by SaveWccRequest::authorize(), which runs before validation.
        $record = new WccRecord($request->safe()->except('version'));
        $record->created_by = $request->user()->id;
        $record->status = WccRecord::DRAFT;
        $record->save();

        $record->histories()->create([
            'user_id' => $request->user()->id,
            'from_status' => null,
            'to_status' => WccRecord::DRAFT,
        ]);

        $this->audit->log('WCC created', "{$record->quo_no} — {$record->client}");

        return response()->json([
            'message' => "Saved as record: {$record->quo_no}",
            'record' => $this->summary($record),
        ], 201);
    }

    /**
     * Overwrite an existing record with the current template state.
     *
     * Guarded by the version the client last saw. Expressed as a conditional
     * UPDATE rather than a read-then-compare, so two saves racing on the same
     * version cannot both find it unchanged and both win.
     */
    public function update(SaveWccRequest $request, WccRecord $record): JsonResponse
    {
        // Authorized by SaveWccRequest::authorize(), which runs before validation.
        $data = $request->safe()->except('version');
        $expected = $request->integer('version');

        $written = WccRecord::whereKey($record->id)
            ->where('version', $expected)
            ->update($data + [
                'version' => $expected + 1,
                'updated_at' => now(),
            ]);

        if ($written === 0) {
            $current = $record->fresh();

            return response()->json([
                'message' => "{$current->quo_no} was saved from another session after you opened it.",
                'your_version' => $expected,
                'current_version' => $current->version,
            ], Response::HTTP_CONFLICT);
        }

        $record->refresh();

        $this->audit->log('WCC updated', "{$record->quo_no} — {$record->client}");

        return response()->json([
            'message' => "Record updated: {$record->quo_no}",
            'record' => $this->summary($record),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WccRecord $record): array
    {
        return [
            'id' => $record->id,
            'quo_no' => $record->quo_no,
            'client' => $record->client,
            'status' => $record->status,
            'version' => $record->version,
            'open_url' => route('wcc.open', $record),
            'update_url' => route('wcc.update', $record),
        ];
    }
}
