<?php
declare(strict_types=1);

function decide_pending_account(int $adminId,int $accountId,string $decision): void
{
    if (!in_array($decision,['approve','reject'],true)) {throw new InvalidArgumentException('Choose approve or reject.');}
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $query->execute([$adminId,$accountId]);$accounts=[];
        foreach ($query->fetchAll() as $row) {$accounts[(int)$row['id']]=$row;}
        $admin=$accounts[$adminId]??null;$target=$accounts[$accountId]??null;
        if (!$admin || $admin['role']!=='admin' || $admin['status']!=='active') {throw new DomainException('Your account cannot approve registrations.');}
        if (!$target) {throw new OutOfBoundsException('Account not found.');}
        if ($target['status']!=='pending' || !in_array($target['role'],['organization','volunteer'],true)) {
            throw new DomainException('Only pending organization and volunteer registrations can be decided here.');
        }
        if ($decision==='approve' && $target['role']==='organization') {
            $query=$pdo->prepare('SELECT id FROM organizations WHERE user_id=? FOR UPDATE');$query->execute([$accountId]);
            if (!$query->fetchColumn()) {throw new DomainException('This account has no organization profile. It cannot be approved until its profile is repaired.');}
        }
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$decision==='approve'?'active':'rejected',$accountId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}

function change_account_access(int $adminId,int $accountId,string $action): void
{
    if (!in_array($action,['suspend','reactivate'],true)) {throw new InvalidArgumentException('Choose suspend or reactivate.');}
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $query->execute([$adminId,$accountId]);$accounts=[];
        foreach ($query->fetchAll() as $account) {$accounts[(int)$account['id']]=$account;}
        $admin=$accounts[$adminId]??null;$target=$accounts[$accountId]??null;
        if (!$admin || $admin['role']!=='admin' || $admin['status']!=='active') {throw new DomainException('Your account cannot manage user access.');}
        if (!$target) {throw new OutOfBoundsException('Account not found.');}
        if ($accountId===$adminId || !in_array($target['role'],['donor','organization','volunteer'],true)) {
            throw new DomainException('Administrator accounts cannot be changed here.');
        }
        $expected=$action==='suspend'?'active':'suspended';
        if ($target['status']!==$expected) {throw new DomainException('This account status has changed. Reload registered users before trying again.');}
        if ($action==='reactivate' && $target['role']==='organization') {
            $query=$pdo->prepare('SELECT id FROM organizations WHERE user_id=? FOR UPDATE');$query->execute([$accountId]);
            if (!$query->fetchColumn()) {throw new DomainException('This account has no organization profile and cannot be reactivated.');}
        }
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$action==='suspend'?'suspended':'active',$accountId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}
