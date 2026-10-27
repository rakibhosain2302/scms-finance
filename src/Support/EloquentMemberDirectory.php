<?php

namespace SCMS\Finance\Support;

use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Contracts\MemberDirectory;

/** Members from the model in scms-finance.models.member, read through member_columns. */
class EloquentMemberDirectory implements MemberDirectory
{
    public function available(): bool
    {
        return FinanceModels::member() !== null;
    }

    public function find(int|string|null $id): ?Model
    {
        $model = FinanceModels::member();
        if (! $model || $id === null || $id === '') {
            return null;
        }

        // The finance services check the branch themselves: no global scope of the member model hides a record here.
        return $model::query()->withoutGlobalScopes()->find($id);
    }

    public function label(?Model $member): string
    {
        if (! $member) {
            return '—';
        }

        $name = (string) $member->getAttribute($this->column('label', 'name'));
        $number = $this->column('number');

        return $number && filled($member->getAttribute($number)) ? $member->getAttribute($number).' · '.$name : $name;
    }

    public function branchId(Model $member): ?int
    {
        $column = $this->column('branch');
        $value = $column ? $member->getAttribute($column) : null;

        return $value === null ? null : (int) $value;
    }

    public function options(?array $branchIds, ?string $term = null, int $limit = 500): array
    {
        $model = FinanceModels::member();
        if (! $model) {
            return [];
        }

        $query = $model::query()->withoutGlobalScopes();
        if ($branchIds !== null && ($column = $this->column('branch'))) {
            $query->whereIn($column, $branchIds ?: [0]);
        }
        if ($term !== null && $term !== '') {
            $query->where(function ($q) use ($term) {
                foreach ((array) config('scms-finance.member_columns.search', []) as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        return $query->orderBy($this->column('label', 'name'))->limit($limit)->get()
            ->mapWithKeys(fn (Model $m) => [$m->getKey() => $this->label($m)])->all();
    }

    public function labels(iterable $ids): array
    {
        $model = FinanceModels::member();
        $ids = collect($ids)->filter()->unique()->values();
        if (! $model || $ids->isEmpty()) {
            return [];
        }

        return $model::query()->withoutGlobalScopes()->whereKey($ids->all())->get()
            ->mapWithKeys(fn (Model $m) => [$m->getKey() => $this->label($m)])->all();
    }

    private function column(string $key, ?string $default = null): ?string
    {
        return config('scms-finance.member_columns.'.$key, $default) ?: $default;
    }
}
