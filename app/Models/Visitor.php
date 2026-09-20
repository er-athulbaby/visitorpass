<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    protected $fillable = ['cpr_number', 'name', 'company_name', 'mobile_number'];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
