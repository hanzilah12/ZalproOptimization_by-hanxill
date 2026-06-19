================================================================
ZALPRO - AUTH LIVE LOG MODULE INSTALLATION GUIDE (DETAILED)
================================================================

1. CREATE DIRECTORY AND SET PERMISSIONS

Run these commands on your server:

sudo mkdir -p /zalpro-optimization
sudo chown -R www-data:www-data /zalpro-optimization
sudo chmod -R 755 /zalpro-optimization

Explanation:
- Creates module directory
- Sets ownership for web server (Apache/Nginx)
- Sets safe production permissions

----------------------------------------------------------------

2. UPLOAD MODULE FILE

File name:
auth-live-log.php

Upload location:
/zalpro-optimization/auth-live-log.php

Use WinSCP / FileZilla / SCP:
- Connect to server via SFTP
- Navigate to /zalpro-optimization/
- Upload auth-live-log.php

----------------------------------------------------------------

3. INTEGRATE INTO PROFILE PAGE

Open file:

sudo nano /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

Inside editor:
- Press CTRL + W
- Search:
profileDocument.php

Just below that line, paste:

#<!-- Auth Live Log Integration START -->
echo '<div class="row">';
include '/zalpro-optimization/auth-live-log'; 
echo '</div>';
#<!-- Auth Live Log Integration END -->

Save & Exit:
CTRL + O → ENTER → CTRL + X

----------------------------------------------------------------

4. VERIFY INSTALLATION

Open admin panel:
- Go to User Profile Page

Check:
- Auth Live Log section appears
- No PHP errors
- Data/logs are rendering correctly

----------------------------------------------------------------

5. TROUBLESHOOTING

If module not showing:

Check file exists:
ls -l /zalpro-optimization/auth-live-log.php

Fix permissions:
sudo chmod 755 /zalpro-optimization/auth-live-log.php

Restart server if needed:
sudo systemctl restart apache2
OR
sudo systemctl restart nginx

Check include path:
'/zalpro-optimization/auth-live-log.php'

================================================================
END OF INSTALLATION GUIDE
================================================================
