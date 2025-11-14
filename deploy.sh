#!/bin/bash

# Laravel Deployment Script
# Usage: ./deploy.sh [branch_name]
# Default branch: main

set -e  # Exit on any error

# Configuration
PROJECT_ROOT="/var/www/html/fairtrade-loans-backend"
BRANCH="${1:-main}"
USER="deploy"
ENV_FILE="$PROJECT_ROOT/.env"

echo "======================================"
echo "Laravel Deployment Script"
echo "======================================"
echo "Project: $PROJECT_ROOT"
echo "Branch: $BRANCH"
echo "User: $USER"
echo "Date: $(date)"
echo "======================================"

# Function to log with timestamp
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check supervisor status
check_supervisor() {
    if command_exists supervisorctl; then
        log "Checking supervisor status..."
        sudo supervisorctl status | grep laravel-worker || log "Laravel worker not found in supervisor"
    else
        log "Supervisor not installed"
    fi
}

# Function to fix permissions
fix_permissions() {
    log "Setting proper file permissions..."
    
    # Set ownership to www-data for secure file operations
    sudo chown -R www-data:www-data $PROJECT_ROOT
    
    # Set secure directory permissions
    sudo find $PROJECT_ROOT -type d -exec chmod 755 {} \;
    sudo find $PROJECT_ROOT -type f -exec chmod 644 {} \;
    
    # Make storage and bootstrap/cache writable
    sudo chmod -R 775 $PROJECT_ROOT/storage/
    sudo chmod -R 775 $PROJECT_ROOT/bootstrap/cache/
    
    # Ensure specific directories have proper permissions
    sudo chmod -R 775 $PROJECT_ROOT/storage/framework/views/
    sudo chmod -R 775 $PROJECT_ROOT/storage/framework/cache/
    sudo chmod -R 775 $PROJECT_ROOT/storage/framework/sessions/
    sudo chmod -R 775 $PROJECT_ROOT/storage/logs/
    
    # Create necessary directories if they don't exist
    sudo -u www-data mkdir -p $PROJECT_ROOT/storage/framework/views
    sudo -u www-data mkdir -p $PROJECT_ROOT/storage/framework/cache
    sudo -u www-data mkdir -p $PROJECT_ROOT/storage/framework/sessions
    
    log "Permissions fixed successfully"
}

# Change to project directory
log "Changing to project directory..."
cd $PROJECT_ROOT

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    log "ERROR: artisan file not found. Are you in the correct Laravel project directory?"
    exit 1
fi

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    log "ERROR: .env file not found!"
    exit 1
fi

# Put application in maintenance mode
log "Putting application in maintenance mode..."
sudo -u www-data php artisan down --render="errors::503" --retry=60

# Function to bring application back up on error
cleanup() {
    log "ERROR occurred. Bringing application back up..."
    sudo -u www-data php artisan up
    exit 1
}

# Set trap to run cleanup on error
trap cleanup ERR

# Git operations
log "Fetching latest changes from git..."
sudo -u www-data git fetch origin

log "Checking out branch: $BRANCH"
sudo -u www-data git checkout $BRANCH

log "Pulling latest changes..."
sudo -u www-data git pull origin $BRANCH

# Show current commit
log "Current commit: $(sudo -u www-data git rev-parse --short HEAD) - $(sudo -u www-data git log -1 --pretty=%s)"

# Install/Update PHP dependencies
log "Installing/updating PHP dependencies..."
sudo -u www-data composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Fix permissions after composer install
fix_permissions

# Clear application caches
log "Clearing application caches..."
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

# Cache configuration for production
log "Caching configuration for better performance..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Run database migrations (if any)
log "Running database migrations..."
sudo -u www-data php artisan migrate --force

# Optional: Run database seeder (uncomment if needed)
# log "Running database seeder..."
# sudo -u www-data php artisan db:seed --force

# Clear and cache events & routes again after migrations
log "Refreshing cached files..."
sudo -u www-data php artisan event:cache

# Fix permissions again after all operations
fix_permissions

# Restart queue workers via supervisor
log "Restarting queue workers..."
if command_exists supervisorctl; then
    # Stop workers gracefully
    sudo supervisorctl stop laravel-worker:*
    
    # Wait a moment
    sleep 2
    
    # Start workers
    sudo supervisorctl start laravel-worker:*
    
    # Check status
    sudo supervisorctl status laravel-worker:*
else
    # Fallback: restart queues via artisan
    log "Supervisor not available, using artisan queue:restart..."
    sudo -u www-data php artisan queue:restart
fi

# Restart PHP-FPM and Nginx
log "Restarting PHP-FPM and Nginx..."
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

# Optional: Clear opcache if enabled
log "Clearing OPcache..."
sudo -u www-data php artisan optimize:clear

# Bring application back up
log "Bringing application back online..."
sudo -u www-data php artisan up

# Final status check
log "Deployment completed successfully!"
log "Checking supervisor status..."
check_supervisor

log "Checking application status..."
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://fairtradeapi.jawbonetechnology.co.ke || log "Could not check application status"

# Show application info
log "Application information:"
sudo -u www-data php artisan about

echo "======================================"
echo "Deployment Summary"
echo "======================================"
echo "✓ Git pull completed"
echo "✓ Dependencies updated" 
echo "✓ Permissions fixed"
echo "✓ Database migrations run"
echo "✓ Caches cleared and rebuilt"
echo "✓ Queue workers restarted"
echo "✓ Services restarted"
echo "✓ Application online"
echo "======================================"
echo "Deployment completed at: $(date)"
echo "======================================"