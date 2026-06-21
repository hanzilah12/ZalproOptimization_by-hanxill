
echo "🚀 Zalpro Optimization Setup Starting..."

# ==================== CREATE MAIN DIRECTORY ====================

sudo mkdir -p /ZalproOptimization

# ==================== SET PERMISSIONS ====================
sudo chown -R www-data:www-data /ZalproOptimization
sudo chmod -R 755 /ZalproOptimization


# ==================== FILE COPY INSTRUCTION ====================
echo "⚠️  Please use WinSCP (or FileZilla) to copy 'auth-live-log.php' file"
echo "   into the directory: /ZalproOptimization/"
echo "   (You already have the file, just upload it there)"


# ==================== EDIT PROFILE.PHP ====================

sudo nano /var/www/html/application/views/themes/legacy/admin_portal/users/profile.php

# === Nano Instructions ===
# 1. Press Ctrl + W
# 2. Type: profileDocument.php  and press Enter
# 3. Paste the following code right below it:

# ------------------- AUTH LIVE LOG -----------------------------
echo '<div class="row">';
include '/ZalproOptimization/auth-live-log.php';
echo '</div>';
# -------------------------------------------------------------

echo "✅ Setup completed successfully!"
