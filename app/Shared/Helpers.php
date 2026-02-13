<?php

namespace App\Shared;

use PDO;
use Throwable;

class Helpers
{
    public static function transaction(PDO $db, callable $callback): mixed
    {
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
