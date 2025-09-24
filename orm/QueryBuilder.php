<?php

class QueryBuilder
{
    protected string $table;
    protected array $wheres = [];
    protected array $joins = [];
    protected array $orderBy = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $selectColumns = ['*'];
    protected array $bindings = [];
    protected array $with = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Seçilecek kolonları belirle
     */
    public function select(array $columns): self
    {
        $this->selectColumns = $columns;
        return $this;
    }

    /**
     * WHERE koşulu ekle
     */
    public function where(string $column, $operator = '=', $value = null): self
    {
        // where('kolon', 'değer') yapısını işle
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = $this->generatePlaceholder($column);
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $placeholder,
            'boolean' => 'and'
        ];

        $this->bindings[$placeholder] = $value;
        
        return $this;
    }

    /**
     * OR WHERE koşulu ekle
     */
    public function orWhere(string $column, string $operator = '=', $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = $this->generatePlaceholder($column);
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $placeholder,
            'boolean' => 'or'
        ];

        $this->bindings[$placeholder] = $value;
        
        return $this;
    }

    /**
     * WHERE IN koşulu ekle
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholder = $this->generatePlaceholder($column . '_' . $index);
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $placeholders,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * JOIN ekle
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'inner',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * LEFT JOIN ekle
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'left',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * ORDER BY ekle
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtolower($direction)
        ];

        return $this;
    }

    /**
     * LIMIT belirle
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * OFFSET belirle
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * İlişkileri önceden yükle
     */
    public function with(array $relations): self
    {
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    /**
     * Sorguyu çalıştır ve tüm sonuçları getir
     */
    public function get(): array
    {
        $sql = $this->toSql();
        $statement = Database::execute($sql, $this->bindings);
        return $statement->fetchAll();
    }

    /**
     * Sorguyu çalıştır ve ilk sonucu getir
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Kayıt sayısını say
     */
    public function count(): int
    {
        $originalSelect = $this->selectColumns;
        $this->selectColumns = ['COUNT(*) as count'];
        
        $sql = $this->toSql();
        $statement = Database::execute($sql, $this->bindings);
        $result = $statement->fetch();
        
        $this->selectColumns = $originalSelect;
        
        return (int) $result['count'];
    }

    /**
     * Herhangi bir kayıt var mı kontrol et
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Yeni kayıt ekle
     */
    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $bindings = [];
        foreach ($data as $column => $value) {
            $bindings[':' . $column] = $value;
        }

        $statement = Database::execute($sql, $bindings);
        $success = $statement->rowCount() > 0;
        
        // Insert başarılıysa lastInsertId'yi korumak için hiçbir şey yapmaya gerek yok
        // Database::lastInsertId() zaten PDO connection'dan alıyor
        
        return $success;
    }

    /**
     * Kayıtları güncelle
     */
    public function update(array $data): int
    {
        $setParts = [];
        $bindings = $this->bindings;

        foreach ($data as $column => $value) {
            $placeholder = ':update_' . $column;
            $setParts[] = $column . ' = ' . $placeholder;
            $bindings[$placeholder] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $setParts);
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $statement = Database::execute($sql, $bindings);
        return $statement->rowCount();
    }

    /**
     * Kayıtları sil
     */
    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $statement = Database::execute($sql, $this->bindings);
        return $statement->rowCount();
    }

    /**
     * Tam SQL sorgusunu oluştur
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selectColumns) . ' FROM ' . $this->table;

        // JOIN'leri ekle
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                strtoupper($join['type']),
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        // WHERE koşullarını ekle
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        // ORDER BY ekle
        if (!empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $order) {
                $orderParts[] = $order['column'] . ' ' . strtoupper($order['direction']);
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        // LIMIT ekle
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        // OFFSET ekle
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    /**
     * WHERE koşulu string'ini oluştur
     */
    protected function buildWhereClause(): string
    {
        $whereParts = [];
        
        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : strtoupper($where['boolean']) . ' ';
            
            if ($where['type'] === 'basic') {
                $whereParts[] = $boolean . $where['column'] . ' ' . $where['operator'] . ' ' . $where['value'];
            } elseif ($where['type'] === 'in') {
                $whereParts[] = $boolean . $where['column'] . ' IN (' . implode(', ', $where['values']) . ')';
            }
        }

        return implode(' ', $whereParts);
    }

    /**
     * Parametre bağlama için benzersiz placeholder oluştur
     */
    protected function generatePlaceholder(string $column): string
    {
        $base = ':' . str_replace('.', '_', $column);
        $counter = 1;
        $placeholder = $base;

        while (isset($this->bindings[$placeholder])) {
            $placeholder = $base . '_' . $counter;
            $counter++;
        }

        return $placeholder;
    }

    /**
     * Mevcut bağlamaları getir
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Tablo adını getir
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Önceden yüklenmiş ilişkileri getir
     */
    public function getWith(): array
    {
        return $this->with;
    }
}
