<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

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
        $this->firestoreQuery = $this->collectionReference;

        return $this;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        // Ensure that all where conditions are applied
        $query = $this->firestoreQuery;

        // Limit the query to 1 document for efficiency
        $documents = $query->limit(1)->documents();

        // Check if at least one document exists
        foreach ($documents as $document) {
            if ($document->exists()) {
                return true;
            }
        }

        return false;
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
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($operator === '=') {
            $operator = '==';
        }

        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '=='];
        }

        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '==');
        }

        // Add to the Firestore query and track the where clause
        $this->firestoreQuery = $this->firestoreQuery->where($column, $operator, $value);

        // Track the where clause
        $type = 'Basic';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if ($not) {
            throw new \InvalidArgumentException('Firestore does not support "where not in" queries.');
        }

        // Ensure Firestore supports the 'in' operator.
        if (empty($values)) {
            throw new \InvalidArgumentException('The values array for whereIn must not be empty.');
        }

        // Add 'in' clause to Firestore query
        $this->firestoreQuery = $this->firestoreQuery->where($column, 'in', $values);

        // Track the where clause
        $type = 'In';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        return $this;
    }

    /**
     * Add an array of "where" clauses to the query.
     *
     * @param  mixed  $column
     * @param  string  $boolean
     * @param  string  $method
     * @return $this
     */
    protected function addArrayOfWheres($column, $boolean, $method = 'where')
    {
        foreach ($column as $key => $value) {
            $this->{$method}($key, '=', $value, $boolean);
        }

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @return string The ID of the inserted document
     */
    public function insert(array $values)
    {
        // Check if an 'id' is set, otherwise generate a new one
        if (! isset($values['id'])) {
            // Generate a unique ID for the Firestore document
            $values['id'] = $this->collectionReference->newDocument()->id();
        }

        // Add the new document with the generated ID
        $this->collectionReference->document($values['id'])->set($values);

        // Return the ID of the inserted document
        return $values['id'];
    }

    /**
     * Insert new records into the Firestore database while ignoring errors.
     *
     * @return int Number of records successfully inserted.
     */
    public function insertOrIgnore(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        // Ensure $values is an array of arrays
        if (! is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $insertedCount = 0;

        foreach ($values as $value) {
            // Check if an 'id' is set, otherwise generate a new one
            if (! isset($value['id'])) {
                $value['id'] = $this->collectionReference->newDocument()->id();
            }

            // Check if the document already exists
            $document = $this->collectionReference->document($value['id'])->snapshot();
            if ($document->exists()) {
                // Skip this document as it already exists
                continue;
            }

            // Insert the document
            try {
                $this->collectionReference->document($value['id'])->set($value);
                $insertedCount++;
            } catch (\Exception $e) {
                // Handle any other errors gracefully (e.g., log the error, etc.)
                continue;
            }
        }

        return $insertedCount; // Return the count of successfully inserted documents
    }

    /**
     * Delete records from the Firestore database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers simply and quickly remove a single document from Firestore.
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        // Get the IDs from the where clause if set.
        $documentIds = $this->extractIdFromWhereClause();

        if (empty($documentIds)) {
            throw new \InvalidArgumentException('An ID or set of IDs is required to delete a document in Firestore.');
        }

        // Convert a single ID into an array if necessary
        if (! is_array($documentIds)) {
            $documentIds = [$documentIds];
        }

        $deletedCount = 0;

        // Delete each document by its ID
        foreach ($documentIds as $documentId) {
            $this->collectionReference->document($documentId)->delete();
            $deletedCount++;
        }

        // Return the count of deleted documents
        return $deletedCount;
    }

    public function update(array $values)
    {
        // Extract the IDs from the query conditions
        $ids = $this->extractIdFromWhereClause();

        // Ensure that we have IDs to update the document
        if (empty($ids)) {
            throw new \InvalidArgumentException('An ID or set of IDs is required to update documents in Firestore.');
        }

        // Convert a single ID into an array if necessary
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        $updatedCount = 0;

        // Update each document by its ID
        foreach ($ids as $id) {
            $documentReference = $this->collectionReference->document($id);

            // Update the document in Firestore
            $documentReference->set($values, ['merge' => true]);
            $updatedCount++;
        }

        return $updatedCount; // Return the count of successfully updated documents
    }

    /**
     * Extract the ID from the "where" clause of the query.
     *
     * @return string|null
     */
    protected function extractIdFromWhereClause()
    {
        $ids = [];

        foreach ($this->wheres as $where) {
            // Check for a "Basic" where clause with an "id" column and "==" operator.
            if ($where['type'] === 'Basic' && $where['column'] === 'id' && $where['operator'] === '==') {
                $ids[] = $where['value'];
            }

            // Check for an "In" where clause with a "key" column.
            if ($where['type'] === 'In' && $where['column'] === 'key') {
                if (isset($where['values']) && is_array($where['values'])) {
                    $ids = array_merge($ids, $where['values']); // Merge all values into the $ids array.
                }
            }
        }

        return ! empty($ids) ? $ids : null; // Return the array of IDs or null if no IDs are found.
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param  string  $boolean
     * @return $this
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        $query = $this->forNestedWhere();

        $callback($query);

        $type = 'Nested';

        $this->wheres[] = compact('type', 'query', 'boolean');

        // Merge Firestore query from the nested query builder.
        $this->firestoreQuery = $query->firestoreQuery;

        return $this;
    }

    /**
     * Create a new query instance for nesting.
     *
     * @return static
     */
    public function forNestedWhere()
    {
        return new static($this->getConnection());
    }

    /**
     * Check if the operator is invalid.
     *
     * @param  string  $operator
     * @return bool
     */
    protected function invalidOperator($operator)
    {
        $validOperators = ['<', '<=', '==', '>', '>=', 'array-contains', 'array-contains-any', 'in', 'not-in'];

        return ! in_array($operator, $validOperators, true);
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        $documents = $this->firestoreQuery->documents();
        $count = 0;

        foreach ($documents as $document) {
            if ($document->exists()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Paginate the given query.
     *
     * @param  int|\Closure  $perPage
     * @param  array|string  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @param  \Closure|int|null  $total
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        // Resolve total records count
        $total = value($total) ?? $this->getCountForPagination();

        // Resolve the per-page value
        $perPage = $perPage instanceof Closure ? $perPage($total) : $perPage;

        // Retrieve the last document ID from the request
        $lastDocumentId = request()->get('last_document_id');

        // Order by the field to maintain consistent pagination
        $this->firestoreQuery = $this->firestoreQuery->orderBy('id'); // Adjust 'id' to your primary sorting field

        // Apply the cursor if a last document ID is provided
        if ($lastDocumentId) {
            $lastDocumentSnapshot = $this->collectionReference->document($lastDocumentId)->snapshot();
            if ($lastDocumentSnapshot->exists()) {
                $this->firestoreQuery = $this->firestoreQuery->startAfter($lastDocumentSnapshot);
            }
        }

        // Apply the limit to fetch only the required number of documents
        $this->firestoreQuery = $this->firestoreQuery->limit($perPage);

        // Fetch the documents
        $documents = $this->firestoreQuery->documents();
        $results = [];
        $lastDocumentSnapshot = null;

        foreach ($documents as $document) {
            if ($document->exists()) {
                $results[] = $document->data();
                $lastDocumentSnapshot = $document; // Keep track of the last document
            }
        }

        // Determine the ID of the last document to be used for the next page cursor
        $lastDocumentId = $lastDocumentSnapshot ? $lastDocumentSnapshot->id() : null;

        // Return the paginator instance
        return new LengthAwarePaginator(
            collect($results),
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
                'last_document_id' => $lastDocumentId,
            ]
        );
    }

    /**
     * Set additional query constraints based on table variable paths.
     *
     * @return $this
     */
    public function setTableVariablePaths(array $variables)
    {
        foreach ($variables as $key => $value) {
            $this->firestoreQuery = $this->firestoreQuery->where($key, '==', $value);
        }

        return $this;
    }
}
