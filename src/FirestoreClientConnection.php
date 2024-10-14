<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Database\Connection;

class FirestoreClientConnection extends Connection
{
    /**
     * The Firestore client instance.
     *
     * @var \Google\Cloud\Firestore\FirestoreClient
     */
    protected $client;

    /**
     * The Firestore transaction instance.
     *
     * @var \Google\Cloud\Firestore\Transaction|null
     */
    protected $transaction;

    /**
     * Create a new Firestore connection instance.
     */
    public function __construct(FirestoreClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;

        // Set the default database and table prefix
        $database = $config['database'] ?? '(default)';
        $tablePrefix = $config['prefix'] ?? '';

        // Call the parent constructor with the correct arguments
        parent::__construct(null, $database, $tablePrefix, $config);

        // Initialize the query grammar and post processor
        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Get the Firestore client instance.
     *
     * @return \Google\Cloud\Firestore\FirestoreClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @param  string|null  $as
     * @return \LaravelFirestore\LaravelFirestoreDriver\FirestoreQueryBuilder
     */
    public function table($table, $as = null)
    {
        $query = new FirestoreQueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );

        return $query->from($table, $as);
    }

    /**
     * Begin a transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->transaction = $this->client->runTransaction(function ($transaction) {
            return $transaction;
        });
    }

    /**
     * Commit the transaction.
     *
     * @return void
     */
    public function commit()
    {
        // No explicit commit is needed for Firestore transactions.
        // The transaction is committed automatically when the transaction closure completes.
        if ($this->transaction) {
            $this->transaction = null; // Clear the transaction to indicate it's finished.
        }
    }

    /**
     * Rollback the transaction.
     *
     * @param  int|null  $toLevel
     * @return void
     */
    public function rollBack($toLevel = null)
    {
        // Firestore transactions do not have an explicit rollback.
        // Transactions are simply not committed if there is an error.
        if ($this->transaction) {
            // Just clear the transaction to indicate it's no longer in progress.
            $this->transaction = null;
        }
    }

    /**
     * Get the Firestore transaction instance.
     *
     * @return \Google\Cloud\Firestore\Transaction|null
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Determine if the current connection is in a transaction.
     *
     * @return bool
     */
    public function inTransaction()
    {
        return ! is_null($this->transaction);
    }
}
