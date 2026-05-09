# AGENTS.md - ksfraser/traits Library

> **DO NOT MODIFY THIS FILE.** Create `AGENTS.local.md` for project-specific overrides.

## Core Purpose

This is a **shared library** providing reusable PHP traits for all ksfraser projects. Traits provide:
- Cross-cutting concerns (logging, validation, events)
- Entity state management
- Property access control
- Common behaviors extracted from multiple classes

---

## Namespace Structure

```
Ksfraser\Traits\
├── EnforceDeclaredPropsTrait  # Restrict property access
├── EntityStateTrait           # Track entity modification state
├── TimestampTrait           # Created/updated timestamps
├── ValidatableTrait         # Validation capability
├── EventEmitterTrait       # Event handling
└── LoggerAwareTrait         # PSR-3 logger injection
```

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