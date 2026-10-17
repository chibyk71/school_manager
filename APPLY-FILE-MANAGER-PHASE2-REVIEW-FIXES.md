# Apply remaining Phase 2 review fixes

Partial remote tip already includes:
- FolderCapabilityResolver.php
- UploadFileRequest.php (no managed_by)
- AttachmentService.php (fail-closed owner auth)
- FolderService.php (operation-time capabilities)

Still apply from `file-manager-phase2-review-fixes.patch` / `.bundle` for:
- app/Services/FileManager/FileService.php (registerModuleManaged, strip client managed_by, capability checks)
- tests/Feature/FileManager/FileManagerDomainTest.php (regression tests)

Local complete commit: `5081cecb1e744e6513ddc96fe68283a76dfbda07`

```bash
git fetch origin
git checkout feature/file-manager-phase2-core-domain
git apply file-manager-phase2-review-fixes.patch
# resolves cleanly if partial files already match
```
