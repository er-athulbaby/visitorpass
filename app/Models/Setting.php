<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'id',
        'deployment_mode',
        'primary_color',
        'logo_path',
        'favicon_path',
        'tagline',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password_encrypted',
        'smtp_from_address',
    ];

    public static function current(): ?self
    {
        return static::find(1);
    }

    public static function isComplete(): bool
    {
        return static::current() !== null;
    }
}
