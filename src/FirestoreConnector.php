<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class FirestoreConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @return \LaravelFirestore\LaravelFirestoreDriver\FirestoreClientConnection
     */
    public function connect(array $config)
    {
        // Get the Firestore connection options from the configuration
        $options = $this->getOptions($config);

        // Create a Firestore client instance with the provided configuration
        $client = new FirestoreClient([
            'keyFilePath' => $config['credentials'] ?? null,
        ]);

        // Return a new FirestoreClientConnection with all required arguments
        return new FirestoreClientConnection($client, $config, $options);
    }
}
