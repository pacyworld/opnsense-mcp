# Contributing to opnsense-mcp

Thank you for your interest in contributing!

## Development Setup

1. Clone the repository
2. Install dev dependencies: `composer install`
3. Copy and configure: `cp config/instances.json.sample config/instances.json`
4. Run tests: `vendor/bin/phpunit`

## Requirements

- PHP 8.1 or later
- ext-curl
- ext-json

## Testing

All pull requests must pass the existing test suite. New features should include tests.

```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-text

# Syntax check
find classes tools bin -name "*.php" | xargs -n1 php -l
```

## Code Style

- Follow existing code conventions in the project
- Use PHP 8.1+ features (named arguments, enums, match expressions, readonly properties)
- PHPDoc blocks on all public methods

## Pull Requests

1. Fork the repository
2. Create a feature branch from `main`
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request with a clear description

## License

By contributing, you agree that your contributions will be licensed under the BSD 2-Clause License.
