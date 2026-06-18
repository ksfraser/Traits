# AGENTS.md - ksfraser/traits Library

> **DO NOT MODIFY THIS FILE.** Create `AGENTS.local.md` for project-specific overrides.

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
├── LoggerAwareTrait           # PSR-3 logger injection with auto-creation + log() convenience
├── TimestampTrait             # Created/updated timestamps
└── ValidatableTrait           # Validation capability (replaces type validators)
```

---

## Legacy Migration Pattern

### The Problem with Legacy Inheritance

Old modules had deep inheritance hierarchies with:
- Magic methods (`__get`, `__set`) calling type validators
- Event notifications in setters (`NOTIFY_*`, `NOTIFY_LOG`)
- CRUD hooks via `hook_invoke_all()`

```php
// OLD: Deep inheritance with magic methods
class Customer extends BaseCRM {
    protected $name;
    
    public function __set($k, $v) {
        validate_type($k, $v);  // Type validation in magic setter
        $this->$k = $v;
        $this->notify("NOTIFY_SET_{$k}", $v);  // Event notification
    }
}
```

### The Trait Composition Solution

Replace inheritance with trait composition:

```php
// NEW: Trait-based composition
class Customer {
    use ValidatableTrait;      // Replaces type validation in __set
    use EventEmitterTrait;     // Replaces NOTIFY_* calls
    use EntityStateTrait;      // Replaces state tracking in parent
    use TimestampTrait;         // Created/updated timestamps
    
    private ?string $name = null;
    
    public function setName(string $name): self
    {
        $this->assertNotEmptyString($name, 'name');  // Validation via trait
        $this->name = $name;
        $this->markModified();                        // State tracking via trait
        $this->emit('customer.name.changed', $name);  // Event via trait
        return $this;
    }
}
```

### Trait Responsibilities

| Legacy Pattern | Trait Replacement | Purpose |
|----------------|-------------------|---------|
| Magic `__set` with validators | `ValidatableTrait` | Type and business validation |
| `NOTIFY_*` calls | `EventEmitterTrait` | Event notifications |
| `hook_invoke_all` in setters | `CrudEventEmitterTrait` | Standardised CRUD events |
| Parent state tracking | `EntityStateTrait` | Track modified/new state |
| Manual timestamps | `TimestampTrait` | Created/updated management |
| Magic `__get` for virtual props | `EnforceDeclaredPropsTrait` | Property access control |

---

## Trait Design Guidelines

### Single Responsibility
```php
trait EntityStateTrait
{
    private bool $modified = false;

    public function isModified(): bool
    {
        return $this->modified;
    }

    protected function markModified(): void
    {
        $this->modified = true;
    }
}
```

### Composition Over Inheritance
- Traits should be small and focused
- Combine multiple traits for complex behavior
- Avoid stateful conflicts between traits

### Dependency Management
- Traits should not hardcode dependencies
- Use setter injection or abstract methods
- Document required collaborators

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

### Compared to EventEmitterTrait

| Aspect | EventEmitterTrait | CrudEventEmitterTrait |
|--------|-------------------|-----------------------|
| Scope | In-process observer | Cross-module via FA hooks |
| Transport | Callable array | `hook_invoke_all()` |
| Listener binding | `on()` / `off()` methods | Method name = hook name |
| Persistence | Per-request only | Per-request only |
| Use case | Intra-class events | Inter-module CRUD notifications |

### When to Use Which

- **EventEmitterTrait** — when a single class needs to notify its own listeners
  (e.g. `customer.name.changed` within the same process).
- **CrudEventEmitterTrait** — when a service needs to notify other FA modules
  that a record was created/updated/deleted.

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

### Methods Provided

| Method | Dispatched By | Behavior |
|--------|--------------|----------|
| `ksf_get_value(&$key, $opts)` | `hook_invoke_first('ksf_get_value', $key)` | Returns value for a known key, null otherwise |
| `ksf_get_values(&$keys, $opts)` | `hook_invoke_all('ksf_get_values', $keys)` | Returns matching key-value pairs (or all if keys empty) |
| `ksf_set_value(&$data, $opts)` | `hook_invoke_all('ksf_set_value', $data)` | No-op by default; override to accept pushed values |

### Abstract Method

Modules MUST implement `_getAdvertisedValues(): array` returning an
associative array of namespaced key => value pairs. Keys use the
convention `<module>.<name>` (e.g. `calendar.api_version`).

### Composer Autoloading

The trait must be available at class-load time. Add this bootstrap at the
top of `hooks.php` (before the class definition):

```php
$autoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
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

## FileLogger — PSR-3 File Logger (v1.3.0)

### Purpose

