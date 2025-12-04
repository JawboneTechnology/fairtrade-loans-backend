#!/bin/bash

# Laravel Deployment Script
# Usage: ./deploy.sh [branch_name]
# Default branch: main

set -e  # Exit on any error

# Configuration
PROJECT_ROOT="/var/www/html/fairtrade-loans-backend"
BRANCH="${1:-main}"
USER="deploy"

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

# Function to check Reverb server status
check_reverb() {
    if command_exists supervisorctl; then
        log "Checking Reverb server status..."
        if sudo supervisorctl status | grep -q reverb; then
            sudo supervisorctl status | grep reverb
        else
            log "Reverb server not found in supervisor"
        fi
    else
        log "Checking if Reverb process is running..."
        if pgrep -f "php artisan reverb:start" > /dev/null; then
            log "Reverb server is running (detected via process)"
        else
            log "Reverb server is not running"
        fi
    fi
}

# Function to restart Reverb server
restart_reverb() {
    log "Restarting Reverb WebSocket server..."
    
    if command_exists supervisorctl; then
        # Check if Reverb is managed by supervisor
        if sudo supervisorctl status | grep -q reverb; then
            log "Stopping Reverb server via supervisor..."
            sudo supervisorctl stop reverb:*
            
            # Wait a moment
            sleep 2
            
            # Start Reverb server
            log "Starting Reverb server via supervisor..."
            sudo supervisorctl start reverb:*
            
            # Check status
            log "Checking Reverb server status..."
            sudo supervisorctl status reverb:*
        else
            log "Reverb not managed by supervisor, attempting to restart via systemd..."
            if systemctl is-active --quiet reverb.service 2>/dev/null; then
                sudo systemctl restart reverb.service
                log "Reverb server restarted via systemd"
            else
                log "Reverb not found in systemd, attempting manual restart..."
                # Kill existing Reverb processes
                pkill -f "php artisan reverb:start" || true
                sleep 1
                # Note: Reverb should be started via supervisor or systemd service
                log "WARNING: Reverb should be managed by supervisor or systemd for production"
            fi
        fi
    else
        log "Supervisor not available, checking systemd..."
        if systemctl is-active --quiet reverb.service 2>/dev/null; then
            sudo systemctl restart reverb.service
            log "Reverb server restarted via systemd"
        else
            log "Reverb service not found. Please configure Reverb to run via supervisor or systemd"
        fi
    fi
}

# Function to setup git safe directory
setup_git_safe_directory() {
    log "Setting up Git safe directory..."
    git config --global --add safe.directory $PROJECT_ROOT
}

# Function to fix permissions for web server
fix_web_permissions() {
    log "Setting proper file permissions for web server..."
    
    # Set ownership to www-data for secure file operations
    sudo chown -R www-data:www-data $PROJECT_ROOT
    
    # Set secure directory permissions
    sudo find $PROJECT_ROOT -type d -exec chmod 755 {} \;
    sudo find $PROJECT_ROOT -type f -exec chmod 644 {} \;
    
    # Make storage and bootstrap/cache writable
    sudo chmod -R 775 $PROJECT_ROOT/storage/
    sudo chmod -R 775 $PROJECT_ROOT/bootstrap/cache/
    
    # IMPORTANT: Keep vendor directory owned by deploy for Composer operations
    sudo chown -R $USER:$USER $PROJECT_ROOT/vendor/
    sudo chmod -R 755 $PROJECT_ROOT/vendor/
    
    log "Web permissions fixed successfully"
}

# Function to fix permissions for git operations
fix_git_permissions() {
    log "Setting permissions for Git operations..."
    
    # Temporarily set ownership to deploy user for git operations
    sudo chown -R $USER:$USER $PROJECT_ROOT
    
    # Ensure git can work with the files
    sudo chmod -R 755 $PROJECT_ROOT
    sudo chmod -R 775 $PROJECT_ROOT/.git
    
    log "Git permissions fixed successfully"
}

