<?php
namespace App\Traits;

use App\Services\AuditLogService;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            if (auth()->check()) {
                AuditLogService::log([
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->role,
                    'action' => strtolower(class_basename($model)) . '_created',
                    'entity_type' => get_class($model),
                    'entity_id' => $model->id,
                    'description' => auth()->user()->full_name . ' created ' . class_basename($model) . ' #' . $model->id,
                    'new_values' => $model->toArray(),
                    'severity' => 'info',
                ]);
            }
        });

        static::updated(function ($model) {
            if (auth()->check()) {
                AuditLogService::log([
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->role,
                    'action' => strtolower(class_basename($model)) . '_updated',
                    'entity_type' => get_class($model),
                    'entity_id' => $model->id,
                    'description' => auth()->user()->full_name . ' updated ' . class_basename($model) . ' #' . $model->id,
                    'old_values' => $model->getOriginal(),
                    'new_values' => $model->getChanges(),
                    'severity' => 'info',
                ]);
            }
        });

        static::deleted(function ($model) {
            if (auth()->check()) {
                AuditLogService::log([
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->role,
                    'action' => strtolower(class_basename($model)) . '_deleted',
                    'entity_type' => get_class($model),
                    'entity_id' => $model->id,
                    'description' => auth()->user()->full_name . ' deleted ' . class_basename($model) . ' #' . $model->id,
                    'old_values' => $model->toArray(),
                    'severity' => 'warning',
                ]);
            }
        });
    }
}