<?php

namespace Contao\CoreBundle\Doctrine\Mapping;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Model;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
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
            /** @var Model $class */
            $class = Model::getClassFromTable($tableName);

            if (!class_exists($class) || !is_a($class, '\Contao\Model', true)) {
                continue;
            }

            $this->driver->setClassNameForTable($tableName, get_class(new $class));

            $this->tables[$tableName] = $schemaManager->listTableDetails($tableName);
        }

        $this->driver->setTables($this->tables, []);
    }
}