`FileLogger` implements `Psr\Log\LoggerInterface` (via `Psr\Log\AbstractLogger`)
and writes structured log entries to FA's `company/<comp>/logs/<module>.log`
directory. It is the standard PSR-3 logger for all ksfraser FA modules.

When running inside FA it resolves the path from `$GLOBALS['path_to_root']`
and `$_SESSION['wa_current_user']->company`. Outside FA (e.g. unit tests) it
falls back to `sys_get_temp_dir()/ksf_logs/`.

### Requirements

| ID   | Requirement | Verified By |
|------|-------------|-------------|
| FLR-1 | Implements `Psr\Log\LoggerInterface` | `testImplementsPsr3LoggerInterface` |
| FLR-2 | Log message includes ISO-style timestamp, uppercase level, and message | `testLogContainsTimestampLevelAndMessage` |
| FLR-3 | Each `log()` call appends a new line — previous content preserved | `testLogAppendsMultipleEntries` |
| FLR-4 | All PSR-3 levels (`emergency` … `debug`) write the correct label | `testAllLevelsWriteCorrectly` |
| FLR-5 | `{key}` placeholders in the message are replaced from `$context` | `testPlaceholderInterpolation` |
| FLR-6 | Context values of type null/bool/scalar/array/toStringable are stringified | `testContextWithNullValue`, `testContextWithBoolValue`, `testContextWithArrayValue` |
| FLR-7 | Log directory is auto-created if missing | `testCreatesLogDirectoryIfMissing` |
| FLR-8 | Module name is sanitised to a safe filename (`[^a-zA-Z0-9_\-]` replaced with `_`) | `testSanitizedFilename` |
| FLR-9 | Outside FA context, falls back to `sys_get_temp_dir()/ksf_logs/` | `testDefaultLogDirWithoutFaContext` |

### Architecture

```
┌──────────────┐    extends     ┌──────────────────┐
│  Psr\Log\    │ ─────────────> │  Ksfraser\Traits\ │
│  AbstractLog │                 │  FileLogger       │
│  ger         │                 │                   │
└──────────────┘                 │ - logDir          │
                                 │ - filename        │
                                 │                   │
                                 │ + log($level,     │
                                 │   $message,       │
                                 │   $context)       │
                                 │ - interpolate()   │
                                 │ - stringify()     │
                                 │ - resolveDefault  │
                                 │   LogDir()        │
                                 └──────────────────┘
                                          │
                                          │ writes to
                                          ▼
                              ┌──────────────────────┐
                              │ company/<comp>/logs/  │
                              │ <module>.log          │
                              └──────────────────────┘
```

### UML — FileLogger

```text
┌─────────────────────────────────────────────┐
│ «class» FileLogger                          │
│ package Ksfraser\Traits                     │
├─────────────────────────────────────────────┤
│ - logDir : string                           │
│ - filename : string                         │
├─────────────────────────────────────────────┤
│ + __construct(moduleName, logDir?)          │
│ + log($level, $message, $context) : void    │
│ - interpolate(message, context) : string    │
│ - stringify(value) : string                 │
│ - resolveDefaultLogDir() : string           │
└─────────────────────────────────────────────┘
```

### Usage

```php
use Ksfraser\Traits\FileLogger;

$logger = new FileLogger('cal_mail');
$logger->info('Sending invite', ['to' => $email, 'entry' => $entryId]);
```

---

## LoggerAwareTrait — PSR-3 Logger Injection (v1.3.0)

### Purpose

Provides PSR-3 `setLogger()`/`getLogger()` to any class, plus a convenience
`log()` method that falls back to auto-creating a `FileLogger` when no logger
was injected.  Useful for service classes that need occasional logging without
mandating DI setup.

### Requirements

| ID   | Requirement | Verified By |
|------|-------------|-------------|
| LAT-1 | `setLogger(LoggerInterface)` stores the logger | `testSetLoggerInjectsLogger` |
| LAT-2 | `getLogger()` returns the injected logger or auto-creates `FileLogger` | `testGetLoggerCreatesDefaultFileLogger` |
| LAT-3 | `log($level, $message, $context)` delegates to `getLogger()->log()` | `testLogConvenienceMethodWritesToFile` |
| LAT-4 | Context placeholders are interpolated through the logger | `testLogWithContextInterpolation` |

### Architecture

```
┌───────────────────────┐
│  «trait»              │
│  LoggerAwareTrait     │
├───────────────────────┤
│ - logger : LoggerInt  │
│   erface|null         │
├───────────────────────┤
│ + setLogger(Logger)   │
│ + getLogger() : Log   │
│   gerInterface        │
│ # log($level, $msg,  │
│     $context)         │
│ # getModuleName() :   │
│   string  [abstract]  │
└───────────────────────┘
          │
          │ uses
          ▼
┌───────────────────────┐
│  FileLogger           │
│  (auto-created when   │
│   no DI logger given) │
└───────────────────────┘
```

