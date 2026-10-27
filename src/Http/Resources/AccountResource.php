<?php

namespace SCMS\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \SCMS\Finance\Models\Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'subtype' => $this->subtype->value,
            'is_postable' => $this->is_postable,
            'is_active' => $this->is_active,
            'branch_id' => $this->branch_id,
        ];
    }
}
