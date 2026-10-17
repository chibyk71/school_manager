# Apply File Manager Phase 2

Remote branch `feature/file-manager-phase2-core-domain` was created from `master` @ `d5da5e79fa616489d1578569a142b55f3b845731`.

Full implementation is in the repository artifacts / local commit `fbaa2ddfd77dbed5e30300db052fd2c4a37f7d14`.

## Apply from patch (recommended)

```bash
git fetch origin
git checkout feature/file-manager-phase2-core-domain
git apply file-manager-phase2-core-domain.patch
# or: git pull the bundle
git bundle unbundle file-manager-phase2-core-domain.bundle
git cherry-pick fbaa2ddfd77dbed5e30300db052fd2c4a37f7d14
```

## Scope delivered locally

- Models: Folder, File, Attachment, SchoolModuleConfiguration under `App\Models\FileManager`
- ModuleDefinitionRegistry + AttachableOwnerRegistry
- FolderService, FileService, AttachmentService, FileObjectStorage, ModuleConfigurationService
- Policies + permissions: file-manager.view|manage|attach|configure
- Routes under /file-manager, Browser.vue, Docent phase-2 docs
- Focused domain tests (FileManagerDomainTest)
- Migration 2026_10_17_100000_create_file_manager_tables

## Note on GitHub push

Authenticated HTTPS git push was unavailable in the agent environment; GitHub MCP `push_files` was used for partial upload. Merge the patch/bundle to complete the branch tip.
