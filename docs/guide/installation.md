# Installation & Setup

### Requirements

- **PHP:** `8.2`, `8.3`, `8.4`, or `8.5+`
- **Laravel Framework:** `11.x`, `12.x`, or `13.x`
- **PHP Extensions:** `fileinfo`, `gd` (or `imagick`), `exif`

---

### Step 1: Install via Composer

```bash
composer require thekaziomar/laravel-smart-upload
```

### Step 2: Publish Configuration

Publish the package configuration file to `config/smart-upload.php`:

```bash
php artisan vendor:publish --tag="smart-upload-config"
```

### Step 3: Link Storage (If using local public disk)

Ensure your public storage symlink is active:

```bash
php artisan storage:link
```

