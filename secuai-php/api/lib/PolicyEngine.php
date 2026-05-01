<?php
class PolicyEngine {
  public static function dispatch(string $event, string $tenantId, string $subjectType, string $subjectId, array $record): void {
    $stmt = Db::$pdo->prepare("SELECT * FROM policy_rules WHERE tenant_id=? AND trigger_event=? AND enabled=1");
    $stmt->execute([$tenantId, $event]);
    $rules = $stmt->fetchAll();
    foreach ($rules as $rule) {
      $cond = json_decode($rule['condition_json'] ?? '{}', true) ?: [];
      $params = json_decode($rule['action_params'] ?? '{}', true) ?: [];
      if (!self::matches($cond, $record)) {
        self::logEvent($tenantId, $rule, $event, $subjectType, $subjectId, 'skipped', ['reason'=>'condition_not_met']);
        continue;
      }
      try {
        $result = self::execute($rule['action_kind'], $params, $tenantId, $subjectType, $subjectId, $record, $rule);
        self::logEvent($tenantId, $rule, $event, $subjectType, $subjectId, 'fired', $result);
      } catch (Throwable $e) {
        self::logEvent($tenantId, $rule, $event, $subjectType, $subjectId, 'error', ['error'=>$e->getMessage()]);
      }
    }
  }

  private static function matches(array $cond, array $rec): bool {
    if (empty($cond)) return true;
    if (!empty($cond['severity_in']) && !in_array(($rec['severity']??''), $cond['severity_in'], true)) return false;
    if (!empty($cond['status_in']) && !in_array(($rec['status']??''), $cond['status_in'], true)) return false;
    if (isset($cond['min_size_bytes']) && (int)($rec['size_bytes'] ?? 0) < (int)$cond['min_size_bytes']) return false;
    if (!empty($cond['title_contains']) && stripos(($rec['title']??''), $cond['title_contains']) === false) return false;
    return true;
  }

  private static function interp(string $tpl, array $rec): string {
    return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function($m) use($rec){
      return (string)($rec[$m[1]] ?? '');
    }, $tpl);
  }

  private static function execute(string $kind, array $params, string $tenant, string $sType, string $sId, array $rec, array $rule): array {
    switch ($kind) {
      case 'create_remediation_task': {
        $title = self::interp($params['title'] ?? "Policy: {$rule['name']}", $rec);
        $desc  = self::interp($params['description'] ?? "Auto-created by rule {$rule['name']}", $rec);
        $due   = isset($params['due_in_days']) ? date('Y-m-d', time()+86400*(int)$params['due_in_days']) : null;
        $id = uuidv4();
        Db::$pdo->prepare("INSERT INTO remediation_tasks (id,tenant_id,finding_id,title,description,status,due_date)
          VALUES (?,?,?,?,?, 'todo', ?)")->execute([
            $id, $tenant, $sType==='finding'?$sId:null,
            $title, "$desc\n\n[policy:".($rule['policy_id']??'none')."] [rule:{$rule['id']}] [$sType:$sId]",
            $due
          ]);
        return ['task_id'=>$id];
      }
      case 'log_activity': {
        $msg = self::interp($params['message'] ?? "Policy rule {$rule['name']} fired", $rec);
        Db::$pdo->prepare("INSERT INTO activity_log (id,tenant_id,actor_id,action,entity_type,entity_id,metadata)
          VALUES (?,?,?,?,?,?,?)")->execute([
            uuidv4(), $tenant, null, 'policy.fired', $sType, $sId,
            json_encode(['rule_id'=>$rule['id'],'message'=>$msg])
          ]);
        return ['logged'=>true];
      }
      case 'require_approval': {
        if ($sType === 'evidence_pack') {
          Db::$pdo->prepare("UPDATE evidence_packs SET status='submitted', decision_note=? WHERE id=?")
            ->execute(["[policy] {$rule['name']} requires additional approval.", $sId]);
        }
        return ['required'=>true];
      }
      case 'create_finding': {
        $id = uuidv4();
        Db::$pdo->prepare("INSERT INTO findings (id,tenant_id,title,description,severity)
          VALUES (?,?,?,?,?)")->execute([
            $id, $tenant,
            self::interp($params['title'] ?? "Policy violation: {$rule['name']}", $rec),
            self::interp($params['description'] ?? 'Auto-created by policy engine', $rec),
            $params['severity'] ?? 'medium'
          ]);
        return ['finding_id'=>$id];
      }
      case 'notify':
        return ['notified'=>$params['channel'] ?? 'in_app'];
    }
    return [];
  }

  private static function logEvent(string $tenant, array $rule, string $event, string $sType, string $sId, string $status, array $result): void {
    Db::$pdo->prepare("INSERT INTO policy_events (id,tenant_id,rule_id,policy_id,trigger_event,subject_type,subject_id,status,result)
      VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        uuidv4(), $tenant, $rule['id'], $rule['policy_id'] ?? null,
        $event, $sType, $sId, $status, json_encode($result)
      ]);
  }
}
