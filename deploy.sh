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

# Apply stashed changes back if there were any
# if [ "$STASH_APPLIED" = true ]; then
#     log "Applying stashed changes..."
#     if git stash pop; then
#         log "Stashed changes applied successfully"
#     else
#         log "Warning: There were conflicts when applying stashed changes. Resolve manually."
#         log "Stashed changes preserved. Use 'git stash list' to see them and 'git stash pop' to apply."
#     fi
# fi

# Show current commit
log "Current commit: $(git rev-parse --short HEAD) - $(git log -1 --pretty=%s)"

# Fix permissions for web server before composer install
fix_web_permissions

# Install/Update PHP dependencies
log "Installing/updating PHP dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

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
echo "✓ Services restarted"
echo "✓ Application online"
echo "======================================"
echo "Deployment completed at: $(date)"
echo "======================================"