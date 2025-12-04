# Reverb Supervisor Setup Guide

This guide will walk you through setting up Laravel Reverb as a supervisor-managed service on your server.

## Prerequisites

- Supervisor installed and running
- Laravel Reverb installed in your project
- PHP 8.1+ installed
- Proper file permissions configured

## Step-by-Step Instructions

### Step 1: Create Supervisor Configuration File

Create a new supervisor configuration file for Reverb:

```bash
sudo nano /etc/supervisor/conf.d/reverb.conf
```

### Step 2: Add Reverb Configuration

Add the following configuration to the file (adjust paths as needed):

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/html/fairtrade-loans-backend/artisan reverb:start
directory=/var/www/html/fairtrade-loans-backend
autostart=true
autorestart=true
startretries=3
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/fairtrade-loans-backend/storage/logs/reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
stopasgroup=true
killasgroup=true
```

### Step 3: Configuration Explanation

- **`process_name`**: Name of the process (reverb)
- **`command`**: Full path to the artisan reverb:start command
- **`directory`**: Working directory (your Laravel project root)
- **`autostart`**: Start automatically when supervisor starts
- **`autorestart`**: Restart if the process crashes
- **`startretries`**: Number of retry attempts
- **`user`**: User to run the process as (www-data for web server)
- **`redirect_stderr`**: Redirect errors to stdout
- **`stdout_logfile`**: Log file location
- **`stdout_logfile_maxbytes`**: Maximum log file size before rotation
- **`stdout_logfile_backups`**: Number of log backups to keep
- **`stopwaitsecs`**: Time to wait before force killing
- **`stopasgroup`**: Stop the entire process group
- **`killasgroup`**: Kill the entire process group

### Step 4: Create Log Directory (if needed)

Ensure the log directory exists and has proper permissions:

```bash
sudo mkdir -p /var/www/html/fairtrade-loans-backend/storage/logs
sudo chown -R www-data:www-data /var/www/html/fairtrade-loans-backend/storage/logs
sudo chmod -R 775 /var/www/html/fairtrade-loans-backend/storage/logs
```

### Step 5: Reload Supervisor Configuration

After creating the configuration file, reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

### Step 6: Start Reverb Service

Start the Reverb service:

```bash
sudo supervisorctl start reverb:*
```

### Step 7: Check Status

Verify that Reverb is running:

```bash
sudo supervisorctl status reverb:*
```

You should see output like:
```
reverb:reverb_00 RUNNING   pid 12345, uptime 0:00:10
```

### Step 8: Monitor Logs

Monitor Reverb logs to ensure it's working correctly:

```bash
tail -f /var/www/html/fairtrade-loans-backend/storage/logs/reverb.log
```

You should see output indicating Reverb is starting and listening on the configured port.

### Step 9: Configure Reverb in .env

Ensure your `.env` file has the correct Reverb configuration:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

Generate Reverb keys if needed:

```bash
php artisan reverb:install
```

### Step 10: Test Reverb Connection

Test that Reverb is accessible:

```bash
curl http://localhost:8080
```

Or test from your application:

```bash
php artisan tinker
>>> broadcast(new \App\Events\NewNotificationEvent(\App\Models\Notification::first()));
```

## Common Commands

### Start Reverb
```bash
sudo supervisorctl start reverb:*
```

### Stop Reverb
```bash
sudo supervisorctl stop reverb:*
```

### Restart Reverb
```bash
sudo supervisorctl restart reverb:*
```

### View Reverb Status
```bash
sudo supervisorctl status reverb:*
```

### View Reverb Logs
```bash
tail -f /var/www/html/fairtrade-loans-backend/storage/logs/reverb.log
```

### View All Supervisor Programs
```bash
sudo supervisorctl status
```

## Troubleshooting

### Issue: Reverb won't start

1. Check supervisor logs:
   ```bash
   sudo tail -f /var/log/supervisor/supervisord.log
   ```

2. Check Reverb logs:
   ```bash
   tail -f /var/www/html/fairtrade-loans-backend/storage/logs/reverb.log
   ```

3. Verify file permissions:
   ```bash
   ls -la /var/www/html/fairtrade-loans-backend/artisan
   sudo chown www-data:www-data /var/www/html/fairtrade-loans-backend/artisan
   ```

4. Test the command manually:
   ```bash
   sudo -u www-data php /var/www/html/fairtrade-loans-backend/artisan reverb:start
   ```

### Issue: Port already in use

If port 8080 is already in use:

1. Check what's using the port:
   ```bash
   sudo lsof -i :8080
   ```

2. Change the port in `.env`:
   ```env
   REVERB_SERVER_PORT=8081
   ```

3. Update supervisor config and restart:
   ```bash
   sudo supervisorctl restart reverb:*
   ```

### Issue: Permission denied

Ensure proper ownership:
```bash
sudo chown -R www-data:www-data /var/www/html/fairtrade-loans-backend
sudo chmod -R 755 /var/www/html/fairtrade-loans-backend
sudo chmod -R 775 /var/www/html/fairtrade-loans-backend/storage
sudo chmod -R 775 /var/www/html/fairtrade-loans-backend/bootstrap/cache
```

### Issue: Reverb stops after deployment

The deployment script (`deploy.sh`) should automatically restart Reverb. If it doesn't:

1. Check the deployment script includes Reverb restart
2. Manually restart after deployment:
   ```bash
   sudo supervisorctl restart reverb:*
   ```

## Security Considerations

1. **Firewall**: Ensure port 8080 (or your configured port) is open:
   ```bash
   sudo ufw allow 8080/tcp
   ```

2. **SSL/TLS**: For production, use HTTPS/WSS:
   - Set `REVERB_SCHEME=https` in `.env`
   - Configure reverse proxy (Nginx) to handle SSL termination

3. **Nginx Configuration**: Add reverse proxy configuration:

   ```nginx
   location /app {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_cache_bypass $http_upgrade;
   }
   ```

## Auto-Start on Server Reboot

Supervisor should automatically start Reverb on server reboot if `autostart=true` is set. To verify supervisor starts on boot:

```bash
sudo systemctl enable supervisor
sudo systemctl status supervisor
```

## Multiple Reverb Instances (Scaling)

If you need to run multiple Reverb instances for scaling, you can create multiple supervisor programs:

```ini
[program:reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/fairtrade-loans-backend/artisan reverb:start
numprocs=4
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/fairtrade-loans-backend/storage/logs/reverb.log
```

This will start 4 Reverb instances. Ensure Redis is configured for scaling in `config/reverb.php`.

## Verification Checklist

- [ ] Supervisor configuration file created
- [ ] Configuration reloaded (`supervisorctl reread && supervisorctl update`)
- [ ] Reverb service started (`supervisorctl start reverb:*`)
- [ ] Status shows RUNNING (`supervisorctl status reverb:*`)
- [ ] Logs show successful startup
- [ ] Port is accessible (curl test)
- [ ] .env configuration is correct
- [ ] File permissions are correct
- [ ] Firewall allows the port
- [ ] Reverb restarts after deployment

## Next Steps

After Reverb is running:

1. Test WebSocket connection from your React app
2. Monitor logs for any errors
3. Set up log rotation if needed
4. Configure monitoring/alerting
5. Test notification broadcasting

## Additional Resources

- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Supervisor Documentation](http://supervisord.org/)
- Check your deployment script for automatic Reverb restart on deployments

