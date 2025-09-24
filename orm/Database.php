<?php

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
    private static bool $envLoaded = false;

    /**
     * Environment dosyasını yükle
     */
    private static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Yorum satırlarını atla
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        self::$envLoaded = true;
    }

    /**
     * Environment değişkeninden değer getir
     */
    private static function env(string $key, $default = null)
    {
        self::loadEnv();
        return $_ENV[$key] ?? $default;
    }

    /**
     * Varsayılan konfigürasyonu getir
     */
    private static function getDefaultConfig(): array
    {
        return [
            'host' => self::env('DB_HOST', 'localhost'),
            'port' => self::env('DB_PORT', '3306'),
            'database' => self::env('DB_DATABASE', 'eloquent_orm'),
            'username' => self::env('DB_USERNAME', 'root'),
            'password' => self::env('DB_PASSWORD', 'root'),
            'charset' => self::env('DB_CHARSET', 'utf8mb4')
        ];
    }

    /**
     * Veritabanı ayarlarını belirle
     */
    public static function setConfig(array $config): void
    {
        self::$config = array_merge(self::getDefaultConfig(), $config);
    }

    /**
     * PDO bağlantısını getir (Singleton pattern)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // Eğer config boşsa, environment'tan yükle
            if (empty(self::$config)) {
                self::$config = self::getDefaultConfig();
            }
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Veritabanı bağlantısını kur
     */
    private static function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        try {
            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Veritabanı bağlantısı başarısız: " . $e->getMessage());
        }
    }

    /**
     * Parametreli sorgu çalıştır
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $connection = self::getConnection();
        $statement = $connection->prepare($sql);
        $statement->execute($params);
        
        return $statement;
    }

    /**
     * Son eklenen ID'yi getir
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Transaction başlat
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Transaction'ı onayla
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Transaction'ı geri al
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Veritabanı bağlantısını kapat
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
