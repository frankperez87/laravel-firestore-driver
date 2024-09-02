<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Database\Connection;

class FirestoreClientConnection extends Connection
{
    protected $client;

    protected $transaction;

    public function __construct(FirestoreClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;

        parent::__construct(null, $config['database'] ?? '(default)', '', $config);

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function table($table, $as = null)
    {
        $query = new FirestoreQueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );

        return $query->from($table, $as);
    }

    public function beginTransaction()
    {
        $this->transaction = $this->client->runTransaction(function ($transaction) {
            return $transaction;
        });
    }

    public function commit()
    {
        if ($this->transaction) {
            $this->transaction->commit();
            $this->transaction = null;
        }
    }

    public function rollBack($toLevel = null)
    {
        if ($this->transaction) {
            $this->transaction->rollback();
            $this->transaction = null;
        }
    }
}
