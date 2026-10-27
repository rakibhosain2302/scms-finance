<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Support\FinanceModels;

/** A chart of accounts entry. Group accounts (is_postable = false) only add up their children. */
class Account extends FinanceModel
{
    use HasCreator;

    protected $table = 'finance_accounts';

    protected $fillable = ['parent_id', 'code', 'name', 'type', 'subtype', 'is_postable', 'branch_id', 'is_active', 'description'];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::branch(), 'branch_id');
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function scopeOfType(Builder $query, AccountType|string ...$types): Builder
    {
        return $query->whereIn('type', array_map(fn ($t) => $t instanceof AccountType ? $t->value : $t, $types));
    }

    public function scopeOfSubtype(Builder $query, AccountSubtype|string ...$subtypes): Builder
    {
        return $query->whereIn('subtype', array_map(fn ($t) => $t instanceof AccountSubtype ? $t->value : $t, $subtypes));
    }

    /** Shared accounts and the ones limited to the branch. */
    public function scopeUsableIn(Builder $query, ?int $branchId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('branch_id')->when($branchId, fn ($q) => $q->orWhere('branch_id', $branchId)));
    }

    public function isUsableIn(?int $branchId): bool
    {
        return $this->branch_id === null || (int) $this->branch_id === (int) $branchId;
    }

    /**
     * Postable accounts for a select, as id => "code · name".
     *
     * @return array<int, string>
     */
    public static function options(?int $branchId = null, AccountType|string|null $type = null, AccountSubtype|string|null $subtype = null): array
    {
        return static::query()->postable()->usableIn($branchId)
            ->when($type, fn ($q) => $q->ofType($type))
            ->when($subtype, fn ($q) => $q->ofSubtype($subtype))
            ->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->name])->all();
    }
}
