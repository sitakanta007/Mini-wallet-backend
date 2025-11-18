<?php

namespace App\Http\Controllers;

use App\Events\MoneyTransferred;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionController extends Controller
{
    // GET /api/transactions
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::with(['sender:id,name', 'receiver:id,name'])
            ->where(function($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('receiver_id', $user->id);
        })
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return response()->json([
            'balance' => $user->balance,
            'transactions' => $transactions->through(function ($t) {
                return [
                    'id' => $t->id,
                    'sender_id' => $t->sender_id,
                    'sender_name' => $t->sender?->name,
                    'receiver_id' => $t->receiver_id,
                    'receiver_name' => $t->receiver?->name,
                    'amount' => $t->amount,
                    'commission_fee' => $t->commission_fee,
                    'status' => $t->status,
                    'idempotency_key' => $t->idempotency_key,
                    'created_at' => $t->created_at,
                ];
            })
        ]);
    }

    // POST /api/transactions
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'idempotency_key' => 'nullable|string',
        ]);

        // If frontend didn't send a key → generate one
        $idempotencyKey = $data['idempotency_key'] ?? uniqid('idem_', true);

        // Check if a transaction with this key already exists
        $existingTx = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existingTx) {
            // Re-fetch sender after previous transaction
            $sender = User::find($existingTx->sender_id);
            return response()->json([
                'message' => 'Transfer already processed',
                'transaction' => $existingTx,
                'balance' => $sender->balance,
                'idempotent' => true
            ]);
        }

        $receiverId = (int) $data['receiver_id'];
        $amount = round((float) $data['amount'], 2);

        if ($receiverId === $user->id) {
            throw ValidationException::withMessages([
                'receiver_id' => 'Cannot send to own account.',
            ]);
        }

        $commission = round($amount * 0.015, 2);
        $totalDebit = round($amount + $commission, 2);

        $attempts = 5;

        while ($attempts--) {
            try {
                return DB::transaction(function() use (
                    $user, $receiverId, $amount, $commission,
                    $totalDebit, $idempotencyKey
                ) {
                    // Atomic debit
                    $updated = DB::update(
                        'UPDATE users SET balance = balance - ?, version = version + 1
                        WHERE id = ? AND balance >= ?',
                        [$totalDebit, $user->id, $totalDebit]
                    );

                    if ($updated === 0) {
                        return response()->json(['error' => 'Insufficient balance'], 422);
                    }

                    // Credit receiver
                    $receiverUpdated = DB::update(
                        'UPDATE users SET balance = balance + ?, version = version + 1
                        WHERE id = ?',
                        [$amount, $receiverId]
                    );

                    if ($receiverUpdated === 0) {
                        throw new \Exception('Receiver not found or credit failed.');
                    }

                    // Insert transaction WITH idempotency key
                    $tx = Transaction::create([
                        'sender_id' => $user->id,
                        'receiver_id' => $receiverId,
                        'amount' => $amount,
                        'commission_fee' => $commission,
                        'status' => 'completed',
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    $sender = User::findOrFail($user->id);
                    $receiver = User::findOrFail($receiverId);

                    //TODO
                    //event(new MoneyTransferred($tx, $sender, $receiver));

                    $tx->load(['sender:id,name', 'receiver:id,name']);
                    return response()->json([
                        'message' => 'Transfer completed',
                        'transaction' => [
                            'id' => $tx->id,
                            'sender_id' => $tx->sender_id,
                            'sender_name' => $tx->sender?->name,
                            'receiver_id' => $tx->receiver_id,
                            'receiver_name' => $tx->receiver?->name,
                            'amount' => $tx->amount,
                            'commission_fee' => $tx->commission_fee,
                            'status' => $tx->status,
                            'idempotency_key' => $tx->idempotency_key,
                            'created_at' => $tx->created_at,
                        ],
                        'balance' => $sender->balance,
                        'idempotent' => false
                    ], 201);

                }, 5);
            } catch (Throwable $e) {
                if (str_contains(strtolower($e->getMessage()), 'deadlock')) {
                    usleep(150000);
                    continue;
                }
                throw $e;
            }
        }

        return response()->json(['error' => 'Transfer failed after multiple retries'], 500);
    }
}