### Usage

```php
class ReminderService {
    use \Ksfraser\Traits\LoggerAwareTrait;

    protected function getModuleName(): string { return 'calendar_reminders'; }

    public function send(int $reminderId): void
    {
        $this->log('info', 'Sending reminder {id}', ['id' => $reminderId]);
    }
}
```

### RTM — Requirements Traceability Matrix

| Req ID | Artifact | Test(s) | Status |
|--------|----------|---------|--------|
| FLR-1–9 | `FileLogger` | `FileLoggerTest` (9 tests) | ✅ |
| LAT-1–4 | `LoggerAwareTrait` | `LoggerAwareTraitTest` (4 tests) | ✅ |

---

## Coding Standards

### PHP Compatibility
- **Minimum**: PHP 7.3
- Always use `declare(strict_types=1);`

### DocBlock Standards
```php
/**
 * Entity State Trait
 *
 * Provides state management for entity objects.
 * Replaces parent class state tracking in legacy hierarchies.
 *
 * @package Ksfraser\Traits
 * @since 1.0.0
 */
```

### Trait Requirements
- Self-contained (no external dependencies)
- Document side effects
- Note property conflicts with composed traits

---

## Testing Requirements

### Test Structure
```php
namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;

class EntityStateTraitTest extends TestCase
{
    public function testIsModifiedInitiallyFalse(): void
    {
        $obj = new class { use EntityStateTrait; };
        $this->assertFalse($obj->isModified());
    }
    
    public function testMarkModifiedSetsTrue(): void
    {
        $obj = new class { 
            use EntityStateTrait;
            public function modify(string $key, $value): void
            {
                $this->setStateValue($key, $value);
            }
        };
        $obj->modify('key', 'value');
        $this->assertTrue($obj->isModified());
    }
}
```

### Coverage Target
- **100% coverage** for all trait methods
- Test trait composition scenarios
- Test magic method interactions

### CrudEventEmitterTrait Test Patterns

```php
class CrudEventEmitterTraitTest extends TestCase
{
    public function testEmitCreatedDispatchesSpecificHook(): void
    {
        $emitter = new class { use CrudEventEmitterTrait; };
        
        // Cannot test hook_invoke_all directly — verify
        // that emitCreated calls emitCrudEvent with correct args
        // by inspecting the concrete hook listener
        $emitter->emitCreated('crm', 'customer', 42, ['name' => 'Acme']);
        $this->expectNotToPerformAssertions();
    }
}
```

---

## Version Management

### Version History
| Version | Changes |
|---------|---------|
| 1.3.0 | Added `FileLogger` (PSR-3 file logger) and `LoggerAwareTrait` (logger injection). Added `psr/log` runtime dependency. |
| 1.2.0 | Added `HookQueryProviderTrait` for FA inter-module query hooks. |
| 0.1.0 | Initial release with `CrudEventEmitterTrait`, `EntityStateTrait`, `EventEmitterTrait`, `TimestampTrait`, `ValidatableTrait`, `EnforceDeclaredPropsTrait`. |

### Semantic Versioning
- **MAJOR**: Breaking changes to trait methods or properties
- **MINOR**: New traits (backward compatible)
- **PATCH**: Bug fixes, documentation

---

## .gitignore

```
/vendor/
/composer.lock
.phpunit.cache/
.idea/
.vscode/
```

---

## Adding New Traits / Classes

1. Ensure trait/class follows single responsibility
2. Document all public methods
3. Note any required collaborators (including Composer dependencies)
4. Write comprehensive tests
5. Update AGENTS.md with Requirements, RTM, Architecture, and UML
6. Update `composer.json` if new runtime dependencies are needed

---

## Local Overrides

Create `AGENTS.local.md` for project-specific overrides:

```markdown
# AGENTS.local.md
# Library-specific overrides

[Your overrides here]
```

**Note**: Core trait design principles cannot be overridden.

## Development Workflow

All development is done in the **devel tree** (`~/Documents/Traits`).

### Workflow Steps
1. **Develop** in this repo (feature branches preferred)
2. **Test**: run repo-appropriate tests
3. **Lint**: `php -l` on modified PHP files (no syntax errors)
4. **Commit** and **Push** branch to GitHub
5. **Merge** to `master` when ready
6. **Push** `master` to GitHub

*No UAT bind point in `~/ksf_Infrastructure/fa_modules/Traits` — this repo is consumed via Composer path repos or other means.*

