# Quality Assurance

Gallery Quest includes comprehensive QA tools to prevent bugs and ensure code quality.

## Quick Commands

```bash
# Run all checks
npm run check

# Run all checks including PHP analysis
npm run check:all

# Auto-fix linting issues
npm run lint:js:fix
npm run lint:css
npm run format:fix
```

## What's Included

### JavaScript Linting (ESLint)
- WordPress coding standards
- Performance rules
- Bug prevention rules
- Auto-fixable issues

### CSS Linting (Stylelint)
- WordPress CSS standards
- Best practices
- Consistency checks

### PHP Linting (PHPCS)
- WordPress coding standards
- Security checks
- Best practices

### PHP Static Analysis (PHPStan)
- Type checking
- Error detection
- Level 5 analysis

### JavaScript Testing (Jest)
- Unit tests
- Integration tests
- Performance tests
- Coverage reporting

### PHP Testing (PHPUnit)
- Unit tests
- Integration tests
- Coverage reporting

### Code Formatting (Prettier)
- Consistent code style
- Auto-formatting
- Multi-language support

## Pre-commit Hooks

Husky automatically runs:
- JavaScript linting
- CSS linting
- Tests (on push)

## CI/CD

GitHub Actions runs:
- All linting checks
- All tests
- Production build
- Coverage reports

## Coverage Goals

- JavaScript: 60% minimum
- PHP: 60% minimum

## Performance Testing

Tests ensure:
- No memory leaks
- Fast initialization
- Efficient event handling
- Proper debouncing

## Common Issues Fixed

### Browser Lockups
- ✅ Prevents infinite loops
- ✅ Debounces observers
- ✅ Tracks initialization
- ✅ Uses event delegation

### Memory Leaks
- ✅ WeakSet for tracking
- ✅ Proper cleanup
- ✅ Single event listeners

### Performance
- ✅ Debounced API calls
- ✅ Efficient DOM queries
- ✅ Minimal observers

## Running Tests

```bash
# Watch mode (development)
npm test

# CI mode
npm run test:ci

# With coverage
npm run test:coverage
```

## Fixing Issues

Most issues can be auto-fixed:
```bash
npm run lint:js:fix
npm run format:fix
```

For PHP:
```bash
composer phpcbf
```


