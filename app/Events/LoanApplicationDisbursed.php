<?php

namespace App\Events;

use App\Models\LoanApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicationDisbursed
{
    use Dispatchable, SerializesModels;

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