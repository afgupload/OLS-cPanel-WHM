# OLS cPanel - Modern OpenLiteSpeed Integration

![OLS cPanel Logo](assets/logo.svg)

## Overview

OLS cPanel is a **modern, comprehensive solution** for integrating OpenLiteSpeed web server with cPanel/WHM. This project replaces the outdated legacy implementation with a robust, secure, and feature-rich system.

## 🚀 Features

### Core Features
- **Modern Architecture**: Built with PHP 8.1+, Vue.js 3, and best practices
- **Automatic Installation**: One-click installation with rollback capability
- **Real-time Configuration**: Live configuration updates without service restarts
- **SSL Management**: Automated SSL certificate handling and renewal
- **PHP Version Management**: Support for multiple PHP versions per domain
- **Performance Monitoring**: Built-in monitoring and analytics dashboard
- **Security Hardening**: Enhanced security features and configurations

### WHM Integration
- **Modern UI**: Beautiful, responsive interface built with Vue.js 3 + Element Plus
- **Real-time Dashboard**: Live server statistics and performance metrics
- **Domain Management**: Easy domain and subdomain configuration
- **VHost Templates**: Customizable virtual host templates
- **Backup & Restore**: Complete configuration backup and restore system

### Technical Features
- **PSR-12 Compliant**: Following modern PHP standards
- **Composer Managed**: Proper dependency management
- **Unit Testing**: Comprehensive test coverage
- **Logging System**: Advanced logging with Monolog
- **API Integration**: RESTful API for third-party integrations
- **Docker Support**: Containerized deployment options

## 📋 Requirements

- **cPanel/WHM**: Version 118 or higher (latest stable)
- **Operating System**: 
  - AlmaLinux 9.x (Recommended)
  - Rocky Linux 9.x (Recommended) 
  - CloudLinux 9.x
  - Ubuntu 22.04 LTS
  - Ubuntu 24.04 LTS
  - Debian 12.x
- **PHP**: Version 8.1 or higher (8.3+ recommended)
- **Memory**: 4GB RAM (8GB recommended)
- **Storage**: 20GB free disk space (SSD recommended)

## 🛠️ Installation

### Quick Install
```bash
# Download and run the installer
curl -fsSL https://get.ols-cpanel.com | bash
```

### Manual Install
```bash
# Clone the repository
git clone https://github.com/afgupload/OLS-cPanel-WHM.git
cd OLS-cPanel-WHM

# Install dependencies
composer install
npm install && npm run build

# Run the installer
sudo ./installer/install.sh
```

## 📖 Documentation

- [Installation Guide](docs/installation.md)
- [Configuration Guide](docs/configuration.md)
- [API Documentation](docs/api.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Developer Guide](docs/development.md)

*Note: Full documentation will be available after project setup and deployment.*

## 🏗️ Architecture

```
OLS-cPanel-WHM/
├── src/                     # Core PHP application
│   ├── Config/             # Configuration management
│   ├── Services/           # Business logic services
│   ├── Controllers/        # WHM API controllers
│   ├── Models/            # Data models
│   └── Utils/             # Utility classes
├── whm-plugin/             # WHM interface
│   ├── assets/            # Frontend assets (Vue.js)
│   ├── templates/         # WHM templates
│   └── api/              # API endpoints
├── installer/              # Installation scripts
├── config/                 # Configuration templates
├── tests/                  # Unit and integration tests
└── docs/                   # Documentation
```

## 🔧 Configuration

### Basic Configuration
The main configuration file is located at `/etc/ols-cpanel/config.yaml`:

```yaml
server:
  name: "OpenLiteSpeed"
  version: "1.8.2"
  
performance:
  max_connections: 10000
  cache_enabled: true
  gzip_compression: true

security:
  ssl_auto_renewal: true
  firewall_rules: true
  rate_limiting: true
```

## 📊 Monitoring

OLS cPanel provides comprehensive monitoring capabilities:

- **Real-time Metrics**: CPU, memory, and network usage
- **Performance Analytics**: Request times and throughput
- **Error Tracking**: Automatic error detection and alerting
- **SSL Monitoring**: Certificate expiration alerts
- **Custom Dashboards**: Configurable monitoring dashboards

## 🛡️ Security

- **Regular Updates**: Automatic security updates
- **SSL/TLS**: Strong encryption and certificate management
- **Firewall Integration**: Built-in firewall rules
- **Access Control**: Granular permission management
- **Audit Logging**: Comprehensive audit trails

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: See the `/docs` directory for comprehensive guides
- **Issues**: [GitHub Issues](https://github.com/afgupload/OLS-cPanel-WHM/issues)
- **Community**: [Discord Server](https://discord.gg/ols-cpanel)
- **Email**: support@ols-cpanel.com

*Note: Support channels will be activated after project launch.*

## 🔄 Migration from Legacy

If you're upgrading from the old version, see our [Migration Guide](docs/migration.md) for step-by-step instructions.

---

**OLS cPanel** - Modern OpenLiteSpeed integration for cPanel/WHM
© 2026 OLS cPanel Team. All rights reserved.
