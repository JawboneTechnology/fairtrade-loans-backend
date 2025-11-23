<?php

namespace App\Events;

use App\Models\MpesaTransaction;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessful
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MpesaTransaction $transaction;
    public Loan $loan;
    public User $user;
    public float $newLoanBalance;
    public string $paymentMethod;

    /**
     * Create a new event instance.
     */
    public function __construct(
        MpesaTransaction $transaction, 
        Loan $loan, 
        User $user, 
        float $newLoanBalance,
        string $paymentMethod = 'M-Pesa'
    ) {
        $this->transaction = $transaction;
        $this->loan = $loan;
        $this->user = $user;
        $this->newLoanBalance = $newLoanBalance;
        $this->paymentMethod = $paymentMethod;
    }
}
