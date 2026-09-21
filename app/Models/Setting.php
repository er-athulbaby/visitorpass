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

    public function onPrimaryColor(): string
    {
        $hex = ltrim($this->primary_color ?? '#0F172A', '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $toLinear = fn (float $channel) => $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;

        $r = $toLinear(hexdec(substr($hex, 0, 2)) / 255);
        $g = $toLinear(hexdec(substr($hex, 2, 2)) / 255);
        $b = $toLinear(hexdec(substr($hex, 4, 2)) / 255);

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.179 ? '#000000' : '#FFFFFF';
    }
}
