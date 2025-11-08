# Configure SSH Access
sudo -u your-username mkdir -p /home/your-username/.ssh
sudo -u your-username chmod 700 /home/your-username/.ssh

sudo chown -R your-username:your-username /home/your-username/.ssh

sudo -u your-username touch /home/your-username/.ssh/authorized_keys
sudo -u your-username chmod 600 /home/your-username/.ssh/authorized_keys

sudo chmod 755 /home/your-username
sudo chown your-username:your-username /home/your-username

// Create SUDO User
adduser username

usermod -aG sudo username

// Generate SSH Key Pair
ssh-keygen -t ecdsa -b 521

// Install PHP and Extensions
sudo apt update
sudo add-apt-repository ppa:ondrej/php
sudo apt install php8.3-cli
sudo apt install php8.3-common php8.3-bcmath php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-mysql

// Check PHP installed modules
php8.3 -m

// Install Composer
sudo apt install composer

// Install MySQL/Secure Installation
sudo apt install mysql-server
sudo mysql_secure_installation

// Setup Database and User
CREATE DATABASE yourdatabase;
CREATE USER 'your-username'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON yourdatabase.* TO 'your-username'@'localhost';
FLUSH PRIVILEGES;
EXIT;

// Install NVM/NodeJS
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash

export NVM_DIR="$([ -z "${XDG_CONFIG_HOME-}" ] && printf %s "${HOME}/.nvm" || printf %s "${XDG_CONFIG_HOME}/nvm")"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

nvm --version

nvm install --lts

// Taking directory ownership
sudo chown -R username /directory-name

// Taking file ownership
sudo chown username filename

// Proper Directory Permissions (Navigate to your project root directory)
sudo chown -R $USER:www-data .

sudo find . -type f -exec chmod 664 {} \;

sudo find . -type d -exec chmod 775 {} \;

sudo chgrp -R www-data storage bootstrap/cache

sudo chmod -R ug+rwx storage bootstrap/cache

// Install Nginx Server and Configure Virtual Host aka Server Blocks:
sudo apt install nginx

// Configure Server Blocks for your website in Nginx.
// Navigate to the directory /etc/nginx/sites-available to create a server block file.
cd /etc/nginx/sites-available
sudo vim example.com # Replace the example.com with your website or web app domain.

// Go to laravel.com, search for nginx site configuration, scroll down, copy the server configuration code, and paste it into the vim server block file.
// Replace the server_name which is example.com with your site domain
// Replace the root path /srv/example.com/public with your site Laravel public directory path.

# note: Scroll down in the same vim file and look for php-fpm which should match the PHP version installed on your server where the app is deployed.
//Save the file and exit it from vim.
// Now create a symbolic link for the server block that we just created.
cd /etc/nginx/sites-available/example.com /etc/nginx/sites-enabled # Replace the example.com with the domain name for which we just created a server block.

// Test the nginx server for any issues or errors.
sudo nginx -t

// Restart the nginx server and then access your website or web app
sudo systemctl restart nginx

// Install Certbot
sudo apt install certbot python3-certbot-nginx

// Issue SSL Cert
sudo certbot --nginx -d glennraya.com

// SSH Config file (keep ssh-agent active)
User git
Hostname github.com
IdentityFile ~/.ssh/id_github
TCPKeepAlive yes
IdentitiesOnly yes
ServerAliveInterval 60


server {
    listen 80;
    listen [::]:80;
    server_name fairtradeapi.jawbonetechnology.co.ke;
    root /var/www/html/fairtrade-loans-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 3000;
    server_name fairtradeonline.jawbonetechnology.co.ke;

    root /var/www/html/fairtrade-loans-webapp/current;
    index index.html;

    access_log /var/log/nginx/fairtradeonline.access.log;
    error_log /var/log/nginx/fairtradeonline.error.log;

    # React Router support: redirect all routes to index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache static assets (JS, CSS, images)
    location ~* \.(?:js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot)$ {
        expires 7d;
        add_header Cache-Control "public, no-transform";
        try_files $uri =404;
    }
}

Kindly login to github and upload the project using the details below:

Gmail Account
Username: Jawbone Software
Email Address: jawbonetechnology@gmail.com
Password: Th@nkuL0rd

Github Account
Username: JawboneTechnology
Email Address: jawbonetechnology@gmail.com
Password: Th@nkuL0rd