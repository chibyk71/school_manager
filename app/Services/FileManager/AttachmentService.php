<?php

namespace App\Services\FileManager;

use App\Enums\FileManager\Capability;
use App\Models\Employee\Staff;
use App\Models\FileManager\Attachment;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\Guardian;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\FileManager\AttachableOwnerRegistry;
use App\Support\FileManager\FolderCapabilityResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Explicit File ↔ business-owner links with dual authorization and allow-listed owners.
 *
 * Owner authorization is mandatory: every allow-listed type has an explicit school-boundary
 * rule. When ownership cannot be verified, the operation is denied (fail closed).
 */
final class AttachmentService
{
    public function __construct(
        private readonly FileService $files,
    ) {}

    /**
     * @return Collection<int, Attachment>
     */
    public function listForFile(File $file, ?string $actingSchoolId): Collection
    {
        $resolved = $this->files->findForContext($file->id, $actingSchoolId);
        if ($resolved === null) {
            throw new InvalidArgumentException('File is not accessible in the current context.');
        }

        return Attachment::query()->where('file_id', $file->id)->orderBy('created_at')->get();
    }

    /**
     * @param  array{owner_type: string, owner_id: string, purpose?: string, lifecycle?: string}  $data
     */
    public function attach(File $file, array $data, ?string $actingSchoolId): Attachment
    {
        $resolved = $this->files->findForContext($file->id, $actingSchoolId);
        if ($resolved === null) {
            throw new InvalidArgumentException('File is not accessible in the current context.');
        }

        $this->assertFileAttachCapability($file);

        $ownerClass = AttachableOwnerRegistry::resolve((string) $data['owner_type']);
        $ownerId = (string) $data['owner_id'];

        /** @var Model|null $owner */
        $owner = $ownerClass::query()->find($ownerId);
        if ($owner === null) {
            throw new InvalidArgumentException('Attachment owner not found.');
        }

        // Mandatory owner authorization — fail closed when school boundary cannot be verified.
        $this->assertOwnerAuthorized($file, $owner, $actingSchoolId);

        $attachment = new Attachment([
            'file_id' => $file->id,
            'owner_type' => $ownerClass,
            'owner_id' => $ownerId,
            'purpose' => (string) ($data['purpose'] ?? 'default'),
            'lifecycle' => (string) ($data['lifecycle'] ?? 'module'),
        ]);
        $attachment->save();

        return $attachment->fresh();
    }

    public function detach(Attachment $attachment, ?string $actingSchoolId): void
    {
        $file = $attachment->file;
        if ($file === null) {
            $attachment->delete();

            return;
        }

        $resolved = $this->files->findForContext($file->id, $actingSchoolId);
        if ($resolved === null) {
            throw new InvalidArgumentException('File is not accessible in the current context.');
        }

        $this->assertFileAttachCapability($file);

        // Detach does not delete the physical object or the File row.
        $attachment->delete();
    }

    /**
     * Enforce school / ownership boundary for every allow-listed owner type.
     *
     * Deny when the relationship cannot be verified (e.g. Profile/User with no
     * link to the acting school).
     */
    private function assertOwnerAuthorized(File $file, Model $owner, ?string $actingSchoolId): void
    {
        // System-owned files may only attach to system-scoped owners in platform context.
        if ($file->isSystemOwned()) {
            if ($actingSchoolId !== null) {
                throw new InvalidArgumentException(
                    'System-owned files cannot be attached from a school context.'
                );
            }
            // Platform context: School owners must not be used for system files (cross-tenant).
            if ($owner instanceof School) {
                throw new InvalidArgumentException(
                    'Cannot attach a system-owned file to a school owner.'
                );
            }
            // User/Profile without school scope are allowed in platform context for system files.
            if ($owner instanceof Student || $owner instanceof Guardian || $owner instanceof Staff) {
                throw new InvalidArgumentException(
                    'Cannot attach a system-owned file to a school-scoped owner.'
                );
            }

            return;
        }

        // School-owned files require a school context matching the file.
        if ($actingSchoolId === null) {
            throw new InvalidArgumentException(
                'School context is required to attach school-owned files.'
            );
        }

        if ((string) $file->school_id !== (string) $actingSchoolId) {
            throw new InvalidArgumentException(
                'File is not accessible in the current school context.'
            );
        }

        if (! $this->ownerBelongsToSchool($owner, $actingSchoolId)) {
            throw new InvalidArgumentException(
                'Attachment owner is not authorized for the current school context.'
            );
        }
    }

    /**
     * Explicit per-type school membership verification. Fail closed.
     */
    private function ownerBelongsToSchool(Model $owner, string $schoolId): bool
    {
        if ($owner instanceof School) {
            return (string) $owner->id === (string) $schoolId;
        }

        if ($owner instanceof Student) {
            return $owner->school_id !== null
                && (string) $owner->school_id === (string) $schoolId;
        }

        if ($owner instanceof Guardian) {
            // Nullable school_id is not sufficient — require explicit school match.
            return $owner->school_id !== null
                && (string) $owner->school_id === (string) $schoolId;
        }

        if ($owner instanceof Staff) {
            return $owner->school_id !== null
                && (string) $owner->school_id === (string) $schoolId;
        }

        if ($owner instanceof Profile) {
            return $this->profileLinkedToSchool($owner, $schoolId);
        }

        if ($owner instanceof User) {
            return $this->userLinkedToSchool($owner, $schoolId);
        }

        // Unknown allow-listed type without a rule → deny.
        return false;
    }

    private function profileLinkedToSchool(Profile $profile, string $schoolId): bool
    {
        if ($profile->students()->where('school_id', $schoolId)->exists()) {
            return true;
        }
        if ($profile->guardians()->where('school_id', $schoolId)->exists()) {
            return true;
        }
        if ($profile->staffPositions()->where('school_id', $schoolId)->exists()) {
            return true;
        }

        return false;
    }

    private function userLinkedToSchool(User $user, string $schoolId): bool
    {
        if ($user->schools()->where('schools.id', $schoolId)->exists()) {
            return true;
        }

        $profile = $user->profile ?? null;
        if ($profile instanceof Profile) {
            return $this->profileLinkedToSchool($profile, $schoolId);
        }

        return false;
    }

    private function assertFileAttachCapability(File $file): void
    {
        if ($file->folder_id === null) {
            return;
        }

        $folder = Folder::query()->find($file->folder_id);
        if ($folder === null) {
            return;
        }

        FolderCapabilityResolver::assertAllows($folder, Capability::Attach);
    }
}
