<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = [
        'visitor_id',
        'mobile_number',
        'employee_id',
        'department_id',
        'company_id',
        'check_in_at',
        'check_out_at',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('visitor', function ($vq) use ($search) {
                    $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('cpr_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->where('check_in_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->where('check_in_at', '<=', $date.' 23:59:59'))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when(($filters['status'] ?? null) === 'inside', fn ($q) => $q->whereNull('check_out_at'))
            ->when(($filters['status'] ?? null) === 'checked_out', fn ($q) => $q->whereNotNull('check_out_at'));
    }
}
