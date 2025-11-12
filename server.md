## Set-up VPS
ssh root@[your server_ip]

# Update system

sudo apt update && sudo apt upgrade -y

# Add user

sudo adduser deploy
sudo usermod -aG sudo deploy

su - deploy

ssh deploy@[your server_ip] # this is the password base authentication

# ssh key basa authentication (Enhanced security)

- create ssh in your local machine

## Install phpmyadmin on VPS server.

sudo apt update
sudo apt install phpmyadmin
sudo apt install php-mbstring php-zip php-gd php-json php-curl

# Stop apache and allow nginx to run

sudo systemctl stop apache2
sudo systemctl disable apache2

# Configure nginx for phpmyadmin

sudo vim /etc/nginx/sites-available/phpmyadmin

server {
    listen 9000;  # Use a different port
    server_name your-server-ip;
    
    root /usr/share/phpmyadmin;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Ensure this marches your fpm version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}

# Enable and test

sudo ln -s /etc/nginx/sites-available/phpmyadmin /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

## Fix mysql root access issues

sudo mysql -u root

SELECT user, host, plugin, authentication_string FROM mysql.user WHERE user = 'root';

CREATE USER 'phpmyadmin'@'localhost' IDENTIFIED BY 'strong-password-here';

GRANT ALL PRIVILEGES ON *.* TO 'phpmyadmin'@'localhost' WITH GRANT OPTION;

FLUSH PRIVILEGES;

EXIT;

# Update phpmyadmin configuration

sudo vim /etc/phpmyadmin/config.inc.php

/* If using the dedicated user (Option A) */
$cfg['Servers'][$i]['controluser'] = 'phpmyadmin';
$cfg['Servers'][$i]['controlpass'] = 'strong-password-here';

/* If using root with password (Option B) */
// $cfg['Servers'][$i]['controluser'] = 'root';
// $cfg['Servers'][$i]['controlpass'] = 'your-new-root-password';

sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm  # Adjust PHP version

## Laravel Scheduler For Loan applicants repayment reminder

# Open crontab
sudo crontab -e

# Add this single line (replace with your actual path)
* * * * * cd /var/www/your-loan-api && php artisan schedule:run >> /dev/null 2>&1

# Check scheduled tasks
cd /var/www/your-loan-api
php artisan schedule:list

# Test run manually
php artisan schedule:run