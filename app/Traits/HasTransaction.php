<?php

namespace App\Traits;

use App\Models\Transaction;

trait HasTransaction
{
    /**
     * Define a polymorphic one-to-one relationship with Transaction.
     */
    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'payable');
    }

    /**
     * Create a transaction for the payable model.
     *
     * @param array $data Transaction data.
     * @return Transaction
     */
    public function createTransaction(array $data): Transaction
    {
        // Merge default values with provided data
        $transactionData = array_merge([
            'transaction_type' => $this->getTransactionType(),
            'category' => $this->getCategory(),
            'amount' => $this->getAmount(),
            'school_id' => $this->getSchoolId(),
            'transaction_date' => now(),
            'recorded_by' => auth()->id(),
        ], $data);

        return $this->transaction()->create($transactionData);
    }

    /**
     * Get the transaction type.
     *
     * @return string
     */
    public function getTransactionType(): string
    {
        return 'income'; // Default to income; override in model if needed
    }

    /**
     * Get the category of the transaction.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'miscellaneous'; // Default category; override in model if needed
    }

    /**
     * Get the amount for the transaction.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount ?? 0.0; // Assumes the payable model has an `amount` attribute
    }

    /**
     * Get the school ID for the transaction.
     *
     * @return int|null
     */
    public function getSchoolId(): ?int
    {
        return $this->school_id ?? null; // Assumes the payable model has a `school_id` attribute
    }
}
