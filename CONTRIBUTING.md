# Contributing to Philips Hue Client

Thank you for your interest in contributing to the Philips Hue Client for PHP! This document outlines the process for contributing to this project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Install dependencies: `composer install`
4. Create a new branch for your feature/bugfix

## Development Setup

1. Install PHP 8.0 or higher
2. Install Composer
3. Run `composer install` to install dependencies
4. Run tests with `composer test`

## Code Standards

- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add PHPDoc comments to all public methods
- Keep methods focused and small

## Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Check code style
composer cs
```

## Submitting Changes

1. Create a descriptive branch name (e.g., `feature/add-sensor-support` or `fix/memory-leak`)
2. Make your changes with appropriate tests
3. Ensure all tests pass
4. Submit a pull request with a clear description

## Pull Request Process

1. Update the README.md with details of changes if applicable
2. Add tests for any new functionality
3. Ensure the test suite passes
4. Update documentation as needed
5. The pull request will be reviewed by maintainers

## Bug Reports

When filing a bug report, please include:

- PHP version
- Package version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Any relevant error messages

## Feature Requests

Feature requests are welcome! Please provide:

- A clear description of the feature
- Use cases and benefits
- Possible implementation details

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code:

- Be respectful and inclusive
- Focus on constructive feedback
- Help create a welcoming environment for all contributors

Thank you for contributing!