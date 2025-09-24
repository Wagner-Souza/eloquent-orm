# Mini PHP ORM

Laravel Eloquent tarzı hafif ORM kütüphanesi. PHP 8+ ve MySQL için tasarlandı.

## Kurulum

### Docker ile (Önerilen)

```bash
# Veritabanını başlat
docker-compose up -d

# Tabloları oluştur
mysql -h127.0.0.1 -P3306 -uroot -proot eloquent_orm < sql/init.sql
```

### Manuel Kurulum

```bash
# MySQL'de veritabanı oluştur
CREATE DATABASE eloquent_orm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# Tabloları import et
mysql -u root -p eloquent_orm < sql/init.sql

# .env dosyasını ayarla
cp .env.example .env
```

## Konfigürasyon

`.env` dosyasını düzenle:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eloquent_orm
DB_USERNAME=root
DB_PASSWORD=root
DB_CHARSET=utf8mb4
```

## Kullanım

### Temel CRUD

```php
require_once 'orm/Database.php';
require_once 'orm/QueryBuilder.php';
require_once 'orm/Model.php';
require_once 'orm/Models/User.php';

// Oluştur
$user = User::create([
    'name' => 'Ali Veli',
    'email' => 'ali@example.com',
    'age' => 25
]);

// Bul
$user = User::find(1);

// Güncelle
$user->name = 'Yeni İsim';
$user->save();

// Sil
$user->delete();
```

### Sorgu Oluşturucu

```php
// Basit sorgular
$users = User::where('status', 'active')->get();
$count = User::where('age', '>', 18)->count();
$first = User::where('email', 'test@example.com')->first();

// Karmaşık sorgular
$users = User::where('status', 'active')
             ->where('age', '>', 18)
             ->orderBy('name')
             ->limit(10)
             ->get();
```

### İlişkiler

```php
// User modelinde
public function posts(): array
{
    return $this->hasMany(Post::class, 'user_id');
}

public function profile(): ?Model
{
    return $this->hasOne(Profile::class, 'user_id');
}

// Kullanım
$user = User::find(1);
$posts = $user->posts();
$profile = $user->profile();
```

### Scope'lar

```php
// User modelinde
public static function active(): QueryBuilder
{
    return static::where('status', 'active');
}

// Kullanım
$activeUsers = User::active()->get();
```

## Test

### Docker ile Test

```bash
# Test veritabanını hazırla
mysql -h127.0.0.1 -P3306 -uroot -proot -e "CREATE DATABASE IF NOT EXISTS eloquent_orm_test"

# Testleri çalıştır
./vendor/bin/phpunit tests/
```

### Manuel Test

```bash
# Test veritabanını oluştur
CREATE DATABASE eloquent_orm_test;

# Composer bağımlılıklarını yükle
composer install

# Testleri çalıştır
composer test
```

## Örnek Çalıştırma

```bash
# Tüm özellikleri test et
php example.php
```

## Proje Yapısı

```
orm/
├── Database.php        # Veritabanı bağlantısı
├── QueryBuilder.php    # SQL sorgu oluşturucu
├── Model.php          # Temel model sınıfı
└── Models/            # Model sınıfları
    ├── User.php
    ├── Post.php
    ├── Profile.php
    └── ...
sql/
└── init.sql           # Veritabanı şeması
tests/
└── ModelTest.php      # Unit testler
```

## Özellikler

- ✅ CRUD işlemleri
- ✅ Akıcı sorgu oluşturucu
- ✅ Model ilişkileri (hasOne, hasMany, belongsTo, belongsToMany)
- ✅ Prepared statements (SQL injection koruması)
- ✅ Model scope'ları
- ✅ Array/JSON dönüştürme
- ✅ Unit testler
- ✅ UTF8MB4 charset desteği
