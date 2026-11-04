<?php

namespace App\Services\UserManagement;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Independent Person (Profile) creation and basic identity updates.
 *
 * Person is creatable without Student / Staff / Guardian capacity and without
 * an Authentication Account. Downstream domains attach capacities separately.
 */
class PersonService
{
    /**
     * @param  array{
     *   first_name: string,
     *   last_name: string,
     *   middle_name?: ?string,
     *   title?: ?string,
     *   gender?: ?string,
     *   date_of_birth?: ?string,
     *   phone?: ?string,
     *   email?: ?string,
     *   notes?: ?string
     * }  $data
     */
    public function create(array $data): Profile
    {
        if (empty($data['first_name']) || empty($data['last_name'])) {
            throw ValidationException::withMessages([
                'first_name' => 'First and last name are required to create a person.',
            ]);
        }

        return DB::transaction(function () use ($data) {
            return Profile::create([
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
