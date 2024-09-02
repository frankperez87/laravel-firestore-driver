<?php

namespace LaravelFirestore\LaravelFirestoreDriver\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LaravelFirestore\LaravelFirestoreDriver\FirestoreQueryBuilder;

class FirestoreModel extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'firestore';

    /**
     * The primary key for the Firestore documents.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Get a new query builder instance for the model's table.
     *
     * @return \LaravelFirestore\LaravelFirestoreDriver\FirestoreQueryBuilder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new FirestoreQueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor()
        );
    }

    /**
     * Get the connection for the model.
     *
     * @return \LaravelFirestore\LaravelFirestoreDriver\FirestoreClientConnection
     */
    public function getConnection()
    {
        return static::resolveConnection('firestore');
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        $key = $this->getAttribute($this->getKeyName());

        // Debugging: log or print the key value
        logger()->info('Getting Key:', ['key' => $key]);

        return $key;
    }

    /**
     * Set the value of the model's primary key.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function setKey($value)
    {
        $this->setAttribute($this->getKeyName(), $value);

        return $this;
    }

    /**
     * Save the model to Firestore.
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            // Perform an update
            return $this->performUpdate($this->newQuery());
        } else {
            // Perform an insert
            return $this->performInsert($this->newQuery());
        }
    }

    /**
     * Perform a model insert operation.
     *
     * @return bool
     */
    protected function performInsert(EloquentBuilder $query)
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // Generate a new Firestore document ID if not already set
        if (! $this->{$this->getKeyName()}) {
            $this->{$this->getKeyName()} = $query->getModel()->getConnection()
                ->getClient()->collection($this->getTable())->newDocument()->id();
        }

        // Use Firestore's collection reference to insert the document
        $query->getModel()->getConnection()->table($this->getTable())
            ->insert($this->attributesToArray());

        $this->exists = true;

        $this->fireModelEvent('created', false);

        // Reset the model state after insertion
        $this->syncOriginal();

        return true;
    }

    /**
     * Perform a model update operation.
     *
     * @return bool
     */
    protected function performUpdate(EloquentBuilder $query)
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Get the dirty attributes
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // Update the document in Firestore
            $query->getModel()->getConnection()->table($this->getTable())
                ->where($this->getKeyName(), '=', $this->getKey())
                ->update($dirty);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $instance = new static;
        $collection = $instance->getConnection()->getClient()->collection($instance->getTable());

        // Try to find the document by ID
        $document = $collection->document($id)->snapshot();

        if ($document->exists()) {
            // Convert Firestore document to a model instance
            $data = $document->data();
            $data[$instance->getKeyName()] = $id; // Ensure the ID is set in the model data

            $model = $instance->newFromBuilder($data);
            $model->exists = true;

            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($instance), $id);
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param  array|int  $ids
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function newQueryForRestoration($ids)
    {
        return $this->newQueryWithoutScopes()->whereIn($this->getKeyName(), (array) $ids);
    }

    // /**
    //  * Override the default method to use Firestore document IDs.
    //  *
    //  * @return void
    //  */
    // public static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         if (! $model->getKey()) {
    //             $model->setKey($model->getConnection()->getClient()->collection($model->getTable())->newDocument()->id());
    //         }
    //     });
    // }
}
