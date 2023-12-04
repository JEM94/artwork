<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public Authenticatable $user, mixed $token)
    {
    }

    public function build(): static
    {
        return $this->from("einladung@test.de", $this->user->first_name)
            ->replyTo($this->user->email)
            ->subject("Einladung für das artwork")
            ->markdown('emails.invitations');
    }
}
