# Testing Guide

Gallery Quest includes comprehensive testing and quality assurance tools.

## Quick Start

```bash
# Run all checks
npm run check

# Run all checks including PHP analysis
npm run check:all
```

## JavaScript Testing

### Unit Tests

```bash
# Run tests in watch mode (development)
npm run test

# Run tests once (CI)
npm run test:ci

# Run with coverage
npm run test:coverage
```

Tests are located in `tests/js/` and cover:
- Attachment fields functionality
- Tag management
- Autocomplete behavior
- Performance characteristics
- Edge cases

### Linting JavaScript

```bash
# Check for issues
npm run lint:js

# Auto-fix issues
npm run lint:js:fix
```

## PHP Testing

### Unit Tests

```bash
# Run PHPUnit tests
composer test

# Run with coverage
composer test:coverage
```

### Linting PHP

```bash
# Check code style
composer phpcs

# Auto-fix issues
composer phpcbf
```

### Static Analysis

```bash
# Run PHPStan
composer phpstan
```

## CSS Linting

```bash
# Lint CSS/SCSS files
npm run lint:css
```

## Code Formatting

```bash
# Check formatting
npm run format:check

# Auto-format code
npm run format:fix
```

## Pre-commit Hooks

Husky automatically runs checks before commits:
- JavaScript linting
- CSS linting
- PHP linting
- Tests

To skip hooks (not recommended):
```bash
git commit --no-verify
```

## CI/CD

GitHub Actions automatically runs:
- All linting checks
- All tests
- Production build
- Coverage reports

## Coverage Goals

- **JavaScript**: 60% minimum
- **PHP**: 60% minimum

View coverage reports:
- JavaScript: `coverage/lcov-report/index.html`
- PHP: `coverage/php/index.html`

## Writing Tests

### JavaScript Test Example

```javascript
describe('Feature Name', () => {
	test('should do something', () => {
		expect(result).toBe(expected);
	});
});
```

### PHP Test Example

```php
class TestFeature extends TestCase {
	public function test_something() {
		$this->assertEquals($expected, $result);
	}
}
```

## Performance Testing

Performance tests ensure:
- No memory leaks
- Acceptable initialization times
- Efficient event handling
- Proper debouncing

Run performance tests:
```bash
npm run test -- tests/performance
```

## Troubleshooting

### Tests failing locally but passing in CI
- Clear `node_modules` and reinstall
- Clear Jest cache: `jest --clearCache`
- Check Node.js version matches CI

### PHP tests not running
- Ensure WordPress test environment is set up
- Check `WP_TESTS_DIR` environment variable
- Verify Composer dependencies are installed


