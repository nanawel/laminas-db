<?php

namespace Laminas\Db\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\TableIdentifier;

use function array_key_exists;

class AlterTable extends AbstractSql implements SqlInterface
{
    public const ADD_COLUMNS      = 'addColumns';
    public const ADD_CONSTRAINTS  = 'addConstraints';
    public const CHANGE_COLUMNS   = 'changeColumns';
    public const DROP_COLUMNS     = 'dropColumns';
    public const DROP_CONSTRAINTS = 'dropConstraints';
    public const DROP_INDEXES     = 'dropIndexes';
    public const TABLE            = 'table';

    /** @var array */
    protected $addColumns = [];

    /** @var Constraint\ConstraintInterface[] */
    protected $addConstraints = [];

    /** @var array */
    protected $changeColumns = [];

    /** @var array */
    protected $dropColumns = [];

    /** @var Constraint\ConstraintInterface[]|ConstraintObject[] */
    protected $dropConstraints = [];

    /** @var array */
    protected $dropIndexes = [];

    /**
     * Specifications for Sql String generation
     *
     * @var array
     */
    protected $specifications = [
        self::TABLE            => "ALTER TABLE %1\$s\n",
        self::ADD_COLUMNS      => [
            "%1\$s" => [
                [1 => "ADD COLUMN %1\$s,\n", 'combinedby' => ""],
            ],
        ],
        self::CHANGE_COLUMNS   => [
            "%1\$s" => [
                [2 => "CHANGE COLUMN %1\$s %2\$s,\n", 'combinedby' => ""],
            ],
        ],
        self::DROP_COLUMNS     => [
            "%1\$s" => [
                [1 => "DROP COLUMN %1\$s,\n", 'combinedby' => ""],
            ],
        ],
        self::ADD_CONSTRAINTS  => [
            "%1\$s" => [
                [1 => "ADD %1\$s,\n", 'combinedby' => ""],
            ],
        ],
        self::DROP_CONSTRAINTS => [
            "%1\$s" => [
                [1 => "DROP CONSTRAINT %1\$s,\n", 'combinedby' => "\n "],
            ],
        ],
        self::DROP_INDEXES     => [
            '%1$s' => [
                [1 => "DROP INDEX %1\$s,\n", 'combinedby' => ''],
            ],
        ],
    ];

    /** @var string */
    protected $table = '';

    /**
     * @param string|TableIdentifier $table
     */
    public function __construct($table = '')
    {
        $table ? $this->setTable($table) : null;
    }

    /**
     * @param  string $name
     * @return $this Provides a fluent interface
     */
    public function setTable($name)
    {
        $this->table = $name;

        return $this;
    }

    /**
     * @return $this Provides a fluent interface
     */
    public function addColumn(Column\ColumnInterface $column)
    {
        $this->addColumns[] = $column;

        return $this;
    }

    /**
     * @param  string $name
     * @return $this Provides a fluent interface
     */
    public function changeColumn($name, Column\ColumnInterface $column)
    {
        $this->changeColumns[$name] = $column;

        return $this;
    }

    /**
     * @param  string $name
     * @return $this Provides a fluent interface
     */
    public function dropColumn($name)
    {
        $this->dropColumns[] = $name;

        return $this;
    }

    /**
     * @param  Constraint\ConstraintInterface|ConstraintObject $constraint
     * @return $this Provides a fluent interface
     */
    public function dropConstraint($constraint)
    {
        $this->dropConstraints[] = $constraint;

        return $this;
    }

    /**
     * @return $this Provides a fluent interface
     */
    public function addConstraint(Constraint\ConstraintInterface $constraint)
    {
        $this->addConstraints[] = $constraint;

        return $this;
    }

    /**
     * @param  string $name
     * @return self Provides a fluent interface
     */
    public function dropIndex($name)
    {
        $this->dropIndexes[] = $name;

        return $this;
    }

    /**
     * @param  string|null $key
     * @return array
     */
    public function getRawState($key = null)
    {
        $rawState = [
            self::TABLE            => $this->table,
            self::ADD_COLUMNS      => $this->addColumns,
            self::DROP_COLUMNS     => $this->dropColumns,
            self::CHANGE_COLUMNS   => $this->changeColumns,
            self::ADD_CONSTRAINTS  => $this->addConstraints,
            self::DROP_CONSTRAINTS => $this->dropConstraints,
            self::DROP_INDEXES     => $this->dropIndexes,
        ];

        return isset($key) && array_key_exists($key, $rawState) ? $rawState[$key] : $rawState;
    }

    /** @return string[] */
    protected function processTable(?PlatformInterface $adapterPlatform = null)
    {
        return [$this->resolveTable($this->table, $adapterPlatform)];
    }

    /** @return string[] */
    protected function processAddColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->addColumns as $column) {
            $sqls[] = $this->processExpression($column, $adapterPlatform);
        }

        return [$sqls];
    }

    /** @return string[] */
    protected function processChangeColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->changeColumns as $name => $column) {
            $sqls[] = [
                $adapterPlatform->quoteIdentifier($name),
                $this->processExpression($column, $adapterPlatform),
            ];
        }

        return [$sqls];
    }

    /** @return string[] */
    protected function processDropColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->dropColumns as $column) {
            $sqls[] = $adapterPlatform->quoteIdentifier($column);
        }

        return [$sqls];
    }

    /** @return string[] */
    protected function processAddConstraints(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->addConstraints as $constraint) {
            $sqls[] = $this->processExpression($constraint, $adapterPlatform);
        }

        return [$sqls];
    }

    /** @return string[] */
    protected function processDropConstraints(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->dropConstraints as $constraint) {
            $sqls[] = $adapterPlatform->quoteIdentifier($constraint->getName());
        }

        return [$sqls];
    }

    /** @return string[] */
    protected function processDropIndexes(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->dropIndexes as $index) {
            $sqls[] = $adapterPlatform->quoteIdentifier($index);
        }

        return [$sqls];
    }
}
