<?php

namespace Laminas\Db\Sql\Platform\Mysql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Constraint\ForeignKey;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\Index\AbstractIndex;
use Laminas\Db\Sql\Platform\PlatformDecoratorInterface;

use function count;
use function range;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr_replace;
use function uksort;

class AlterTableDecorator extends AlterTable implements PlatformDecoratorInterface
{
    /** @var AlterTable */
    protected $subject;

    /** @var int[] */
    protected $columnOptionSortOrder = [
        'unsigned'      => 0,
        'zerofill'      => 1,
        'identity'      => 2,
        'serial'        => 2,
        'autoincrement' => 2,
        'comment'       => 3,
        'columnformat'  => 4,
        'format'        => 4,
        'storage'       => 5,
        'after'         => 6,
    ];

    /**
     * @param AlterTable $subject
     * @return $this Provides a fluent interface
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        $this->subject->specifications[self::DROP_CONSTRAINTS] = [
            "%1\$s" => [
                [2 => "DROP %1\$s %2\$s,\n", 'combinedby' => " "],
            ]
        ];

        return $this;
    }

    /**
     * @param string $sql
     * @return array
     */
    protected function getSqlInsertOffsets($sql)
    {
        $sqlLength   = strlen($sql);
        $insertStart = [];

        foreach (['NOT NULL', 'NULL', 'DEFAULT', 'UNIQUE', 'PRIMARY', 'REFERENCES'] as $needle) {
            $insertPos = strpos($sql, ' ' . $needle);

            if ($insertPos !== false) {
                switch ($needle) {
                    case 'REFERENCES':
                        $insertStart[2] = ! isset($insertStart[2]) ? $insertPos : $insertStart[2];
                    // no break
                    case 'PRIMARY':
                    case 'UNIQUE':
                        $insertStart[1] = ! isset($insertStart[1]) ? $insertPos : $insertStart[1];
                    // no break
                    default:
                        $insertStart[0] = ! isset($insertStart[0]) ? $insertPos : $insertStart[0];
                }
            }
        }

        foreach (range(0, 3) as $i) {
            $insertStart[$i] = $insertStart[$i] ?? $sqlLength;
        }

        return $insertStart;
    }

    /**
     * @return array
     */
    protected function processAddColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];

        foreach ($this->addColumns as $i => $column) {
            $sql           = $this->processExpression($column, $adapterPlatform);
            $insertStart   = $this->getSqlInsertOffsets($sql);
            $columnOptions = $column->getOptions();

            uksort($columnOptions, [$this, 'compareColumnOptions']);

            foreach ($columnOptions as $coName => $coValue) {
                $insert = '';

                if (! $coValue) {
                    continue;
                }

                switch ($this->normalizeColumnOption($coName)) {
                    case 'unsigned':
                        $insert = ' UNSIGNED';
                        $j      = 0;
                        break;
                    case 'zerofill':
                        $insert = ' ZEROFILL';
                        $j      = 0;
                        break;
                    case 'identity':
                    case 'serial':
                    case 'autoincrement':
                        $insert = ' AUTO_INCREMENT';
                        $j      = 1;
                        break;
                    case 'comment':
                        $insert = ' COMMENT ' . $adapterPlatform->quoteValue($coValue);
                        $j      = 2;
                        break;
                    case 'columnformat':
                    case 'format':
                        $insert = ' COLUMN_FORMAT ' . strtoupper($coValue);
                        $j      = 2;
                        break;
                    case 'storage':
                        $insert = ' STORAGE ' . strtoupper($coValue);
                        $j      = 2;
                        break;
                    case 'after':
                        $insert = ' AFTER ' . $adapterPlatform->quoteIdentifier($coValue);
                        $j      = 2;
                }

                if ($insert) {
                    $j                = $j ?? 0;
                    $sql              = substr_replace($sql, $insert, $insertStart[$j], 0);
                    $insertStartCount = count($insertStart);
                    for (; $j < $insertStartCount; ++$j) {
                        $insertStart[$j] += strlen($insert);
                    }
                }
            }
            $sqls[$i] = $sql;
        }
        return [$sqls];
    }

    /**
     * @return array
     */
    protected function processChangeColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->changeColumns as $name => $column) {
            $sql           = $this->processExpression($column, $adapterPlatform);
            $insertStart   = $this->getSqlInsertOffsets($sql);
            $columnOptions = $column->getOptions();

            uksort($columnOptions, [$this, 'compareColumnOptions']);

            foreach ($columnOptions as $coName => $coValue) {
                $insert = '';

                if (! $coValue) {
                    continue;
                }

                switch ($this->normalizeColumnOption($coName)) {
                    case 'unsigned':
                        $insert = ' UNSIGNED';
                        $j      = 0;
                        break;
                    case 'zerofill':
                        $insert = ' ZEROFILL';
                        $j      = 0;
                        break;
                    case 'identity':
                    case 'serial':
                    case 'autoincrement':
                        $insert = ' AUTO_INCREMENT';
                        $j      = 1;
                        break;
                    case 'comment':
                        $insert = ' COMMENT ' . $adapterPlatform->quoteValue($coValue);
                        $j      = 2;
                        break;
                    case 'columnformat':
                    case 'format':
                        $insert = ' COLUMN_FORMAT ' . strtoupper($coValue);
                        $j      = 2;
                        break;
                    case 'storage':
                        $insert = ' STORAGE ' . strtoupper($coValue);
                        $j      = 2;
                        break;
                }

                if ($insert) {
                    $j                = $j ?? 0;
                    $sql              = substr_replace($sql, $insert, $insertStart[$j], 0);
                    $insertStartCount = count($insertStart);
                    for (; $j < $insertStartCount; ++$j) {
                        $insertStart[$j] += strlen($insert);
                    }
                }
            }
            $sqls[] = [
                $adapterPlatform->quoteIdentifier($name),
                $sql,
            ];
        }

        return [$sqls];
    }

    /**
     * @param string $name
     * @return string
     */
    private function normalizeColumnOption($name)
    {
        return strtolower(str_replace(['-', '_', ' '], '', $name));
    }

    /**
     * @param string $columnA
     * @param string $columnB
     * @return int
     */
    // phpcs:ignore SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod
    private function compareColumnOptions($columnA, $columnB)
    {
        $columnA = $this->normalizeColumnOption($columnA);
        $columnA = $this->columnOptionSortOrder[$columnA] ?? count($this->columnOptionSortOrder);

        $columnB = $this->normalizeColumnOption($columnB);
        $columnB = $this->columnOptionSortOrder[$columnB] ?? count($this->columnOptionSortOrder);

        return $columnA - $columnB;
    }

    protected function processDropConstraints(PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->dropConstraints as $constraint) {
            $sqls[] = [
                $this->getConstraintType($constraint),
                $adapterPlatform->quoteIdentifier($constraint->getName())
            ];
        }

        return [$sqls];
    }

    /**
     * @param $constraint
     * @return string
     */
    protected function getConstraintType($constraint)
    {
        if ($constraint instanceof ConstraintObject) {
            return $constraint->getType();
        }
        if ($constraint instanceof PrimaryKey) {
            return 'PRIMARY KEY';
        } elseif ($constraint instanceof ForeignKey) {
            return 'FOREIGN KEY';
        } elseif ($constraint instanceof AbstractIndex) {
            return 'INDEX';
        } else {
            return 'KEY';
        }
    }
}
