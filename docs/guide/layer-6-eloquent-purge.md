# Layer 6: Eloquent Model Lifecycle Integration (`HasUploadPurge`)

When updating or deleting Eloquent models, old files are often abandoned on disk or S3 unless manually managed.

Smart Upload provides the `HasUploadPurge` trait to automate this lifecycle safely without requiring custom model observers.

---

### Setup on Model

Add the trait to your Eloquent model and declare the attributes holding stored paths:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use KaziOmar\SmartUpload\Traits\HasUploadPurge;

class User extends Model
{
    use HasUploadPurge;

    // Attributes mapped here are cleaned up on update or deletion:
    protected array $purgeableFiles = ['avatar', 'cover_photo'];
}
```

---

### Lifecycle Behaviors

#### 1. Automatic Cleanup on Model Update

```php
$user->avatar = 'avatars/new_photo.webp';
$user->save();
```

When an attribute in `$purgeableFiles` changes, the previous file (`avatars/old_photo.webp`) and all its sibling responsive variants (e.g. `avatars/old_photo_thumb.webp`) are automatically deleted from storage.

#### 2. Automatic Cleanup on Model Deletion

```php
$user->delete();
```

When a model is deleted, all stored files referenced in `$purgeableFiles` are purged from the storage disk.

#### 3. Array & JSON Column Support

For models storing multiple image paths in a single column (such as `$casts = ['photos' => 'array']`):

```php
// Updating an array column removes only the omitted files:
$user->update([
    'photos' => ['products/photo_2.webp', 'products/photo_3.webp']
]);
```

`HasUploadPurge` calculates the array difference between the old and new states, purging only `photo_1.webp` while leaving the retained files untouched.

---

### SoftDeletes Compatibility

If your Eloquent model uses Laravel's `Illuminate\Database\Eloquent\SoftDeletes` trait, Smart Upload adapts automatically:

* **Soft Delete (`$model->delete()`):** Files are **preserved on disk**, ensuring restored records retain their associated media.
* **Permanent Delete (`$model->forceDelete()`):** Files and all sibling variants are permanently purged from storage.

---

### Query Builder Caveats

The `HasUploadPurge` trait hooks into Eloquent's `updating` and `deleting` lifecycle events. 

Direct database queries executed through the query builder bypass model events:

```php
// Bypasses Eloquent events — trait will NOT fire:
User::where('id', 1)->update(['avatar' => 'avatars/new.webp']);
```

For direct database operations, either:
1. Retrieve the model instance first: `$user = User::find(1); $user->update([...]);`
2. Or use the fluent builder's replacement helper: `Upload::file($file)->replace($oldAvatar)->save('avatars');`

---

### Constraining Deletions (`purge_allowed_directories`)

To prevent misconfigured attributes from deleting files outside upload folders, you can restrict purges in `config/smart-upload.php`:

```php
'purge_allowed_directories' => ['avatars', 'products', 'gallery'],
```

When defined, any purge request targeting files outside these specified directories will be safely ignored.
