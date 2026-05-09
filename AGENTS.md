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
├── EnforceDeclaredPropsTrait  # Restrict property access (replaces magic __get/__set)
├── EntityStateTrait           # Track entity modification state
├── TimestampTrait           # Created/updated timestamps
├── ValidatableTrait         # Validation capability (replaces type validators)
├── EventEmitterTrait        # Event handling (replaces NOTIFY_* calls)
└── LoggerAwareTrait         # PSR-3 logger injection
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

---

## Version Management

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

## Adding New Traits

1. Ensure trait follows single responsibility
2. Document all public methods
3. Note any required collaborators
4. Write comprehensive tests
5. Update README with usage examples

---

## Local Overrides

Create `AGENTS.local.md` for project-specific overrides:

```markdown
# AGENTS.local.md
# Library-specific overrides

[Your overrides here]
```

**Note**: Core trait design principles cannot be overridden.