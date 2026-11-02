# Layer 6: Eloquent Model Lifecycle Integration (`HasUploadPurge`)

When updating or deleting Eloquent models, old files are often left behind on disk or S3 unless manually managed.

Smart Upload provides the `HasUploadPurge` trait to automate this lifecycle safely.

---

### Setup on Model

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use KaziOmar\SmartUpload\Traits\HasUploadPurge;

class User extends Authenticatable
{
    use HasUploadPurge;

    // Define which attributes hold upload paths:
    protected array $purgeableFiles = ['avatar', 'cover_photo'];
}
```

---

### Automatic Behavior

#### 1. On Model Update:
```php
$user->avatar = 'avatars/new_avatar.webp';
$user->save();
```
The previous avatar file (`avatars/old_avatar.webp`) and all its sibling responsive variants (e.g. `avatars/old_avatar_thumb.webp`) are automatically deleted from storage!

#### 2. On Model Deletion:
```php
$user->delete();
```
All files listed in `$purgeableFiles` are purged from the storage disk.

#### 3. Supports JSON Array Columns:
If you store arrays of images (e.g. `$casts = ['photos' => 'array']`), `HasUploadPurge` automatically computes the diff and only deletes the removed images from storage!

