<?php

namespace App\Services\School;

use App\Models\School;
use Illuminate\Support\Facades\Validator;

/**
 * Evaluates the five Phase 1 readiness conditions for Draft → Ready.
 *
 * Conditions (exactly five):
 * 1. School name present
 * 2. School code present and unique within the tenant database
 * 3. School email present and valid
 * 4. Primary contact: contact_name + at least one of email / phone_one / phone_two
 * 5. Primary address present via HasAddress
 *
 * School type is optional on create/edit and does not affect readiness or feature access.
 * Sections, description, motto, and media do not affect readiness.
 */
final class SchoolReadinessEvaluator
{
    public const CONDITION_NAME = 'name';

    public const CONDITION_CODE = 'code';

    public const CONDITION_EMAIL = 'email';

    public const CONDITION_PRIMARY_CONTACT = 'primary_contact';

    public const CONDITION_PRIMARY_ADDRESS = 'primary_address';

    /**
     * @return array{
     *     ready: bool,
     *     conditions: array<string, array{passed: bool, message: string|null}>
     * }
     */
    public function evaluate(School $school): array
    {
        $conditions = [
            self::CONDITION_NAME => $this->checkName($school),
            self::CONDITION_CODE => $this->checkCode($school),
            self::CONDITION_EMAIL => $this->checkEmail($school),
            self::CONDITION_PRIMARY_CONTACT => $this->checkPrimaryContact($school),
            self::CONDITION_PRIMARY_ADDRESS => $this->checkPrimaryAddress($school),
        ];

        $ready = collect($conditions)->every(fn (array $c) => $c['passed'] === true);

        return [
            'ready' => $ready,
            'conditions' => $conditions,
        ];
    }

    public function isReady(School $school): bool
    {
        return $this->evaluate($school)['ready'];
    }

    /**
     * @return array{passed: bool, message: string|null}
     */
    private function checkName(School $school): array
    {
        if (filled($school->name) && is_string($school->name) && trim($school->name) !== '') {
            return ['passed' => true, 'message' => null];
        }

        return ['passed' => false, 'message' => 'School name is required.'];
    }

    /**
     * @return array{passed: bool, message: string|null}
     */
    private function checkCode(School $school): array
    {
        if (! filled($school->code) || ! is_string($school->code) || trim($school->code) === '') {
            return ['passed' => false, 'message' => 'School code is required.'];
        }

        $exists = School::query()
            ->where('code', $school->code)
            ->when($school->getKey(), fn ($q) => $q->whereKeyNot($school->getKey()))
            ->exists();

        if ($exists) {
            return ['passed' => false, 'message' => 'School code must be unique within the tenant database.'];
        }

        return ['passed' => true, 'message' => null];
    }

    /**
     * @return array{passed: bool, message: string|null}
     */
    private function checkEmail(School $school): array
    {
        if (! filled($school->email)) {
            return ['passed' => false, 'message' => 'School email is required.'];
        }

        $validator = Validator::make(
            ['email' => $school->email],
            ['email' => ['required', 'email']]
        );

        if ($validator->fails()) {
            return ['passed' => false, 'message' => 'School email must be a valid email address.'];
        }

        return ['passed' => true, 'message' => null];
    }

    /**
     * Primary contact is a person (contact_name), not the institution name.
     * At least one of email / phone_one / phone_two must be present.
     *
     * @return array{passed: bool, message: string|null}
     */
    private function checkPrimaryContact(School $school): array
    {
        if (! filled($school->contact_name) || ! is_string($school->contact_name) || trim($school->contact_name) === '') {
            return [
                'passed' => false,
                'message' => 'Primary contact name is required (contact person, not the institution name).',
            ];
        }

        $hasChannel = filled($school->email)
            || filled($school->phone_one)
            || filled($school->phone_two);

        if (! $hasChannel) {
            return [
                'passed' => false,
                'message' => 'Primary contact requires at least one phone number or email.',
            ];
        }

        return ['passed' => true, 'message' => null];
    }

    /**
     * @return array{passed: bool, message: string|null}
     */
    private function checkPrimaryAddress(School $school): array
    {
        $primary = $school->primaryAddress();

        if ($primary === null) {
            return ['passed' => false, 'message' => 'Primary address is required.'];
        }

        return ['passed' => true, 'message' => null];
    }
}
