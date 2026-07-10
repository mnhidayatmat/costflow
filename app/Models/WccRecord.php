<?php

namespace App\Models;

use App\Support\Search;
use Database\Factories\WccRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['quo_no', 'client', 'title', 'dept', 'manager', 'planned_cost', 'selling', 'actual', 'snapshot'])]
class WccRecord extends Model
{
    /** @use HasFactory<WccRecordFactory> */
    use HasFactory;

    public const DRAFT = 'Draft';

    public const COSTED = 'Costed';

    public const SUBMITTED = 'Submitted';

    public const APPROVED = 'Approved';

    public const RETURNED = 'Returned';

    /**
     * Which statuses a record may legally move to next.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::DRAFT => [self::COSTED],
        self::COSTED => [self::SUBMITTED],
        self::RETURNED => [self::SUBMITTED],
        self::SUBMITTED => [self::APPROVED, self::RETURNED],
        self::APPROVED => [],
    ];

    /**
     * Transitions only Management may perform.
     *
     * @var list<string>
     */
    public const MANAGEMENT_ONLY = [self::APPROVED, self::RETURNED];

    /**
     * Mirror the database defaults so a freshly instantiated record already
     * knows its status and version, rather than reading them back as null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::DRAFT,
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_cost' => 'decimal:2',
            'selling' => 'decimal:2',
            'actual' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WccStatusHistory::class)->latest('created_at');
    }

    /* ----------------------------------------------------------------
     | Derived money
     |----------------------------------------------------------------*/

    /**
     * Actual cost once WCC2 has been filled in, otherwise the WCC1 plan.
     */
    public function effectiveCost(): float
    {
        return (float) ($this->actual > 0 ? $this->actual : $this->planned_cost);
    }

    public function profit(): float
    {
        return (float) $this->selling - $this->effectiveCost();
    }

    public function marginPercent(): float
    {
        return $this->selling > 0 ? $this->profit() / (float) $this->selling * 100 : 0.0;
    }

    /* ----------------------------------------------------------------
     | Workflow
     |----------------------------------------------------------------*/

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Once approved, the sheet is frozen — matching the PO-confirmed lock in
     * the template, which makes WCC1 and the BPE Price Sheet read-only.
     */
    public function isLocked(): bool
    {
        return $this->status === self::APPROVED;
    }

    /* ----------------------------------------------------------------
     | Scopes
     |----------------------------------------------------------------*/

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    /** Still moving through costing and review. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::DRAFT, self::COSTED, self::SUBMITTED]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return Search::across($query, ['quo_no', 'client', 'title', 'manager'], $term);
    }
}
