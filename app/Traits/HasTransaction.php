<?php

namespace App\Traits;

use App\Models\Finance\Transaction;
use App\Models\School;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Trait HasTransaction
 *
 * Provides functionality to manage polymorphic transactions for a model.
 */
trait HasTransaction
{
    /**
     * Define a polymorphic one-to-many relationship with Transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    /**
     * Create a transaction for the payable model.
     *
     * @param array $data Transaction data (e.g., amount, transaction_type).
     * @return Transaction The created transaction.
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If transaction creation fails.
     */
    public function createTransaction(array $data): Transaction
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $validator = Validator::make($data, [
                'amount' => 'sometimes|numeric|min:0',
                'transaction_type' => 'sometimes|string|in:income,expense',
                'category' => 'sometimes|string|max:100',
                'transaction_date' => 'sometimes|date',
                'description' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $transactionData = array_merge([
                'transaction_type' => $this->getTransactionType(),
                'category' => $this->getCategory(),
                'amount' => $this->getAmount(),
                'school_id' => $school->id,
                'transaction_date' => now(),
                'recorded_by' => auth()->id(),
            ], $data);

            return $this->transactions()->create($transactionData);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create transaction: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the transaction type.
     *
     * @return string The transaction type (e.g., 'income', 'expense').
     */
    public function getTransactionType(): string
    {
        return 'income';
    }

    /**
     * Get the category of the transaction.
     *
     * @return string The transaction category.
     */
    public function getCategory(): string
    {
        return 'miscellaneous';
    }

    /**
     * Get the amount for the transaction.
     *
     * @return float The transaction amount.
     * @throws \Exception If amount is not defined.
     */
    public function getAmount(): float
    {
        if (!isset($this->amount)) {
            throw new \Exception('Amount not defined for model ' . get_class($this));
        }
        return $this->amount;
    }

    /**
     * Get the school ID for the transaction.
     *
     * @return int The school ID.
     * @throws \Exception If school ID is not defined.
     */
    public function getSchoolId(): int
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \Exception('No active school found.');
        }
        return $school->id;
    }
}
