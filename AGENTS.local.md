<!-- Repo-specific appendix to the shared AGENTS.md. Generic conventions live in AGENTS_ARCH.md (hardlinked). -->

# AGENTS.local.md — ksfraser/traits Library
> Repo-specific overrides for `ksfraser/traits`. Core trait design principles cannot be overridden.
---
## Core Purpose
This is a **shared library** providing reusable PHP traits for all ksfraser projects. Traits provide:
- Cross-cutting concerns (logging, validation, events)
- Entity state management
- Property access control
- Common behaviors extracted from multiple classes
This library is part of the migration from legacy inheritance patterns to trait composition.
---
## Namespace Structure
```
Ksfraser\Traits\
├── CrudEventEmitterTrait      # FA hook_invoke_all CRUD events (created/updated/deleted)
├── EnforceDeclaredPropsTrait  # Restrict property access (replaces magic __get/__set)
├── EntityStateTrait           # Track entity modification state
├── EventEmitterTrait          # Event handling (replaces NOTIFY_* calls)
├── FileLogger                 # PSR-3 FileLogger — writes to company/<comp>/logs/<module>.log
├── HookQueryProviderTrait     # FA inter-module query hook provider (ksf_get_value/ksf_get_values/ksf_set_value)
├── InlinePostActionsTrait     # FA tab inline POST save/delete via a configured upsert action
├── InlineTabRendererTrait     # FA tab renderTabContent flow delegating to a UI renderer
├── LoggerAwareTrait           # PSR-3 logger injection with auto-creation + log() convenience
├── TimestampTrait             # Created/updated timestamps
└── ValidatableTrait           # Validation capability (replaces type validators)
```
---
## CrudEventEmitterTrait — CRUD Event Dispatch
### Purpose
Dispatches standardised `created` / `updated` / `deleted` events via FA's
`hook_invoke_all()` so other modules can react without a direct code dependency.
### Dual Dispatch Pattern
Each event fires under **two** hook names:
| # | Hook Name | Direction | Example |
|---|-----------|-----------|---------|
| 1 | `<module>_<action>_<recordType>` | Targeted | `calendar_created_entry` |
| 2 | `ksf_crud_event` | Broadcast | All modules receive the full payload |
### Usage in Service Classes
```php
use Ksfraser\Traits\CrudEventEmitterTrait;
class CustomerService {
    use CrudEventEmitterTrait;
    public function create(array $data): Customer
    {
        // ... persist customer ...
        $customer = new Customer($data);
        $this->emitCreated('crm', 'customer', $customer->getId(), $data);
        return $customer;
    }
}
```
### Usage in FA hooks.php (Listener Pattern)
```php
class hooks_ksf_FA_Calendar extends hooks {
    // Specific listener — only fires for calendar_created_entry
    function calendar_created_entry(&$payload, $opts = []) {
        $entryId = $payload['record_id'];
        // React to calendar entry creation
    }
    // Generic listener — catches all CRUD events from any module
    function ksf_crud_event(&$payload, $opts = []) {
        if ($payload['action'] === 'deleted' && $payload['module'] === 'crm') {
            // React to any CRM record deletion
        }
    }
}
```
---
## HookQueryProviderTrait — FA Inter-Module Query Hooks
### Purpose
Provides three standard FA hook methods (`ksf_get_value`, `ksf_get_values`,
`ksf_set_value`) so any module's `hooks.php` can advertise values to other
modules without a direct code dependency.
### Usage in hooks.php
```php
class hooks_ksf_FA_MyModule extends hooks {
    use \Ksfraser\Traits\HookQueryProviderTrait;
    protected function _getAdvertisedValues(): array
    {
        return [
            'mymodule.version'       => $this->version,
            'mymodule.module_name'   => $this->module_name,
            'mymodule.hooks_version' => '2.0',
        ];
    }
}
```
### Consumer Pattern
```php
$key = 'calendar.api_version';
$value = hook_invoke_first('ksf_get_value', $key);
if ($value !== null) {
    // Calendar module responded
}
```
IMPORTANT: FA's `hook_invoke_first/all` declare `&$data` (by-reference).
Always pass a variable, never a literal.
---
## InlinePostActionsTrait — Inline Tab POST Actions (v1.4.0)
### Purpose
Encapsulates the common inline tab save pattern used by FrontAccounting module
tab classes: persist POST data through a dedicated upsert action class and show
a notification. Other modules (CRM, HRM, ...) can reuse the same tab pattern
without re-implementing the save/delete/no-hard-refresh behaviour.
### Requirements
| ID    | Requirement | Verified By |
|-------|-------------|-------------|
| IPA-1 | `initUpsertClass()` builds `$this->upsertClass` from `$upsertClassName` | `testConstructorBuildsUpsertClassFromClassName` |
| IPA-2 | `handleSave($stockId, $postData)` persists via `$this->upsertClass` | `testHandleSavePersistsViaUpsertClass` |
| IPA-3 | `handleDelete($stockId)` delegates to `$this->dao->delete()` | `testHandleDeleteDelegatesToDao` |
| IPA-4 | `handlePostActions()` saves only on POST with the tab's own submit button present | `testHandlePostActionsSavesWhenButtonPresent` |
| IPA-5 | No save when the button is absent (product/tab changes do not save) | `testHandlePostActionsSkipsWithoutButton` |
| IPA-6 | No save on GET requests | `testHandlePostActionsSkipsOnGetRequest` |
| IPA-7 | No save for empty stock id | `testHandlePostActionsSkipsEmptyStockId` |
### Properties
| Property | Declared By | Purpose |
|----------|-------------|---------|
| `$dao` | trait | The injected data-access object |
| `$upsertClass` | trait | The upsert action created from `$upsertClassName` |
| `$upsertClassName` | using class (required) | FQCN of the upsert action class |
| `$saveButtonName` | using class (required) | Submit-button name that gates the save |
| `$notificationMessage` | using class (required) | Message shown after a successful save |
### Usage
```php
use Ksfraser\Traits\InlinePostActionsTrait;
class IdentifiersTab extends AbstractTab
{
    use InlinePostActionsTrait;
    protected $upsertClassName     = UpsertProductIdentifiersAction::class;
    protected $saveButtonName      = 'pa_identifiers_save';
    protected $notificationMessage = 'Identifiers saved.';
    public function __construct(ProductIdentifiersDao $dao)
    {
        $this->dao = $dao;
        $this->initUpsertClass();
    }
}
```
---
## InlineTabRendererTrait — Inline Tab Rendering (v1.4.0)
### Purpose
Standardises `renderTabContent()` as "run inline POST actions, then delegate to
a dedicated UI renderer". The using class supplies `$tabClassName` and may
override `createTab()` when the renderer needs extra constructor arguments.
### Properties
| Property | Declared By | Purpose |
|----------|-------------|---------|
| `$tab` | trait | The UI renderer instance produced by `createTab()` |
| `$tabClassName` | using class (required) | FQCN of the UI renderer class |
### Methods
| Method | Behaviour |
|--------|-----------|
| `renderTabContent($stockId)` | Calls `handlePostActions()`, then `createTab()->render($stockId)` |
| `createTab()` | `new $this->tabClassName($this->dao)`; override for extra args |
---
## Version Management
### Version History
| Version | Changes |
|---------|---------|
| 1.4.0 | Added `InlinePostActionsTrait` and `InlineTabRendererTrait` for FA tab inline save/render patterns. |
| 1.3.0 | Added `FileLogger` (PSR-3 file logger) and `LoggerAwareTrait` (logger injection). Added `psr/log` runtime dependency. |
| 1.2.0 | Added `HookQueryProviderTrait` for FA inter-module query hooks. |
| 0.1.0 | Initial release with `CrudEventEmitterTrait`, `EntityStateTrait`, `EventEmitterTrait`, `TimestampTrait`, `ValidatableTrait`, `EnforceDeclaredPropsTrait`. |
---
## Adding New Traits / Classes
1. Ensure trait/class follows single responsibility
2. Document all public methods
3. Note any required collaborators (including Composer dependencies)
4. Write comprehensive tests
5. Update AGENTS.md with Requirements, RTM, Architecture, and UML
6. Update `composer.json` if new runtime dependencies are needed
---
## Development Workflow
All development is done in the **devel tree** (`~/Documents/Traits`).
### Workflow Steps
1. **Develop** in this repo (feature branches preferred)
2. **Test**: run repo-appropriate tests
3. **Lint**: `php -l` on modified PHP files (no syntax errors)
4. **Commit** and **Push** branch to GitHub
5. **Merge** to `master` when ready
6. **Push** `master` to GitHub
### UAT Bind Point
| Path | Purpose |
|------|---------|
| `~/Documents/Traits` | Devel tree — all development, testing, commits |
| `~/ksf_Infrastructure/fa_modules/Traits` | UAT bind point — required so path repos (`../Traits`) resolve for consuming modules in UAT |