# Change to project directory
log "Changing to project directory..."
cd $PROJECT_ROOT

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    log "ERROR: artisan file not found. Are you in the correct Laravel project directory?"
    exit 1
fi

# Setup git safe directory first
setup_git_safe_directory

# Fix permissions for git operations
fix_git_permissions

# Put application in maintenance mode
log "Putting application in maintenance mode..."
php artisan down --render="errors::503" --retry=60

# Function to bring application back up on error
cleanup() {
    log "ERROR occurred. Bringing application back up..."
    php artisan up
    exit 1
}

# Set trap to run cleanup on error
trap cleanup ERR

# Git operations
log "Fetching latest changes from git..."
git fetch origin

log "Checking out branch: $BRANCH"
git checkout $BRANCH

log "Checking for local changes..."
if ! git diff-index --quiet HEAD --; then
    log "Stashing local changes..."
    git stash push -m "Auto-stashed by deployment script $(date +'%Y-%m-%d %H:%M:%S')"
    STASH_APPLIED=true
    log "Local changes stashed successfully"
else
    STASH_APPLIED=false
    log "No local changes to stash"
fi

log "Pulling latest changes..."
git pull origin $BRANCH

# Show current commit
log "Current commit: $(git rev-parse --short HEAD) - $(git log -1 --pretty=%s)"

# FIX: Run Composer FIRST while we still have deploy permissions
log "Setting vendor directory permissions for Composer..."
sudo chown -R $USER:$USER $PROJECT_ROOT/vendor/
sudo chown -R $USER:$USER $PROJECT_ROOT/composer.json
sudo chown -R $USER:$USER $PROJECT_ROOT/composer.lock
sudo chmod -R 755 $PROJECT_ROOT/vendor/

# Install/Update PHP dependencies - RUN THIS BEFORE WEB PERMISSIONS
log "Installing/updating PHP dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# NOW set web permissions (this will preserve vendor for deploy)
log "Setting web permissions after Composer..."
fix_web_permissions

# Clear application caches
log "Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for production
log "Caching configuration for better performance..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations (if any)
log "Running database migrations..."
php artisan migrate --force

# Optional: Run database seeder (uncomment if needed)
# log "Running database seeder..."
# php artisan db:seed --force

# Clear and cache events & routes again after migrations
log "Refreshing cached files..."
php artisan event:cache

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
    php artisan queue:restart
fi

# Restart Reverb WebSocket server
restart_reverb

# Restart PHP-FPM and Nginx
log "Restarting PHP-FPM and Nginx..."
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

# Optional: Clear opcache if enabled
log "Clearing OPcache..."
php artisan optimize:clear

# Bring application back up
log "Bringing application back online..."
php artisan up

# Final status check
log "Deployment completed successfully!"
log "Checking supervisor status..."
check_supervisor

log "Checking Reverb server status..."
check_reverb

log "Checking application status..."
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://fairtradeapi.jawbonetechnology.co.ke || log "Could not check application status"

# Show application info
log "Application information:"
php artisan about

echo "======================================"
echo "Deployment Summary"
echo "======================================"
echo "✓ Git pull completed"
echo "✓ Dependencies updated" 
echo "✓ Permissions fixed"
echo "✓ Database migrations run"
echo "✓ Caches cleared and rebuilt"
echo "✓ Queue workers restarted"
echo "✓ Reverb WebSocket server restarted"
echo "✓ Services restarted"
echo "✓ Application online"
echo "======================================"
echo "Deployment completed at: $(date)"
echo "======================================"

exit 0

# Reverb server restart command

# [program:reverb]
# process_name=%(program_name)s
# command=php /var/www/html/fairtrade-loans-backend/artisan reverb:start
# autostart=true
# autorestart=true
# user=www-data
# redirect_stderr=true
# stdout_logfile=/var/www/html/fairtrade-loans-backend/storage/logs/reverb.log