# Contributing to OLS cPanel

Thank you for your interest in contributing to OLS cPanel! This document provides guidelines and information for contributors.

## Getting Started

### Prerequisites
- PHP 7.4+ with required extensions
- Node.js 18+ and npm
- Git
- Basic knowledge of cPanel/WHM
- Familiarity with OpenLiteSpeed

### Development Setup

1. **Fork the Repository**
   ```bash
   git clone https://github.com/your-username/OLS-cPanel-WHM.git
   cd OLS-cPanel-WHM
   ```

2. **Install Dependencies**
   ```bash
   composer install
   cd whm-plugin/assets
   npm install
   cd ../../
   ```

3. **Set Up Development Environment**
   ```bash
   # Copy configuration template
   cp config/ols-cpanel.yaml.example config/ols-cpanel.yaml
   
   # Set up test environment
   php tests/setup.php
   ```

## Code Standards

### PHP Standards
- Follow PSR-12 coding standards
- Use strict types declarations
- Include proper PHPDoc comments
- Follow SOLID principles

### JavaScript/Vue Standards
- Use ES6+ features
- Follow Vue.js style guide
- Include proper JSDoc comments
- Use TypeScript for new components

### File Organization
```
src/
├── Config/          # Configuration management
├── Controllers/     # WHM API controllers
├── Models/         # Data models
├── Services/       # Business logic
└── Utils/          # Utility classes

whm-plugin/assets/src/
├── components/     # Vue components
├── views/         # Page views
├── router/        # Vue router
├── stores/        # Pinia stores
└── utils/         # Frontend utilities
```

## Development Workflow

### 1. Create Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### 2. Make Changes
- Write clean, documented code
- Follow coding standards
- Add tests for new functionality
- Update documentation

### 3. Test Your Changes
```bash
# Run PHP tests
composer test

# Run frontend tests
cd whm-plugin/assets
npm run test

# Check code style
composer cs-check
npm run lint
```

### 4. Commit Changes
```bash
git add .
git commit -m "feat: add new feature description"
```

### 5. Push and Create Pull Request
```bash
git push origin feature/your-feature-name
```

## Pull Request Guidelines

### PR Requirements
- Clear description of changes
- Link to related issues
- Tests pass
- Code follows standards
- Documentation updated

### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] Tests added/updated
```

## Testing

### Unit Tests
```bash
# Run all tests
composer test

# Run specific test
composer test -- tests/ConfigManagerTest.php

# Generate coverage report
composer test -- --coverage-html coverage/
```

### Integration Tests
```bash
# Setup test environment
php tests/setup-integration.php

# Run integration tests
composer test-integration
```

### Frontend Tests
```bash
cd whm-plugin/assets

# Run unit tests
npm run test:unit

# Run component tests
npm run test:component

# Run E2E tests
npm run test:e2e
```

## Documentation

### Code Documentation
- PHPDoc for all classes and methods
- JSDoc for JavaScript functions
- Inline comments for complex logic

### User Documentation
- Update README.md for user-facing changes
- Update docs/ for new features
- Include examples and screenshots

## Issue Reporting

### Bug Reports
Include:
- Environment details
- Steps to reproduce
- Expected vs actual behavior
- Error messages/logs
- Screenshots if applicable

### Feature Requests
Include:
- Use case description
- Proposed solution
- Alternative approaches
- Implementation considerations

## Release Process

### Version Management
- Follow semantic versioning
- Update version numbers in all files
- Create release notes
- Tag releases properly

### Release Checklist
- [ ] All tests pass
- [ ] Documentation updated
- [ ] Changelog updated
- [ ] Version numbers updated
- [ ] Release notes prepared

## Community Guidelines

### Code of Conduct
- Be respectful and inclusive
- Welcome newcomers
- Provide constructive feedback
- Focus on what is best for the community

### Communication
- Use GitHub issues for bugs/features
- Use discussions for questions
- Join Discord for real-time chat
- Follow project announcements

## Recognition

### Contributors
All contributors are recognized in:
- README.md contributors section
- Release notes
- Project website

### Types of Contributions
- Code contributions
- Bug reports
- Documentation
- Community support
- Translation

## Getting Help

### Resources
- [Documentation](../docs/)
- [API Reference](../docs/api.md)
- [Discord Community](https://discord.gg/ols-cpanel)
- [GitHub Issues](https://github.com/afgupload/OLS-cPanel-WHM/issues)

### Contact
- Email: dev@ols-cpanel.com
- Discord: #development channel
- GitHub: @mention maintainers

Thank you for contributing to OLS cPanel!
