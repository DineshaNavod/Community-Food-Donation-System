<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';

function report_date_range(mixed $start,mixed $end): array
{
    if (!is_string($start) || !is_string($end)) {throw new InvalidArgumentException('Enter valid report dates.');}
    if ($start==='' && $end==='') {return ['start'=>'','end'=>'','parameters'=>[]];}
    $dates=[];
    foreach ([$start,$end] as $value) {
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$value)) {throw new InvalidArgumentException('Choose both dates using valid calendar dates.');}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value || $date->format('Y')<'1000' || $date->format('Y')>'9998') {
            throw new InvalidArgumentException('Choose both dates using valid calendar dates.');
        }
        $dates[]=$date;
    }
    if ($dates[0]>$dates[1]) {throw new InvalidArgumentException('The start date must be on or before the end date.');}
    return ['start'=>$start,'end'=>$end,'parameters'=>[$dates[0]->format('Y-m-d H:i:s'),$dates[1]->modify('+1 day')->format('Y-m-d H:i:s')]];
}

function admin_report_data(array $range): array
{
    $pdo=db();$params=$range['parameters'];
    $where=static fn(string $column):string=>$params?" WHERE $column>=? AND $column<?":'';
    $count=static function(string $table,string $column) use ($pdo,$params,$where):int {
        $query=$pdo->prepare('SELECT COUNT(*) FROM '.$table.$where($column));$query->execute($params);return (int)$query->fetchColumn();
    };
    try {
        // One consistent snapshot for totals and their status breakdowns.
        $pdo->beginTransaction();
        $result=['totals'=>[
            'users'=>$count('users','created_at'),'donations'=>$count('donations','created_at'),
            'requests'=>$count('donation_requests','created_at'),'assignments'=>$count('collection_tasks','assigned_at')]];
        foreach (['deliveries'=>['collection_tasks','delivered_at'],'receipts'=>['donations','completed_at']] as $key=>[$table,$column]) {
            $sql='SELECT COUNT(*) FROM '.$table.($params?$where($column):" WHERE $column IS NOT NULL");
            $query=$pdo->prepare($sql);$query->execute($params);$result['totals'][$key]=(int)$query->fetchColumn();
        }
        $donationStatus="CASE WHEN status IN ('available','requested','assigned') AND deadline_at<=NOW() THEN 'expired' ELSE status END";
        foreach (['donations'=>['donations','created_at',$donationStatus],
            'requests'=>['donation_requests','created_at','status'],'tasks'=>['collection_tasks','assigned_at','status'],
            'users'=>['users','created_at','role']] as $key=>[$table,$column,$group]) {
            $query=$pdo->prepare("SELECT $group AS label,COUNT(*) AS total FROM $table".$where($column).' GROUP BY label ORDER BY label');
            $query->execute($params);$result[$key]=$query->fetchAll();
        }
        // Keep quantities in separate units; kilograms, portions and items cannot be added.
        $query=$pdo->prepare('SELECT quantity_unit AS label,COUNT(*) AS donations,SUM(quantity) AS quantity FROM donations'
            .($params?$where('completed_at'):' WHERE completed_at IS NOT NULL')." AND status='completed' GROUP BY quantity_unit ORDER BY quantity_unit");
        $query->execute($params);$result['received_quantities']=$query->fetchAll();
        $pdo->commit();return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}
