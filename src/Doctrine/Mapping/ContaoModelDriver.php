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
     * @param string        $className
     * @param ClassMetadata $metadata
     *
     * @return void
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $this->loadMappingFromDatabase();

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
        $this->loadMappingFromDatabase();

        return $this->driver->getAllClassNames();
    }

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
     *
     * @param string $className
     *
     * @return boolean
     */
    public function isTransient($className)
    {
        return true;
    }


    private function loadMappingFromDatabase()
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

    private function loadRelationsFromDca(ClassMetadataInfo $metadata)
    {
        $tableName = $metadata->getTableName();
        $dca       = $GLOBALS['TL_DCA'][$tableName];
        $relations = DcaExtractor::getInstance($tableName)->getRelations();

        if ($dca['config']['ptable']
            && !isset($relations['pid'])
            && ($targetEntity = $this->getModelNameForTable($dca['config']['ptable'])) !== null
        ) {
            // $tableName.pid hat ManyToOne relation auf $ptable.id

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
                // $tableName.id hat OneToMany auf $ctable.pid

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
            // $tableName.$field has $relation['type'] relation to $relation['table'][$relation['field']]

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
