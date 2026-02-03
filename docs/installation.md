# Installation Guide

## Overview

OLS cPanel is a modern OpenLiteSpeed integration for cPanel/WHM that replaces Apache with high-performance OpenLiteSpeed web server while maintaining full compatibility with cPanel features.

## System Requirements

### Minimum Requirements
- **cPanel/WHM**: Version 118 or higher (latest stable)
- **Operating System**: 
  - AlmaLinux 9.x (Recommended)
  - Rocky Linux 9.x (Recommended)
  - CloudLinux 9.x
  - Ubuntu 22.04 LTS
  - Ubuntu 24.04 LTS
  - Debian 12.x
- **PHP**: Version 8.1 or higher (8.2+ recommended)
- **Memory**: 4GB RAM (8GB recommended)
- **Storage**: 20GB free disk space (SSD recommended)
- **Root Access**: Required for installation

### Recommended Requirements
- **Memory**: 8GB+ RAM (16GB for production)
- **CPU**: 4+ cores (8+ for production)
- **Storage**: 50GB+ free disk space (NVMe SSD recommended)
- **Network**: Stable internet connection for downloads
- **Architecture**: x86_64 (ARM64 support coming soon)

## Pre-Installation Checklist

### 1. System Preparation
```bash
# For RHEL-based systems (AlmaLinux/Rocky/CloudLinux)
sudo dnf update -y

# For Debian-based systems (Ubuntu/Debian)
sudo apt update && sudo apt upgrade -y

# Check cPanel version
whmapi1 version

# Verify PHP installation
php --version

# Check available disk space
df -h

# Check memory
free -h

# Check system architecture
uname -m
```

### 2. Backup Current System
```bash
# Create system backup
/scripts/pkgacct user1

# Backup Apache configuration
cp -r /etc/httpd/conf /root/apache_conf_backup

# Backup SSL certificates
cp -r /etc/ssl /root/ssl_backup
```

### 3. Remove Conflicting Software
```bash
# Stop and disable Apache (if exists)
sudo systemctl stop httpd apache2 2>/dev/null || true
sudo systemctl disable httpd apache2 2>/dev/null || true

# Remove Enterprise LiteSpeed if installed
sudo /usr/local/lsws/admin/misc/uninstall.sh 2>/dev/null || true

# Remove cPanel LiteSpeed plugin if exists
sudo /usr/local/cpanel/whostmgr/docroot/cgi/lsws_whm_plugin_uninstall.sh 2>/dev/null || true

# Remove old OLS versions
sudo systemctl stop lshttpd 2>/dev/null || true
sudo systemctl disable lshttpd 2>/dev/null || true
sudo rm -rf /usr/local/lsws 2>/dev/null || true
```

## Installation Methods

### Method 1: Quick Install (Recommended)

```bash
# Download and run the installer
curl -fsSL https://get.ols-cpanel.com | bash

# Monitor installation progress
tail -f /var/log/ols-cpanel/install.log
```

### Method 2: Manual Install

#### Step 1: Download the Package
```bash
# Clone the repository
git clone https://github.com/afgupload/OLS-cPanel-WHM.git
cd OLS-cPanel-WHM

# Or download the latest release
wget https://github.com/afgupload/OLS-cPanel-WHM/releases/latest/download/ols-cpanel.tar.gz
tar -xzf ols-cpanel.tar.gz
cd OLS-cPanel-WHM
```

#### Step 2: Install Dependencies
```bash
# Install system dependencies
yum install -y curl wget unzip tar systemd which git

# Install Composer (PHP package manager)
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js (for frontend assets)
curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
yum install -y nodejs
```

#### Step 3: Install PHP Dependencies
```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
cd whm-plugin/assets
npm install
npm run build
cd ../../
```

#### Step 4: Run the Installer
```bash
# Make installer executable
chmod +x installer/install.sh

# Run the installer
sudo ./installer/install.sh
```

## Installation Process

The installer performs the following steps:

### 1. System Validation
- Checks system requirements
- Validates cPanel/WHM installation
- Verifies sufficient resources
- Checks for conflicting software

### 2. Backup Creation
- Creates full system backup
- Backs up Apache configuration
- Preserves SSL certificates
- Saves current domain settings

### 3. OpenLiteSpeed Installation
- Downloads latest OpenLiteSpeed version
- Installs web server binaries
- Configures basic settings
- Sets up system services

### 4. WHM Plugin Installation
- Installs WHM interface plugin
- Configures API endpoints
- Sets up web assets
- Registers with cPanel

### 5. Configuration Migration
- Migrates Apache virtual hosts
- Converts SSL certificates
- Maps PHP versions
- Updates domain settings

### 6. Service Configuration
- Configures OpenLiteSpeed service
- Sets up monitoring service
- Enables automatic startup
- Tests configuration

## Post-Installation

### 1. Verify Installation
```bash
# Check OpenLiteSpeed status
systemctl status lshttpd

# Check WHM plugin
whmapi1 listmodules

# Test web server
curl -I http://localhost

# Check admin interface
curl -I http://localhost:7080
```

