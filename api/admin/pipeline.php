<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$candidateId = (int)($input['candidate_id'] ?? 0);
$candidateModel = new ContentCandidateModel();

try {
    switch ($action) {
        case 'ingest':
            $stats = ContentPipeline::runAutomatic(Auth::id());
            Helper::json([
                'success' => true,
                'message' => 'Pipeline run completed.',
                'stats' => $stats,
            ]);
            break;

        case 'generate_draft':
            if ($candidateId <= 0) {
                Helper::json(['error' => 'Candidate is required.'], 422);
            }

            $result = ContentPipeline::generateDraftFromCandidate($candidateId, Auth::id());
            Helper::json([
                'success' => true,
                'message' => $result['message'] ?? 'Draft generated.',
                'post_id' => $result['post_id'] ?? null,
                'edit_url' => $result['edit_url'] ?? null,
                'created' => $result['created'] ?? false,
            ]);
            break;

        case 'set_status':
            $status = trim((string)($input['status'] ?? ''));
            if ($candidateId <= 0) {
                Helper::json(['error' => 'Candidate is required.'], 422);
            }

            $candidateModel->setStatus($candidateId, $status);
            Helper::json([
                'success' => true,
                'message' => 'Candidate status updated.',
            ]);
            break;

        default:
            Helper::json(['error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    $status = 500;
    if ($e instanceof InvalidArgumentException) {
        $status = 422;
    } elseif (str_contains($e->getMessage(), 'not configured')) {
        $status = 503;
    }

    Helper::json(['error' => $e->getMessage()], $status);
}
