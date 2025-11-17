<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoneyTransferred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;
    public $sender;
    public $receiver;

    public function __construct($transaction, $sender, $receiver)
    {
        $this->transaction = $transaction;
        $this->sender = [
            'id' => $sender->id,
            'balance' => (string)$sender->balance,
        ];
        $this->receiver = [
            'id' => $receiver->id,
            'balance' => (string)$receiver->balance,
        ];
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->sender['id']),
            new PrivateChannel('user.' . $this->receiver['id']),
        ];
    }

    public function broadcastAs()
    {
        return 'MoneyTransferred';
    }

    public function broadcastWith()
    {
        return [
            'transaction' => $this->transaction->toArray(),
            'sender' => $this->sender,
            'receiver' => $this->receiver,
        ];
    }
}
