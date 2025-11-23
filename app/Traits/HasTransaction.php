<?php

namespace App\Traits;

use App\Models\Finance\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trait HasTransaction
 *
 * Automatically creates a Transaction ledger entry when used on payable models.
 */
trait HasTransaction
{
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    /**
     * Create a transaction record for this model.
     */
    public function createTransaction(array $overrides = []): Transaction
    {
        return DB::transaction(function () use ($overrides) {
            $school = GetSchoolModel();

            $data = array_merge([
                'school_id' => $school->id,
                'transaction_type' => $this->getTransactionType(),
                'category' => $this->getCategory(),
                'amount' => $this->getAmount(),
                'transaction_date' => now(),
                'recorded_by' => auth()->id(),
                'description' => $this->getTransactionDescription(),
            ], $overrides);

            return $this->transactions()->create($data);
        });
    }

    /** Implement in model */
    abstract public function getTransactionType(): string; // 'income' or 'expense'
    abstract public function getCategory(): string;
    abstract public function getAmount(): float;

    public function getTransactionDescription(): ?string
    {
        return $this->description ?? null;
    }
}
