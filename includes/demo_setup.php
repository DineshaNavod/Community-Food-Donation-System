<?php
declare(strict_types=1);
// Internal CLI/test helper. The SQL file is the single source of demo records.
function load_sample_data(PDO $pdo): array
{
    $sql=file_get_contents(__DIR__.'/../database/sample_data.sql');
    if ($sql===false) {throw new RuntimeException('Sample SQL could not be read.');}
    $sql=preg_replace('/^DELIMITER[^\r\n]*$/m','',$sql);$results=[];
    try {
        foreach (explode('$$',$sql) as $statement) {
            $statement=trim($statement);if ($statement==='') {continue;}
            if (preg_match('/\ACALL\s/i',$statement)) {
                $query=$pdo->query($statement);
                do {if ($query->columnCount()>0) {foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {if (isset($row['result'])) {$results[]=$row['result'];}}}}while ($query->nextRowset());
                $query->closeCursor();
            }else {$pdo->exec($statement);}
        }
        return $results;
    } catch (Throwable $exception) {
        // Clean up only this setup script's temporary stored routine.
        $pdo->exec('DROP PROCEDURE IF EXISTS community_food_demo_seed_v1');throw $exception;
    }
}
