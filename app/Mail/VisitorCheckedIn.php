<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitorCheckedIn extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Visit $visit)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Visitor arrived: '.$this->visit->visitor->name)
            ->view('emails.visitor-checked-in');
    }
}
