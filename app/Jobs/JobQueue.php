<?php

namespace App\Jobs;

use PDO;
use Redis;
use Throwable;

class JobQueue
{
    private PDO $db;
    private ?Redis $redis;

    public function __construct(PDO $db, ?Redis $redis = null)
    {
        $this->db = $db;
        $this->redis = $redis;
    }

    public function push(string $queue, array $payload, ?string $idempotencyKey = null, int $maxAttempts = 5): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO jobs_queue (queue_name, idempotency_key, payload_json, status, attempts, max_attempts, available_at, created_at)
             VALUES (?, ?, ?, ?, 0, ?, NOW(), NOW())'
        );

        try {
            $stmt->execute([
                $queue,
                $idempotencyKey,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                'PENDING',
                max(1, $maxAttempts),
            ]);

            $jobId = (int) $this->db->lastInsertId();
            if ($this->redis !== null) {
                $this->redis->rpush('jobs:signal:' . $queue, (string) $jobId);
            }
            return $jobId;
        } catch (Throwable $e) {
            if ($idempotencyKey === null) {
                throw $e;
            }

            $lookup = $this->db->prepare(
                'SELECT id FROM jobs_queue WHERE queue_name = ? AND idempotency_key = ? LIMIT 1'
            );
            $lookup->execute([$queue, $idempotencyKey]);
            $row = $lookup->fetch();
            if (!$row) {
                throw $e;
            }

            return (int) $row['id'];
        }
    }

    public function pop(string $queue): ?array
    {
        $job = $this->popJob($queue);
        if ($job === null) {
            return null;
        }

        return $job['payload'];
    }

    public function popJob(string $queue): ?array
    {
        $this->recoverStaleJobs($queue);

        $candidateStmt = $this->db->prepare(
            "SELECT id, payload_json, attempts, max_attempts
             FROM jobs_queue
             WHERE queue_name = ?
               AND status IN ('PENDING', 'RETRY')
               AND available_at <= NOW()
             ORDER BY available_at ASC, id ASC
             LIMIT 1"
        );
        $candidateStmt->execute([$queue]);
        $candidate = $candidateStmt->fetch();

        if (!$candidate) {
            return null;
        }

        $claimStmt = $this->db->prepare(
            "UPDATE jobs_queue
             SET status = 'PROCESSING',
                 reserved_at = NOW(),
                 attempts = attempts + 1,
                 updated_at = NOW()
             WHERE id = ?
               AND status IN ('PENDING', 'RETRY')"
        );
        $claimStmt->execute([(int) $candidate['id']]);
        if ($claimStmt->rowCount() === 0) {
            return null;
        }

        $payload = json_decode((string) $candidate['payload_json'], true);
        if (!is_array($payload)) {
            $this->fail((int) $candidate['id'], 'Payload JSON invalido');
            return null;
        }

        return [
            'id' => (int) $candidate['id'],
            'payload' => $payload,
            'attempt' => (int) $candidate['attempts'] + 1,
            'max_attempts' => (int) $candidate['max_attempts'],
        ];
    }

    public function complete(int $jobId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE jobs_queue
             SET status = 'COMPLETED',
                 completed_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$jobId]);
    }

    public function fail(int $jobId, string $error): void
    {
        $error = substr($error, 0, 500);
        $stmt = $this->db->prepare(
            'SELECT id, queue_name, payload_json, attempts, max_attempts
             FROM jobs_queue
             WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            return;
        }

        $attempts = (int) $job['attempts'];
        $maxAttempts = (int) $job['max_attempts'];

        if ($attempts >= $maxAttempts) {
            $dlq = $this->db->prepare(
                'INSERT INTO jobs_dlq (job_id, queue_name, payload_json, attempts, max_attempts, last_error, failed_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $dlq->execute([
                (int) $job['id'],
                (string) $job['queue_name'],
                (string) $job['payload_json'],
                $attempts,
                $maxAttempts,
                $error,
            ]);

            $markFailed = $this->db->prepare(
                "UPDATE jobs_queue
                 SET status = 'FAILED',
                     last_error = ?,
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $markFailed->execute([$error, $jobId]);
            return;
        }

        $backoff = $this->computeBackoffSeconds($attempts);
        $retry = $this->db->prepare(
            "UPDATE jobs_queue
             SET status = 'RETRY',
                 last_error = ?,
                 available_at = FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) + ?),
                 updated_at = NOW()
             WHERE id = ?"
        );
        $retry->execute([$error, $backoff, $jobId]);
    }

    private function recoverStaleJobs(string $queue): void
    {
        $stmt = $this->db->prepare(
            "UPDATE jobs_queue
             SET status = 'RETRY',
                 available_at = NOW(),
                 updated_at = NOW(),
                 last_error = COALESCE(last_error, 'Recovered after worker crash/timeout')
             WHERE queue_name = ?
               AND status = 'PROCESSING'
               AND reserved_at IS NOT NULL
               AND reserved_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->execute([$queue]);
    }

    private function computeBackoffSeconds(int $attempt): int
    {
        return min(300, max(5, $attempt * 10));
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
