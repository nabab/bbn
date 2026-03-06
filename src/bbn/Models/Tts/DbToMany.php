<?php
namespace bbn\Entities\Models;

use bbn\X;
use Exception;

/**
 * Trait to handle many-to-many relationships for entity caching.
 */
trait DbToMany                                                                 
{
    /**
     * @var array Cache of relationship information between tables
     */
    protected static $manyToManyRelations = [];

    /**
     * Registers a many-to-many relationship between two tables.
     *
     * @param string $table1 First table name
     * @param string $field1 Field in first table that references the junction table
     * @param string $table2 Second table name
     * @param string $field2 Field in second table that references the junction table
     * @param string $junctionTable Junction table name
     */
    public static function registerManyToManyRelation(
        string $table1,
        string $field1,
        string $table2,
        string $field2,
        string $junctionTable
    ): void {
        self::$manyToManyRelations[$table1][$table2] = [
            'field' => $field1,
            'junction_table' => $junctionTable,
            'other_field' => $field2
        ];
        self::$manyToManyRelations[$table2][$table1] = [
            'field' => $field2,
            'junction_table' => $junctionTable,
            'other_field' => $field1
        ];
    }

    /**
     * Gets all many-to-many relationships for a given table.
     *
     * @param string $table Table name
     * @return array Relationship information
     */
    protected function getManyToManyRelations(string $table): array
    {
        return self::$manyToManyRelations[$table] ?? [];
    }

    /**
     * Handles cache invalidation for many-to-many relationships.
     *
     * @param string $table Table name where change occurred
     * @param mixed $id Id of the changed record
     */
    protected function invalidateManyToManyCache(string $table, $id): void
    {
        $relations = $this->getManyToManyRelations($table);

        foreach ($relations as $relatedTable => $relation) {
            // Get all junction records for this ID
            $junctionRecords = $this->db->selectAll(
                $relation['junction_table'],
                [$relation['field'] => $id]
            );

            if (!empty($junctionRecords)) {
                foreach ($junctionRecords as $record) {
                    // Invalidate cache for each related record
                    $relatedId = $record->{$relation['other_field']};
                    $this->invalidateEntityCacheForTable($relatedTable, $relatedId);
                }
            }

            // Also invalidate the junction table's cache if it has an id_entity column
            if (isset($this->class_cfg['arch'][$relation['junction_table']]['id_entity'])) {
                $this->invalidateEntityCacheForTable(
                    $relation['junction_table'],
                    $this->id_entity,
                    true // Force full invalidation of the junction table's cache
                );
            }
        }
    }

    /**
     * Invalidate entity cache for a specific table and ID.
     *
     * @param string $table Table name
     * @param mixed $entityId Entity ID
     * @param bool $force Whether to force invalidation of all related records
     */
    protected function invalidateEntityCacheForTable(string $table, $entityId, bool $force = false): void
    {
        // Get the model class for this table
        $modelClass = $this->entities->getModelClass($table);

        if ($modelClass) {
            /** @var EntityTable $model */
            $model = new $modelClass($this->db, $this->entities, $this->entity);

            // Invalidate cache for the specific record
            $cacheKey = DbCache::getRowCacheKey($table, $entityId);
            $this->getDbCacheManager()->delete($cacheKey);

            if ($force) {
                // Force invalidation of all records in this table for this entity
                $ids = $model->dbTraitGetIds([$model->fields['id_entity'] => $this->id_entity]);
                foreach ($ids as $id) {
                    $this->getDbCacheManager()->delete(DbCache::getRowCacheKey($table, $id));
                }
            }

            // Invalidate the IDs cache for this table
            $this->getDbCacheManager()->deleteAll(
                'table/' . $table . '/ids/'
            );
        }
    }
}
