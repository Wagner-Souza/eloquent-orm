<?php

// Sınıfları otomatik yükle
require_once 'vendor/autoload.php';

// Composer autoload yoksa ORM dosyalarını manuel dahil et
require_once 'orm/Database.php';
require_once 'orm/QueryBuilder.php';
require_once 'orm/Model.php';
require_once 'orm/Models/User.php';
require_once 'orm/Models/Post.php';
require_once 'orm/Models/Profile.php';
require_once 'orm/Models/Comment.php';
require_once 'orm/Models/Role.php';
require_once 'orm/Models/Tag.php';


echo "=== Mini ORM Kütüphanesi Örneği ===\n\n";

try {
    // Veritabanı bağlantısını test et
    echo "1. Veritabanı Bağlantısı Test Ediliyor...\n";
    $connection = Database::getConnection();
    echo "✓ Veritabanı başarıyla bağlandı!\n\n";

    // 1. Temel CRUD İşlemleri
    echo "2. Temel CRUD İşlemleri:\n";
    echo "------------------------\n";

    // CREATE - Yeni kullanıcı oluştur
    echo "Yeni kullanıcı oluşturuluyor...\n";
    $newUser = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'age' => 28,
        'status' => 'active'
    ]);
    echo "✓ Kullanıcı oluşturuldu, ID: " . $newUser->getKey() . "\n";

    // READ - Kullanıcıyı ID ile bul
    echo "Kullanıcı ID ile aranıyor...\n";
    $foundUser = User::find($newUser->getKey());
    echo "✓ Kullanıcı bulundu: " . $foundUser->name . " (" . $foundUser->email . ")\n";

    // UPDATE - Kullanıcıyı güncelle
    echo "Kullanıcı güncelleniyor...\n";
    $foundUser->name = 'Updated Test User';
    $foundUser->age = 29;
    $foundUser->save();
    echo "✓ Kullanıcı adı güncellendi: " . $foundUser->name . "\n";

    // Alternatif güncelleme yöntemi
    $updatedRows = User::updateModel($foundUser->getKey(), ['status' => 'inactive']);
    echo "✓ Statik metod ile {$updatedRows} satır güncellendi\n";

    // DELETE - Kullanıcıyı sil
    echo "Kullanıcı siliniyor...\n";
    $deleted = $foundUser->delete();
    echo "✓ Kullanıcı silindi: " . ($deleted ? 'Evet' : 'Hayır') . "\n\n";

    // 2. Akıcı Sorgu Oluşturucu
    echo "3. Akıcı Sorgu Oluşturucu:\n";
    echo "------------------------\n";

    // Çoklu koşullu karmaşık sorgu
    echo "18 yaşından büyük aktif kullanıcılar isme göre sıralı aranıyor...\n";
    $activeUsers = User::where('status', 'active')
                      ->where('age', '>', 18)
                      ->orderBy('name', 'asc')
                      ->limit(10)
                      ->get();
    
    echo "✓ " . count($activeUsers) . " aktif kullanıcı bulundu:\n";
    foreach ($activeUsers as $user) {
        echo "  - {$user['name']} (Yaş: {$user['age']})\n";
    }
    echo "\n";

    // OR koşulları kullanma
    echo "Email'inde 'example' olan VEYA yaşı 25'ten büyük kullanıcılar aranıyor...\n";
    $users = User::where('email', 'like', '%example%')
                 ->orWhere('age', '>', 25)
                 ->get();
    echo "✓ Kriterlere uyan " . count($users) . " kullanıcı bulundu\n\n";

    // 3. Sorgu Oluşturucu Metodları
    echo "4. Sorgu Oluşturucu Metodları:\n";
    echo "-------------------------\n";

    // Sayma
    $userCount = User::where('status', 'active')->count();
    echo "✓ Aktif kullanıcı sayısı: {$userCount}\n";

    // İlk
    $firstUser = User::where('status', 'active')->first();
    echo "✓ İlk aktif kullanıcı: " . ($firstUser ? $firstUser['name'] : 'Yok') . "\n";

    // Var mı
    $hasInactiveUsers = User::where('status', 'inactive')->exists();
    echo "✓ Pasif kullanıcı var mı: " . ($hasInactiveUsers ? 'Evet' : 'Hayır') . "\n\n";

    // 4. İlişkiler
    echo "5. İlişkiler:\n";
    echo "-----------------\n";

    // Postları olan kullanıcı getir (bire-çok)
    echo "Postları olan kullanıcı getiriliyor...\n";
    $userWithPosts = User::find(1);
    if ($userWithPosts) {
        $posts = $userWithPosts->posts();
        echo "✓ '{$userWithPosts->name}' kullanıcısının " . count($posts) . " postu var:\n";
        foreach ($posts as $post) {
            echo "  - {$post->title}\n";
        }
    }
    echo "\n";

    // Yazarı ile birlikte post getir (ait olma)
    echo "Post yazarı ile birlikte getiriliyor...\n";
    $postWithUser = Post::find(1);
    if ($postWithUser) {
        $author = $postWithUser->user();
        echo "✓ '{$postWithUser->title}' postu yazan: " . ($author ? $author->name : 'Bilinmiyor') . "\n";
    }
    echo "\n";

    // Kullanıcı profili getir (bire-bir)
    echo "Kullanıcı profili getiriliyor...\n";
    $userWithProfile = User::find(1);
    if ($userWithProfile) {
        $profile = $userWithProfile->profile();
        echo "✓ Kullanıcı profil bio: " . ($profile ? substr($profile->bio, 0, 50) . '...' : 'Profil yok') . "\n";
    }
    echo "\n";

    // Kullanıcı rollerini getir (çoka-çok)
    echo "Kullanıcı rolleri getiriliyor...\n";
    $userWithRoles = User::find(1);
    if ($userWithRoles) {
        $roles = $userWithRoles->roles();
        echo "✓ Kullanıcının " . count($roles) . " rolü var:\n";
        foreach ($roles as $role) {
            echo "  - {$role->name}\n";
        }
    }
    echo "\n";

    // 5. Model Scope'ları ve Özel Metodlar
    echo "6. Model Scope'ları:\n";
    echo "----------------\n";

    // Özel scope'ları kullanma
    $activeUsers = User::active()->get();
    echo "✓ Aktif kullanıcılar (scope kullanarak): " . count($activeUsers) . "\n";

    $olderUsers = User::olderThan(25)->get();
    echo "✓ 25 yaşından büyük kullanıcılar: " . count($olderUsers) . "\n";

    $publishedPosts = Post::published()->get();
    echo "✓ Yayınlanmış postlar: " . count($publishedPosts) . "\n\n";

    // 6. Array ve JSON Dönüşümü
    echo "7. Veri Dönüşümü:\n";
    echo "-------------------\n";

    $user = User::find(1);
    if ($user) {
        echo "✓ Kullanıcı array olarak:\n";
        $userArray = $user->toArray();
        print_r(array_slice($userArray, 0, 3, true)); // İlk 3 alanı göster

        echo "✓ Kullanıcı JSON olarak:\n";
        $userJson = $user->toJson();
        echo substr($userJson, 0, 100) . "...\n\n";
    }

    // 7. Ham Sorgu Oluşturucu Kullanımı
    echo "8. Ham Sorgu Oluşturucu:\n";
    echo "---------------------\n";

    // QueryBuilder'ı bağımsız kullanma
    $queryBuilder = new QueryBuilder('users');
    $results = $queryBuilder
        ->select(['name', 'email', 'age'])
        ->where('status', 'active')
        ->where('age', '>=', 25)
        ->orderBy('age', 'desc')
        ->limit(3)
        ->get();

    echo "✓ Ham QueryBuilder sonuçları:\n";
    foreach ($results as $result) {
        echo "  - {$result['name']} (Yaş: {$result['age']})\n";
    }
    echo "\n";

    // Oluşturulan SQL'i göster
    $sql = $queryBuilder->toSql();
    echo "✓ Oluşturulan SQL: {$sql}\n";
    echo "✓ Bağlamalar: " . json_encode($queryBuilder->getBindings()) . "\n\n";

    // 8. Hata Yönetimi
    echo "9. Hata Yönetimi:\n";
    echo "------------------\n";

    try {
        // Olmayan bir kullanıcı bulmaya çalış
        $nonExistentUser = User::findOrFail(99999);
    } catch (RuntimeException $e) {
        echo "✓ Beklenen hata yakalandı: " . $e->getMessage() . "\n";
    }

    try {
        // Geçersiz veri ile kullanıcı oluşturmaya çalış (tekrar eden email)
        User::create([
            'name' => 'Duplicate User',
            'email' => 'ali@example.com', // Bu email zaten var
            'password' => 'password123'
        ]);
    } catch (Exception $e) {
        echo "✓ Tekrar eden email için veritabanı hatası yakalandı\n";
    }

    echo "\n=== Tüm Örnekler Başarıyla Tamamlandı! ===\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "Hata izleme:\n" . $e->getTraceAsString() . "\n";
}
