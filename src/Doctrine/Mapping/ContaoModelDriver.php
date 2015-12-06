<?php

namespace Contao\CoreBundle\Doctrine\Mapping;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;

/**
 * ContaoModelDriver registers Doctrine metadata for Contao models.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class ContaoModelDriver implements MappingDriver
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Table[]
     */
    private $tables;

    /**
     * @var DatabaseDriver
     */
    private $driver;

    /**
     * @var array
     */
    private $loaded = [];

    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     * @param Connection               $connection
     */
    public function __construct(ContaoFrameworkInterface $framework, Connection $connection)
    {
        $this->framework  = $framework;
        $this->connection = $connection;
    }


    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string                          $className
     * @param ClassMetadata|ClassMetadataInfo $metadata
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $this->registerDatabaseDriver();

        $this->driver->loadMetadataForClass($className, $metadata);

        $metadata->markReadOnly();

        $this->loadRelationsFromDca($metadata);

        $this->loaded[$className] = true;
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        $this->registerDatabaseDriver();

        return $this->driver->getAllClassNames();
    }

    /**
     * @inheritdoc
     */
    public function isTransient($className)
    {
        return !$this->loaded[$className] && is_a($className, '\Contao\Model', true);
    }

    /**
     * Registers tables for Contao models in a DatabaseDriver.
     * The DatabaseDriver is then used to load basic information from DB definition.
     */
    private function registerDatabaseDriver()
    {
        if (null !== $this->driver) {
            return;
        }

        $this->framework->initialize();

        $schemaManager = $this->connection->getSchemaManager();
        $this->tables  = [];

        $this->driver  = new DatabaseDriver($schemaManager);
        $this->driver->setNamespace('');

        foreach ($schemaManager->listTableNames() as $tableName) {
            $class = $this->getModelNameForTable($tableName);

            if (null === $class || !is_a($class, '\Contao\Model', true)) {
                continue;
            }

            $this->driver->setClassNameForTable($tableName, $class);

            $this->tables[$tableName] = $schemaManager->listTableDetails($tableName);

            foreach ($schemaManager->listTableColumns($tableName) as $column) {
                $name = $column->getName();
                $this->driver->setFieldNameForColumn($tableName, $name, $name);
            }
        }

        $this->driver->setTables($this->tables, []);
    }

    /**
     * @param ClassMetadataInfo $metadata
     */
    private function loadRelationsFromDca(ClassMetadataInfo $metadata)
    {
        $tableName = $metadata->getTableName();

        /**
         * @var \Contao\DcaExtractor $extractor
         * @var \Contao\DcaLoader    $loader
         */
        $extractor = $this->framework->getAdapter('Contao\DcaExtractor');
        $loader    = $this->framework->createInstance('Contao\DcaLoader', [$tableName]);

        $loader->load();

        $dca       = $GLOBALS['TL_DCA'][$tableName];
        $relations = $extractor->getInstance($tableName)->getRelations();

        if ($dca['config']['ptable']
            && !isset($relations['pid'])
            && ($targetEntity = $this->getModelNameForTable($dca['config']['ptable'])) !== null
        ) {
            // $tableName.pid has ManyToOne relation to $ptable.id

            $this->mapManyToOne(
                $metadata,
                'pid',
                'id',
                $dca['config']['ptable'],
                $targetEntity
            );

        } elseif (!$dca['config']['ptable']
            && !isset($relations['pid'])
            && $dca['config']['mode'] == 5
            && ($targetEntity = $this->getModelNameForTable($tableName)) !== null
        ) {
            // $tableName.pid has ManyToOne relation to $tableName.id

            $this->mapManyToOne(
                $metadata,
                'pid',
                'id',
                $tableName,
                $targetEntity
            );
        }

        if (is_array($dca['config']['ctable'])) {
            foreach ($dca['config']['ctable'] as $ctable) {
                // $tableName.id has OneToMany to $ctable.pid

                if (($targetEntity = $this->getModelNameForTable($ctable)) !== null) {
                    $this->mapOneToMany(
                        $metadata,
                        'pid',
                        $ctable,
                        $targetEntity
                    );
                }
            }
        }

        foreach ($relations as $field => $relation) {
            // $tableName.$field has $relation['type'] relation to $relation['table'].$relation['field']

            if (($relation['type'] == 'hasOne' || $relation['type'] == 'belongsTo')
                && ($targetEntity = $this->getModelNameForTable($relation['table'])) !== null
            ) {
                $this->mapManyToOne(
                    $metadata,
                    $field,
                    $relation['field'],
                    $relation['table'],
                    $targetEntity
                );
            }
        }

        if (is_array($dca['fields'])) {
            foreach ($dca['fields'] as $field => $config) {
                if (Type::BLOB === $metadata->fieldMappings[$field]['type']) {
                    if ($config['eval']['multiple'] && !isset($config['eval']['csv'])) {
                        $metadata->fieldMappings[$field]['type'] = Type::TARRAY;
                    } elseif ($config['eval']['multiple'] && ',' === $config['eval']['csv']) {
                        $metadata->fieldMappings[$field]['type'] = Type::SIMPLE_ARRAY;
                    } else {
                        $metadata->fieldMappings[$field]['type'] = Type::TEXT;
                    }
                }

                if ('fileTree' === $config['inputType']) {
                    $metadata->fieldMappings[$field]['type'] = ($config['eval']['multiple'] ? 'uuid_array' : 'uuid');
                    $column = $this->tables[$tableName]->getColumn($field);

                    $fieldMapping = array(
                        'columnName' => $column->getName(),
                        'nullable'   => (!$column->getNotNull()),
                        'length'     => $column->getLength(),
                        'options'    => ['fixed' => $column->getFixed()],
                    );

                    // Comment
                    if (($comment = $column->getComment()) !== null) {
                        $fieldMapping['options']['comment'] = $comment;
                    }

                    // Default
                    if (($default = $column->getDefault()) !== null) {
                        $fieldMapping['options']['default'] = $default;
                    }

                    $metadata->setAttributeOverride($field, $fieldMapping);
                }
            }
        }
    }

    /**
     * Returns the FQCN of the Contao model for a table name or null if not found.
     *
     * @param string $tableName
     *
     * @return null|string
     */
    private function getModelNameForTable($tableName)
    {
        /** @var \Contao\Model $adapter */
        $adapter = $this->framework->getAdapter('Contao\Model');
        $class   = $adapter->getClassFromTable($tableName, true);

        if (!class_exists($class)) {
            return null;
        }

        return $class;
    }

    /**
     * @param ClassMetadataInfo $metadata
     * @param string            $targetColumn
     * @param string            $targetTable
     * @param string            $targetEntity
     */
    private function mapOneToMany(
        ClassMetadataInfo $metadata,
        $targetColumn,
        $targetTable,
        $targetEntity
    ) {
        $metadata->mapOneToMany([
            'fieldName'     => "relation(table=$targetTable)",
            'mappedBy'      => "relation(field=$targetColumn)",
            'targetEntity'  => $targetEntity,
            'cascade'       => [],
            'indexBy'       => null,
            'orphanRemoval' => false,
            'fetch'         => ClassMetadataInfo::FETCH_LAZY,
        ]);
    }

    /**
     * @param ClassMetadataInfo $metadata
     * @param string            $field
     * @param string            $targetColumn
     * @param string            $targetTable
     * @param string            $targetEntity
     */
    private function mapManyToOne(
        ClassMetadataInfo $metadata,
        $field,
        $targetColumn,
        $targetTable,
        $targetEntity
    ) {
        $metadata->mapManyToOne([
            'fieldName'    => "relation(field=$field)",
            'joinColumns'  => [
                [
                    'name'                 => $field,
                    'unique'               => false,
                    'nullable'             => false,
                    'onDelete'             => null,
                    'columnDefinition'     => null,
                    'referencedColumnName' => $targetColumn,
                ]
            ],
            'cascade'      => [],
            'inversedBy'   => '', //"relation(table=$targetTable)",
            'targetEntity' => $targetEntity,
            'fetch'        => ClassMetadataInfo::FETCH_LAZY
        ]);
    }
}