### 2. Access WHM Interface
1. Log in to WHM
2. Navigate to **Plugins** → **OLS Manager**
3. Verify dashboard shows server status
4. Check domain listings

### 3. Configure Basic Settings
1. Go to **Configuration** → **Server Settings**
2. Set admin email
3. Configure performance settings
4. Enable SSL auto-renewal

### 4. Test Domain Functionality
```bash
# Test a domain
curl -I https://example.com

# Check SSL certificate
openssl s_client -connect example.com:443

# Verify PHP functionality
echo "<?php phpinfo(); ?>" > /home/user/public_html/test.php
curl http://example.com/test.php
rm /home/user/public_html/test.php
```

## Configuration Files

### Main Configuration Files
- `/etc/ols-cpanel/config.yaml` - Main configuration
- `/usr/local/lsws/conf/httpd_config.conf` - OpenLiteSpeed config
- `/var/log/ols-cpanel/` - Log files
- `/var/backups/ols-cpanel/` - Backup files

### WHM Plugin Files
- `/usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/` - Plugin directory
- `/usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/assets/` - Web assets

## Troubleshooting

### Common Issues

#### Installation Fails
```bash
# Check installer log
tail -f /var/log/ols-cpanel/install.log

# Check system logs
journalctl -xe

# Verify permissions
ls -la /usr/local/lsws/
```

#### Service Won't Start
```bash
# Check service status
systemctl status lshttpd

# Check configuration
/usr/local/lsws/bin/lswsctrl -t

# View error logs
tail -f /usr/local/lsws/logs/error.log
```

#### WHM Plugin Not Working
```bash
# Check plugin files
ls -la /usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/

# Check permissions
chown -R root:root /usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/
chmod 755 /usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/*.cgi

# Restart cPanel service
systemctl restart cpanel
```

#### SSL Issues
```bash
# Check SSL certificates
openssl x509 -in /usr/local/lsws/conf/ssl/domain.crt -text -noout

# Verify certificate chain
openssl verify -CAfile /usr/local/lsws/conf/ssl/domain.ca /usr/local/lsws/conf/ssl/domain.crt
```

### Getting Help

1. **Check Logs**: `/var/log/ols-cpanel/install.log`
2. **Documentation**: See `/docs` directory in the project
3. **GitHub Issues**: https://github.com/afgupload/OLS-cPanel-WHM/issues
4. **Community**: https://discord.gg/ols-cpanel
5. **Email Support**: support@ols-cpanel.com

## Uninstallation

If you need to remove OLS cPanel:

```bash
# Run uninstaller
/usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/uninstall.sh

# Manually remove files (if needed)
rm -rf /usr/local/lsws
rm -rf /etc/ols-cpanel
rm -rf /var/log/ols-cpanel
rm -rf /usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel

# Restore Apache (if backup available)
systemctl enable httpd
systemctl start httpd
```

## Migration from Legacy Version

If upgrading from the old version:

1. **Backup Current Installation**
   ```bash
   cp -r /usr/local/lsws /root/lsws_legacy_backup
   ```

2. **Remove Old Version**
   ```bash
   rm -rf /usr/local/cpanel/whostmgr/docroot/cgi/addon_lsws.cgi
   ```

3. **Install New Version**
   ```bash
   ./installer/install.sh
   ```

4. **Import Old Configuration**
   ```bash
   /usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel/api/import_config.php
   ```

## Performance Optimization

After installation, consider these optimizations:

### 1. Cache Configuration
```yaml
# In /etc/ols-cpanel/config.yaml
performance:
  cache_enabled: true
  cache_size_mb: 2048
  gzip_compression: true
```

### 2. PHP Optimization
```yaml
php:
  opcache_enabled: true
  opcache_memory_consumption: "256M"
  memory_limit: "512M"
```

### 3. SSL Optimization
```yaml
ssl:
  enable_ocsp_stapling: true
  enable_hsts: true
  default_protocol: "TLSv1.3"
```

## Security Considerations

1. **Firewall Configuration**
   ```bash
   firewall-cmd --permanent --add-service=http
   firewall-cmd --permanent --add-service=https
   firewall-cmd --reload
   ```

2. **File Permissions**
   ```bash
   chmod 640 /etc/ols-cpanel/config.yaml
   chown root:lsadm /etc/ols-cpanel/config.yaml
   ```

3. **SSL Configuration**
   - Enable HSTS
   - Use strong cipher suites
   - Enable OCSP stapling

## Next Steps

After successful installation:

1. **Explore WHM Interface**
2. **Configure Monitoring**
3. **Set Up Backups**
4. **Optimize Performance**
5. **Enable SSL Auto-renewal**
6. **Configure Alerts**

For detailed configuration options, see the [Configuration Guide](configuration.md).

*Note: This documentation is part of the project and will be expanded as the project develops.*
