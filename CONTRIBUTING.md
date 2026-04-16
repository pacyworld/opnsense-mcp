# Contributing to opnsense-mcp

Thank you for your interest in contributing!

## Development Setup

1. Clone the repository
2. Copy and configure: `cp config/instances.json.sample config/instances.json`
3. Run tests: `phpunit`

No Composer required — the project uses the Enchilada Framework autoloader with vendored libraries.

## Requirements

- PHP 8.2 or later (PHPUnit 11 requires 8.2+)
- ext-curl
- ext-json
- PHPUnit 11 (system package)

## Testing

All pull requests must pass the existing test suite. New features should include tests.

```bash
# Run all tests
phpunit --colors=always

# Syntax check
find classes tools bin system libraries -name "*.php" | xargs -n1 php -l
```

## Code Style

- Follow existing code conventions in the project
- Use PHP 8.2+ features (named arguments, enums, match expressions, readonly properties)
- PHPDoc blocks on all public methods

## Pull Requests

1. Fork the repository
2. Create a feature branch from `master`
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request with a clear description

## License

By contributing, you agree that your contributions will be licensed under the BSD 2-Clause License.
