<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../orm/Database.php';
require_once __DIR__ . '/../orm/QueryBuilder.php';
require_once __DIR__ . '/../orm/Model.php';
require_once __DIR__ . '/../orm/Models/User.php';
require_once __DIR__ . '/../orm/Models/Post.php';
require_once __DIR__ . '/../orm/Models/Profile.php';

class ModelTest extends TestCase
{
    protected static $testUserId;

    public static function setUpBeforeClass(): void
    {
        // Test veritabanını yapılandır
        Database::setConfig([
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'eloquent_orm',  // Mevcut veritabanını kullan
            'username' => 'root',
            'password' => 'root'  // Doğru şifre
        ]);

        // Test tabloları oluştur (test için basitleştirilmiş)
        try {
            $connection = Database::getConnection();
            
            // Users tablosu oluştur
            $connection->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    age INT DEFAULT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Posts tablosu oluştur
            $connection->exec("
                CREATE TABLE IF NOT EXISTS posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    content TEXT,
                    status ENUM('draft', 'published') DEFAULT 'draft',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            // Profiles tablosu oluştur
            $connection->exec("
                CREATE TABLE IF NOT EXISTS profiles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE NOT NULL,
                    bio TEXT,
                    avatar VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

        } catch (Exception $e) {
            // Test veritabanı yoksa, veritabanına bağımlı testleri atla
            self::markTestSkipped('Test veritabanı kullanılamıyor: ' . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $connection = Database::getConnection();
            $connection->exec("DROP TABLE IF EXISTS profiles");
            $connection->exec("DROP TABLE IF EXISTS posts");
            $connection->exec("DROP TABLE IF EXISTS users");
        } catch (Exception $e) {
            // Temizleme hatalarını yoksay
        }
    }

    protected function setUp(): void
    {
        // Her testten önce veriyi temizle
        try {
            $connection = Database::getConnection();
            $connection->exec("DELETE FROM profiles");
            $connection->exec("DELETE FROM posts");
            $connection->exec("DELETE FROM users");
            $connection->exec("ALTER TABLE users AUTO_INCREMENT = 1");
        } catch (Exception $e) {
            $this->markTestSkipped('Veritabanı temizliği başarısız: ' . $e->getMessage());
        }
    }

    public function testDatabaseConnection()
    {
        $connection = Database::getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testQueryBuilderBasicSelect()
    {
        $queryBuilder = new QueryBuilder('users');
        $sql = $queryBuilder->select(['name', 'email'])->toSql();
        
        $this->assertEquals('SELECT name, email FROM users', $sql);
    }

    public function testQueryBuilderWithWhere()
    {
        $queryBuilder = new QueryBuilder('users');
        $queryBuilder->where('status', 'active')->where('age', '>', 18);
        
        $sql = $queryBuilder->toSql();
        $bindings = $queryBuilder->getBindings();
        
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('age', $sql);
        $this->assertCount(2, $bindings);
    }

    public function testQueryBuilderWithOrderAndLimit()
    {
        $queryBuilder = new QueryBuilder('users');
        $sql = $queryBuilder
            ->orderBy('name', 'desc')
            ->limit(10)
            ->offset(5)
            ->toSql();
        
        $this->assertStringContainsString('ORDER BY name DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
    }

    public function testModelCreate()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'age' => 25,
            'status' => 'active'
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNotNull($user->getKey());
        
        self::$testUserId = $user->getKey();
    }

    public function testModelFind()
    {
        // Önce bir kullanıcı oluştur
        $createdUser = User::create([
            'name' => 'Find Test User',
            'email' => 'find@example.com',
            'password' => 'password123'
        ]);

        // Sonra onu bul
        $foundUser = User::find($createdUser->getKey());
        
        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($createdUser->getKey(), $foundUser->getKey());
        $this->assertEquals('Find Test User', $foundUser->name);
    }

    public function testModelFindOrFail()
    {
        $this->expectException(RuntimeException::class);
        User::findOrFail(99999);
    }

    public function testModelUpdate()
    {
        // Bir kullanıcı oluştur
        $user = User::create([
            'name' => 'Update Test',
            'email' => 'update@example.com',
            'password' => 'password123'
        ]);

        // Kullanıcıyı güncelle
        $user->name = 'Updated Name';
        $user->age = 30;
        $result = $user->save();

        $this->assertTrue($result);
        
        // Güncellemeyi doğrula
        $updatedUser = User::find($user->getKey());
        $this->assertEquals('Updated Name', $updatedUser->name);
        $this->assertEquals(30, $updatedUser->age);
    }

    public function testModelDelete()
    {
        // Bir kullanıcı oluştur
        $user = User::create([
            'name' => 'Delete Test',
            'email' => 'delete@example.com',
            'password' => 'password123'
        ]);

        $userId = $user->getKey();
        
        // Kullanıcıyı sil
        $result = $user->delete();
        $this->assertTrue($result);
        
        // Silme işlemini doğrula
        $deletedUser = User::find($userId);
        $this->assertNull($deletedUser);
    }

    public function testModelWhere()
    {
        // Test kullanıcıları oluştur
        User::create(['name' => 'Active User 1', 'email' => 'active1@example.com', 'password' => 'pass', 'status' => 'active']);
        User::create(['name' => 'Active User 2', 'email' => 'active2@example.com', 'password' => 'pass', 'status' => 'active']);
        User::create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'password' => 'pass', 'status' => 'inactive']);

        $activeUsers = User::where('status', 'active')->get();
        
        $this->assertCount(2, $activeUsers);
        $this->assertEquals('active', $activeUsers[0]['status']);
        $this->assertEquals('active', $activeUsers[1]['status']);
    }

    public function testModelCount()
    {
        // Test kullanıcıları oluştur
        User::create(['name' => 'Count User 1', 'email' => 'count1@example.com', 'password' => 'pass']);
        User::create(['name' => 'Count User 2', 'email' => 'count2@example.com', 'password' => 'pass']);

        $count = User::query()->count();
        $this->assertEquals(2, $count);
    }

    public function testModelFirst()
    {
        // Test kullanıcıları oluştur
        User::create(['name' => 'First User', 'email' => 'first@example.com', 'password' => 'pass']);
        User::create(['name' => 'Second User', 'email' => 'second@example.com', 'password' => 'pass']);

        $firstUser = User::query()->orderBy('id', 'asc')->first();
        
        $this->assertNotNull($firstUser);
        $this->assertEquals('First User', $firstUser['name']);
    }

    public function testModelExists()
    {
        $exists = User::where('email', 'nonexistent@example.com')->exists();
        $this->assertFalse($exists);

        User::create(['name' => 'Exists User', 'email' => 'exists@example.com', 'password' => 'pass']);
        
        $exists = User::where('email', 'exists@example.com')->exists();
        $this->assertTrue($exists);
    }

    public function testModelToArray()
    {
        $user = User::create([
            'name' => 'Array Test',
            'email' => 'array@example.com',
            'password' => 'password123',
            'age' => 25
        ]);

        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('Array Test', $array['name']);
        $this->assertEquals('array@example.com', $array['email']);
        $this->assertEquals(25, $array['age']);
    }

    public function testModelToJson()
    {
        $user = User::create([
            'name' => 'JSON Test',
            'email' => 'json@example.com',
            'password' => 'password123'
        ]);

        $json = $user->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertIsString($json);
        $this->assertIsArray($decoded);
        $this->assertEquals('JSON Test', $decoded['name']);
    }

    public function testModelFillable()
    {
        $user = new User();
        $user->fill([
            'name' => 'Fillable Test',
            'email' => 'fillable@example.com',
            'password' => 'password123',
            'invalid_field' => 'should_be_ignored'
        ]);

        $this->assertEquals('Fillable Test', $user->name);
        $this->assertEquals('fillable@example.com', $user->email);
        $this->assertNull($user->getAttribute('invalid_field'));
    }

    public function testModelGetTable()
    {
        $user = new User();
        $this->assertEquals('users', $user->getTable());
    }

    public function testModelGetKeyName()
    {
        $user = new User();
        $this->assertEquals('id', $user->getKeyName());
    }

    public function testQueryBuilderJoin()
    {
        $queryBuilder = new QueryBuilder('users');
        $sql = $queryBuilder
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->toSql();
        
        $this->assertStringContainsString('INNER JOIN profiles ON users.id = profiles.user_id', $sql);
    }

    public function testQueryBuilderLeftJoin()
    {
        $queryBuilder = new QueryBuilder('users');
        $sql = $queryBuilder
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->toSql();
        
        $this->assertStringContainsString('LEFT JOIN profiles ON users.id = profiles.user_id', $sql);
    }

    public function testQueryBuilderWhereIn()
    {
        $queryBuilder = new QueryBuilder('users');
        $queryBuilder->whereIn('id', [1, 2, 3]);
        
        $sql = $queryBuilder->toSql();
        $bindings = $queryBuilder->getBindings();
        
        $this->assertStringContainsString('IN (', $sql);
        $this->assertCount(3, $bindings);
    }

    public function testModelStaticDestroy()
    {
        $user = User::create([
            'name' => 'Destroy Test',
            'email' => 'destroy@example.com',
            'password' => 'password123'
        ]);

        $userId = $user->getKey();
        $deletedCount = User::destroy($userId);
        
        $this->assertEquals(1, $deletedCount);
        $this->assertNull(User::find($userId));
    }

    public function testModelStaticUpdate()
    {
        $user = User::create([
            'name' => 'Static Update Test',
            'email' => 'static@example.com',
            'password' => 'password123'
        ]);

        $updatedCount = User::updateModel($user->getKey(), ['name' => 'Updated Static']);
        
        $this->assertEquals(1, $updatedCount);
        
        $updatedUser = User::find($user->getKey());
        $this->assertEquals('Updated Static', $updatedUser->name);
    }
}
