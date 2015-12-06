<?php

namespace Contao\CoreBundle\Doctrine\Mapping;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\DcaExtractor;
use Contao\DcaLoader;
use Contao\Model;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
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
        return true;
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
        }

        $this->driver->setTables($this->tables, []);
    }

    /**
     * @param ClassMetadataInfo $metadata
     */
    private function loadRelationsFromDca(ClassMetadataInfo $metadata)
    {
        $tableName = $metadata->getTableName();
        $dca       = $GLOBALS['TL_DCA'][$tableName];
        $relations = DcaExtractor::getInstance($tableName)->getRelations();

        if ($dca['config']['ptable']
            && !isset($relations['pid'])
            && ($targetEntity = $this->getModelNameForTable($dca['config']['ptable'])) !== null
        ) {
            // $tableName.pid has ManyToOne relation to $ptable.id

            $metadata->mapManyToOne([
                'fieldName'    => 'relation(ptable)',
                'joinColumns'  => [[
                    'name'                 => 'pid',
                    'unique'               => false,
                    'nullable'             => false,
                    'onDelete'             => null,
                    'columnDefinition'     => null,
                    'referencedColumnName' => 'id',
                ]],
                'cascade'      => [],
                'inversedBy'   => $dca['config']['ptable'],
                'targetEntity' => $targetEntity,
                'fetch'        => ClassMetadataInfo::FETCH_LAZY
            ]);
        }

        if (is_array($dca['config']['ctable'])) {
            foreach ($dca['config']['ctable'] as $ctable) {
                // $tableName.id has OneToMany to $ctable.pid

                if (($targetEntity = $this->getModelNameForTable($ctable)) !== null) {
                    $metadata->mapOneToMany([
                        'fieldName'     => "relation(ctable=$ctable)",
                        'mappedBy'      => 'pid',
                        'targetEntity'  => $targetEntity,
                        'cascade'       => [],
                        'indexBy'       => null,
                        'orphanRemoval' => false,
                        'fetch'         => ClassMetadataInfo::FETCH_LAZY,
                    ]);
                }
            }
        }

        foreach ($relations as $field => $relation) {
            // $tableName.$field has $relation['type'] relation to $relation['table'].$relation['field']

            if (($relation['type'] == 'hasOne' || $relation['type'] == 'belongsTo')
                && ($targetEntity = $this->getModelNameForTable($relation['table'])) !== null
            ) {
                $metadata->mapManyToOne([
                    'fieldName'    => "relation(field=$field)",
                    'joinColumns'  => [[
                        'name'                 => $field,
                        'unique'               => false,
                        'nullable'             => false,
                        'onDelete'             => null,
                        'columnDefinition'     => null,
                        'referencedColumnName' => $relation['field'],
                    ]],
                    'cascade'      => [],
                    'inversedBy'   => $relation['table'],
                    'targetEntity' => $targetEntity,
                    'fetch'        => ClassMetadataInfo::FETCH_LAZY
                ]);
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
}
