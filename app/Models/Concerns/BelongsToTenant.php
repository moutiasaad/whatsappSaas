<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public function resolveRouteBinding($value, $field = null): ?static
    {
        return $this->withoutGlobalScope('tenant')->findOrFail($value);
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('current_tenant_id') && app('current_tenant_id')) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', app('current_tenant_id'));
            }
        });

        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = app()->has('current_tenant_id') && app('current_tenant_id')
                    ? app('current_tenant_id')
                    : auth()->user()?->tenant_id;
            }
        });
    }
}
