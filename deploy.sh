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

log "Pulling latest changes..."
git pull origin $BRANCH

# Show current commit
log "Current commit: $(git rev-parse --short HEAD) - $(git log -1 --pretty=%s)"

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

# Set proper file permissions
log "Setting proper file permissions..."
sudo chown -R $USER:$USER $PROJECT_ROOT
sudo chmod -R 775 $PROJECT_ROOT/storage
sudo chmod -R 775 $PROJECT_ROOT/bootstrap/cache

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
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost || log "Could not check application status"

# Show application info
log "Application information:"
php artisan about

echo "======================================"
echo "Deployment Summary"
echo "======================================"
echo "✓ Git pull completed"
echo "✓ Dependencies updated" 
echo "✓ Database migrations run"
echo "✓ Caches cleared and rebuilt"
echo "✓ Queue workers restarted"
echo "✓ File permissions set"
echo "✓ Services restarted"
echo "✓ Application online"
echo "======================================"
echo "Deployment completed at: $(date)"
echo "======================================"

# Make the script executable
# chmod +x deploy.sh