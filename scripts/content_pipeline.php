<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$command = strtolower(trim((string)($argv[1] ?? 'ingest')));

try {
    switch ($command) {
        case 'ingest':
            $result = ContentPipeline::ingestFeeds();
            break;

        case 'run':
        case 'auto':
            $authorId = (int)($argv[2] ?? 0);
            $result = ContentPipeline::runAutomatic($authorId);
            break;

        case 'draft':
            $candidateId = (int)($argv[2] ?? 0);
            $authorId = (int)($argv[3] ?? 0);
            if ($candidateId <= 0) {
                throw new InvalidArgumentException('Usage: php scripts/content_pipeline.php draft <candidateId> [authorId]');
            }

            $result = ContentPipeline::generateDraftFromCandidate($candidateId, $authorId);
            break;

        default:
            throw new InvalidArgumentException('Usage: php scripts/content_pipeline.php [ingest|run|auto [authorId]|draft <candidateId> [authorId]]');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
