<?php

namespace App\Services\FileManager;

use App\Models\FileManager\Attachment;
use App\Models\FileManager\File;
use App\Support\FileManager\AttachableOwnerRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Explicit File ↔ business-owner links with dual authorization and allow-listed owners.
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
    public function attach(File $file, array $data, ?string $actingSchoolId, ?callable $authorizeOwner = null): Attachment
    {
        $resolved = $this->files->findForContext($file->id, $actingSchoolId);
        if ($resolved === null) {
            throw new InvalidArgumentException('File is not accessible in the current context.');
        }

        $ownerClass = AttachableOwnerRegistry::resolve((string) $data['owner_type']);
        $ownerId = (string) $data['owner_id'];

        /** @var Model|null $owner */
        $owner = $ownerClass::query()->find($ownerId);
        if ($owner === null) {
            throw new InvalidArgumentException('Attachment owner not found.');
        }

        $this->assertOwnerSchoolBoundary($file, $owner, $actingSchoolId);

        if ($authorizeOwner !== null) {
            $authorizeOwner($owner);
        }

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

        // Detach does not delete the physical object or the File row.
        $attachment->delete();
    }

    private function assertOwnerSchoolBoundary(File $file, Model $owner, ?string $actingSchoolId): void
    {
        // If the owner has a school_id attribute, it must match school-owned files.
        if ($file->isSchoolOwned() && isset($owner->school_id) && $owner->school_id !== null) {
            if ((string) $owner->school_id !== (string) $file->school_id) {
                throw new InvalidArgumentException(
                    'Cannot attach a file to an owner that belongs to a different school.'
                );
            }
        }

        if ($actingSchoolId !== null && isset($owner->school_id) && $owner->school_id !== null) {
            if ((string) $owner->school_id !== (string) $actingSchoolId) {
                throw new InvalidArgumentException(
                    'Cannot attach to an owner outside the current school context.'
                );
            }
        }
    }
}
