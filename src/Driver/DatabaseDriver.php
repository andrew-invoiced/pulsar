<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar\Driver;

use JAQB\ConnectionManager;
use JAQB\Exception\JAQBException;
use JAQB\QueryBuilder;
use PDOException;
use PDOStatement;
use Pulsar\Exception\DriverException;
use Pulsar\Model;
use Pulsar\Query;

/**
 * Driver for storing models in a database using PDO.
 */
class DatabaseDriver extends AbstractDriver
{
    /**
     * @var ConnectionManager
     */
    private $connections;

    /**
     * @var QueryBuilder
     */
    private $connection;

    /**
     * Sets the connection manager.
     *
     * @param ConnectionManager $manager
     *
     * @return $this
     */
    public function setConnectionManager(ConnectionManager $manager)
    {
        $this->connections = $manager;

        return $this;
    }

    /**
     * Gets the connection manager.
     *
     * @return ConnectionManager
     */
    public function getConnectionManager()
    {
        return $this->connections;
    }

    /**
     * Sets the default database connection.
     *
     * @param QueryBuilder $db
     *
     * @return $this
     */
    public function setConnection(QueryBuilder $db)
    {
        $this->connection = $db;

        return $this;
    }

    /**
     * Gets the database connection.
     *
     * @param string|false $id connection ID
     *
     * @throws DriverException when the connection has not been set yet
     *
     * @return QueryBuilder
     */
    public function getConnection($id)
    {
        if ($this->connections) {
            try {
                if ($id) {
                    return $this->connections->get($id);
                } else {
                    return $this->connections->getDefault();
                }
            } catch (PDOException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            } catch (JAQBException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!$this->connection) {
            throw new DriverException('The database driver has not been given a connection!');
        }

        return $this->connection;
    }

    public function createModel(Model $model, array $parameters)
    {
        $values = $this->serialize($parameters);
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        try {
            return $db->insert($values)
                    ->into($tablename)
                    ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when creating the '.$model::modelName().': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function getCreatedID(Model $model, $propertyName)
    {
        try {
            $id = $this->getConnection($model->getConnection())->lastInsertId();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when getting the ID of the new '.$model::modelName().': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }

        return Model::cast($model::getProperty($propertyName), $id);
    }

    public function loadModel(Model $model)
    {
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        try {
            $row = $db->select('*')
                ->from($tablename)
                ->where($model->ids())
                ->one();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when loading an instance of '.$model::modelName().': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }

        if (!is_array($row)) {
            return false;
        }

        return $row;
    }

    public function updateModel(Model $model, array $parameters)
    {
        if (0 == count($parameters)) {
            return true;
        }

        $values = $this->serialize($parameters);
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        try {
            return $db->update($tablename)
                    ->values($values)
                    ->where($model->ids())
                    ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when updating the '.$model::modelName().': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function deleteModel(Model $model)
    {
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        try {
            return $db->delete($tablename)
                    ->where($model->ids())
                    ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while deleting the '.$model::modelName().': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function queryModels(Query $query)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();

        // build a DB query from the model query
        $dbQuery = $this->getConnection($model->getConnection())
            ->select($this->prefixSelect('*', $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename))
            ->limit($query->getLimit(), $query->getStart())
            ->orderBy($this->prefixSort($query->getSort(), $tablename));

        // join conditions
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return $dbQuery->all();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while performing the '.$model::modelName().' query: '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function count(Query $query)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        $dbQuery = $db->select()
            ->count()
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename));
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return (int) $dbQuery->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the number of '.$model::modelName().' objects: '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function sum(Query $query, $field)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        $dbQuery = $db->select()
            ->sum($this->prefixColumn($field, $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename));
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return (int) $dbQuery->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the sum of '.$model::modelName().' '.$field.': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function average(Query $query, $field)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        $dbQuery = $db->select()
            ->average($this->prefixColumn($field, $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename));
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return (int) $dbQuery->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the average of '.$model::modelName().' '.$field.': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function max(Query $query, $field)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        $dbQuery = $db->select()
            ->max($this->prefixColumn($field, $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename));
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return (int) $dbQuery->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the max of '.$model::modelName().' '.$field.': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }

    public function min(Query $query, $field)
    {
        $modelClass = $query->getModel();
        $model = new $modelClass();
        $tablename = $model->getTablename();
        $db = $this->getConnection($model->getConnection());

        $dbQuery = $db->select()
            ->min($this->prefixColumn($field, $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename));
        $this->addJoins($query, $tablename, $dbQuery);

        try {
            return (int) $dbQuery->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the min of '.$model::modelName().' '.$field.': '.$original->getMessage());
            $e->setException($original);
            throw $e;
        }
    }
}
