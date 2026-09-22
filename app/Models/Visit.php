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
                $escaped = addcslashes($search, '%_\\');
                $q->where('mobile_number', 'like', "%{$escaped}%")
                    ->orWhereHas('visitor', function ($vq) use ($escaped) {
                        $vq->where('name', 'like', "%{$escaped}%")
                            ->orWhere('cpr_number', 'like', "%{$escaped}%")
                            ->orWhere('mobile_number', 'like', "%{$escaped}%")
                            ->orWhere('company_name', 'like', "%{$escaped}%");
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
