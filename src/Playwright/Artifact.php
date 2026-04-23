<?php

declare(strict_types=1);

namespace Pest\Browser\Playwright;

use InvalidArgumentException;
use RuntimeException;

/**
 * @internal
 */
final readonly class Artifact
{
    use Concerns\InteractsWithPlaywright;

    /**
     * Chunk size per stream read, matching the playwright-python binding.
     */
    private const int STREAM_CHUNK_SIZE = 1048576;

    /**
     * Creates a new context instance.
     */
    public function __construct(
        private string $guid
    ) {
        //
    }

    /**
     * Save artifact to a local path.
     *
     * Uses saveAsStream rather than saveAs so the artifact is transferred
     * over the wire and written client-side. The plain saveAs writes on the
     * Playwright server's filesystem, which fails when the server is remote.
     *
     * @param  array{path?: string}  $params
     */
    public function saveAs(array $params = []): void
    {
        if (! isset($params['path']) || ! is_string($params['path'])) {
            throw new InvalidArgumentException('Artifact::saveAs requires a string "path" parameter.');
        }

        $path = $params['path'];

        $directory = dirname($path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $streamGuid = null;

        /** @var array{result?: array{stream?: array{guid?: string}}} $message */
        foreach (Client::instance()->execute($this->guid, 'saveAsStream') as $message) {
            if (isset($message['result']['stream']['guid'])) {
                $streamGuid = $message['result']['stream']['guid'];
            }
        }

        if ($streamGuid === null) {
            throw new RuntimeException('saveAsStream did not return a stream.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open '{$path}' for writing.");
        }

        try {
            while (true) {
                $response = Client::instance()->execute($streamGuid, 'read', ['size' => self::STREAM_CHUNK_SIZE]);
                $chunk = $this->processBinaryResponse($response);

                if ($chunk === '') {
                    break;
                }

                $decoded = base64_decode($chunk, true);

                if ($decoded === false) {
                    throw new RuntimeException('Stream returned invalid base64 data.');
                }

                fwrite($handle, $decoded);
            }
        } finally {
            fclose($handle);
        }
    }
}
