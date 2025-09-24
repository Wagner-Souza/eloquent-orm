<?php

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = [];
    protected array $attributes = [];
    protected array $original = [];
    public bool $exists = false;
    protected array $relations = [];
    protected static array $relationshipCache = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        
        // Constructor'da model henüz veritabanında yok
        // exists sadece veritabanından gelen veriler için true olmalı
        $this->exists = false;
        $this->original = [];
    }

    /**
     * Model için tablo adını getir
     */
    public function getTable(): string
    {
        if (empty($this->table)) {
            $className = (new ReflectionClass($this))->getShortName();
            $this->table = strtolower($className) . 's';
        }
        
        return $this->table;
    }

    /**
     * Model için primary key'i getir
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Modelin primary key değerini getir
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Modelin veritabanında var olup olmadığını kontrol et
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Modeli attribute array'i ile doldur
     */
    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Verilen array'den fillable attribute'ları getir
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->fillable) > 0 && !in_array('*', $this->guarded)) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return array_diff_key($attributes, array_flip($this->guarded));
    }

    /**
     * Modelde belirli bir attribute'u ayarla
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Modelden bir attribute getir
     */
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Modeldeki tüm mevcut attribute'ları getir
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Model için yeni sorgu oluşturucu oluştur
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder((new static)->getTable());
    }

    /**
     * Yeni model instance'i oluştur
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Primary key ile model bul
     */
    public static function find($id): ?static
    {
        $instance = new static;
        $result = static::query()
            ->where($instance->getKeyName(), $id)
            ->first();

        if ($result) {
            $model = new static();
            $model->fill($result);
            $model->exists = true;
            $model->original = $result;
            return $model;
        }

        return null;
    }

    /**
     * Primary key ile model bul veya exception fırlat
     */
    public static function findOrFail($id): static
    {
        $model = static::find($id);
        
        if ($model === null) {
            throw new RuntimeException("ID ile model bulunamadı: {$id}");
        }

        return $model;
    }

    /**
     * WHERE koşulu ile model sorgusuna başla
     */
    public static function where(string $column, string $operator = '=', $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Tüm modelleri getir
     */
    public static function all(): array
    {
        $results = static::query()->get();
        return array_map(function($result) {
            $model = new static($result);
            $model->exists = true;
            $model->original = $result;
            return $model;
        }, $results);
    }

    /**
     * Primary key ile modeli güncelle
     */
    public static function updateModel($id, array $attributes): int
    {
        $instance = new static;
        return static::query()
            ->where($instance->getKeyName(), $id)
            ->update($attributes);
    }

    /**
     * Primary key ile modeli sil
     */
    public static function destroy($id): int
    {
        $instance = new static;
        return static::query()
            ->where($instance->getKeyName(), $id)
            ->delete();
    }

    /**
     * Modeli veritabanına kaydet
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Model insert işlemi gerçekleştir
     */
    protected function performInsert(): bool
    {
        $attributes = $this->getAttributes();
        
        if (empty($attributes)) {
            return true;
        }

        $inserted = static::query()->insert($attributes);
        
        if ($inserted) {
            $this->exists = true;
            $this->original = $attributes;
            
            // Otomatik oluşturulmuşsa primary key'i ayarla
            if (!$this->getKey()) {
                $lastId = Database::lastInsertId();
                if ($lastId && $lastId !== '0') {
                    $this->setAttribute($this->getKeyName(), (int)$lastId);
                }
            }
            
        }

        return $inserted;
    }

    /**
     * Model update işlemi gerçekleştir
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();
        
        if (empty($dirty)) {
            return true;
        }

        $updated = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->update($dirty);

        if ($updated > 0) {
            $this->original = array_merge($this->original, $dirty);
        }

        return $updated > 0;
    }

    /**
     * Son senkronizasyondan beri değişen attribute'ları getir
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Modeli veritabanından sil
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
        }

        return $deleted > 0;
    }

    /**
     * Bire-bir ilişki tanımla
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): ?Model
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();

        $relatedInstance = new $related;
        $result = $relatedInstance::query()
            ->where($foreignKey, $this->getAttribute($localKey))
            ->first();

        return $result ? new $related($result) : null;
    }

    /**
     * Bire-çok ilişki tanımla
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();

        $relatedInstance = new $related;
        $results = $relatedInstance::query()
            ->where($foreignKey, $this->getAttribute($localKey))
            ->get();

        return array_map(fn($result) => new $related($result), $results);
    }

    /**
     * Ters bire-bir veya bire-çok ilişki tanımla
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): ?Model
    {
        $relatedInstance = new $related;
        $foreignKey = $foreignKey ?? $relatedInstance->getForeignKey();
        $ownerKey = $ownerKey ?? $relatedInstance->getKeyName();

        $result = $relatedInstance::query()
            ->where($ownerKey, $this->getAttribute($foreignKey))
            ->first();

        return $result ? new $related($result) : null;
    }

    /**
     * Çoka-çok ilişki tanımla
     */
    protected function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): array
    {
        $relatedInstance = new $related;
        
        $table = $table ?? $this->joiningTable($related);
        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?? $relatedInstance->getForeignKey();

        $results = static::query()
            ->select([$relatedInstance->getTable() . '.*'])
            ->join($table, $this->getTable() . '.' . $this->getKeyName(), '=', $table . '.' . $foreignPivotKey)
            ->join($relatedInstance->getTable(), $table . '.' . $relatedPivotKey, '=', $relatedInstance->getTable() . '.' . $relatedInstance->getKeyName())
            ->where($this->getTable() . '.' . $this->getKeyName(), $this->getKey())
            ->get();

        return array_map(fn($result) => new $related($result), $results);
    }

    /**
     * Çoka-çok ilişki için birleştirme tablo adını getir
     */
    protected function joiningTable(string $related): string
    {
        $relatedInstance = new $related;
        $models = [
            strtolower((new ReflectionClass($this))->getShortName()),
            strtolower((new ReflectionClass($relatedInstance))->getShortName())
        ];
        
        sort($models);
        
        return implode('_', $models);
    }

    /**
     * Model için varsayılan foreign key adını getir
     */
    public function getForeignKey(): string
    {
        $className = (new ReflectionClass($this))->getShortName();
        return strtolower($className) . '_id';
    }

    /**
     * İlişkileri eager load et
     */
    public static function with(array $relations): QueryBuilder
    {
        return static::query()->with($relations);
    }

    /**
     * Model koleksiyonu için ilişkileri yükle
     */
    public static function loadRelations(array $models, array $relations): array
    {
        foreach ($relations as $relation) {
            $models = static::loadRelation($models, $relation);
        }

        return $models;
    }

    /**
     * Model koleksiyonu için belirli bir ilişkiyi yükle
     */
    protected static function loadRelation(array $models, string $relation): array
    {
        if (empty($models)) {
            return $models;
        }

        // Bu basitleştirilmiş bir eager loading implementasyonu
        // Gerçek bir ORM'de bu daha gelişmiş olurdu
        foreach ($models as $model) {
            if (method_exists($model, $relation)) {
                $model->relations[$relation] = $model->$relation();
            }
        }

        return $models;
    }

    /**
     * İlişki değerini getir
     */
    public function getRelation(string $relation)
    {
        if (array_key_exists($relation, $this->relations)) {
            return $this->relations[$relation];
        }

        if (method_exists($this, $relation)) {
            return $this->relations[$relation] = $this->$relation();
        }

        throw new RuntimeException("İlişki [{$relation}] model üzerinde bulunamadı [" . get_class($this) . "]");
    }

    /**
     * Modeli array'e çevir
     */
    public function toArray(): array
    {
        $array = $this->attributes;

        foreach ($this->relations as $key => $relation) {
            if (is_array($relation)) {
                $array[$key] = array_map(fn($model) => $model instanceof Model ? $model->toArray() : $model, $relation);
            } elseif ($relation instanceof Model) {
                $array[$key] = $relation->toArray();
            } else {
                $array[$key] = $relation;
            }
        }

        return $array;
    }

    /**
     * Modeli JSON'a çevir
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Model üzerindeki attribute'ları dinamik olarak getir
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Model üzerindeki attribute'ları dinamik olarak ayarla
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Model üzerinde bir attribute'un var olup olmadığını belirle
     */
    public function __isset(string $key): bool
    {
        return !is_null($this->getAttribute($key));
    }

    /**
     * Model üzerindeki bir attribute'u kaldır
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Dinamik statik method çağrılarını handle et
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Model içine dinamik method çağrılarını handle et
     */
    public function __call(string $method, array $parameters)
    {
        // İlişki metodu olup olmadığını kontrol et
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        // Query builder'a yönlendir
        return static::query()->$method(...$parameters);
    }
}
