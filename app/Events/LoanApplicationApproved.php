<?php

namespace App\Events;

use App\Models\LoanApplication;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LoanApplication $loanApplication
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('loan-applications.' . $this->loanApplication->id),
        ];
    }
}