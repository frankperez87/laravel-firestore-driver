<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Illuminate\Database\Query\Builder;

class FirestoreQueryBuilder extends Builder
{
    protected $client;

    protected $collectionReference;

    protected $firestoreQuery;

    /**
     * Create a new Firestore query builder instance.
     *
     * @param  mixed  $grammar
     * @param  mixed  $processor
     */
    public function __construct(FirestoreClientConnection $connection, $grammar = null, $processor = null)
    {
        parent::__construct($connection, $grammar, $processor);
        $this->client = $connection->getClient();
    }

    /**
     * Set the collection name for the query.
     *
     * @param  string  $collection
     * @param  string|null  $as
     * @return $this
     */
    public function from($collection, $as = null)
    {
        $this->from = $collection;
        $this->collectionReference = $this->client->collection($collection);
        $this->firestoreQuery = $this->collectionReference; // Initialize Firestore query object

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $documents = $this->firestoreQuery->documents();
        $results = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $results[] = $document->data();
            }
        }

        return collect($results);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($operator === null) {
            $operator = '=';
        }

        // Firestore uses '==' instead of '=' for equality
        if ($operator === '=') {
            $operator = '==';
        }

        $this->firestoreQuery = $this->firestoreQuery->where($column, $operator, $value);

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Add the new document to the collection
        $this->collectionReference->add($values);

        return true;
    }
}
