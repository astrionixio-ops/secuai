<?php
declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);

require __DIR__.'/lib/Resp.php';
require __DIR__.'/lib/Db.php';
require __DIR__.'/lib/Jwt.php';
require __DIR__.'/lib/Auth.php';
require __DIR__.'/lib/PolicyEngine.php';

$cfg = file_exists(__DIR__.'/config.php') ? require __DIR__.'/config.php' : require __DIR__.'/config.example.php';

// CORS
header('Access-Control-Allow-Origin: '.$cfg['cors_origin']);
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

try {
  Db::init($cfg['db']);
  Auth::load($cfg);

  $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  // strip leading /api or script base
  $uri = preg_replace('#^/api#','',$uri);
  $uri = '/'.trim($uri,'/');
  $method = $_SERVER['REQUEST_METHOD'];

  $route = "$method $uri";

  // ---------------- Public auth
  if ($route === 'POST /auth/signup') {
    $b = input_json();
    if (empty($b['email']) || empty($b['password'])) Resp::error(400,'email and password required');
    $stmt = Db::$pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$b['email']]);
    if ($stmt->fetch()) Resp::error(409,'email already in use');
    $id = uuidv4();
    Db::$pdo->prepare("INSERT INTO users (id,email,password_hash,display_name,email_confirmed) VALUES (?,?,?,?,1)")
      ->execute([$id, $b['email'], password_hash($b['password'], PASSWORD_BCRYPT), $b['display_name'] ?? null]);
    Db::$pdo->prepare("INSERT INTO profiles (id,user_id,display_name) VALUES (?,?,?)")
      ->execute([uuidv4(), $id, $b['display_name'] ?? explode('@',$b['email'])[0]]);
    Resp::json(['id'=>$id, 'token'=>Jwt::encode(['sub'=>$id,'email'=>$b['email'],'exp'=>time()+$cfg['jwt_ttl']], $cfg['jwt_secret'])]);
  }

  if ($route === 'POST /auth/login') {
    $b = input_json();
    $stmt = Db::$pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$b['email'] ?? '']);
    $u = $stmt->fetch();
    if (!$u || !password_verify($b['password'] ?? '', $u['password_hash'])) Resp::error(401,'invalid credentials');
    Resp::json([
      'token' => Jwt::encode(['sub'=>$u['id'],'email'=>$u['email'],'exp'=>time()+$cfg['jwt_ttl']], $cfg['jwt_secret']),
      'user'  => ['id'=>$u['id'],'email'=>$u['email'],'display_name'=>$u['display_name']],
    ]);
  }

  if ($route === 'GET /health') Resp::json(['ok'=>true]);

  // ---------------- Authed
  Auth::require();

  if ($route === 'GET /me') {
    $stmt = Db::$pdo->prepare("SELECT u.id,u.email,u.display_name FROM users u WHERE u.id=?");
    $stmt->execute([Auth::userId()]);
    $u = $stmt->fetch();
    $tm = Db::$pdo->prepare("SELECT tm.tenant_id, tm.role, t.name, t.slug FROM tenant_members tm JOIN tenants t ON t.id=tm.tenant_id WHERE tm.user_id=?");
    $tm->execute([Auth::userId()]);
    Resp::json(['user'=>$u,'tenants'=>$tm->fetchAll()]);
  }

  // ---- Tenants
  if ($route === 'POST /tenants') {
    $b = input_json();
    if (empty($b['name']) || empty($b['slug'])) Resp::error(400,'name and slug required');
    $id = uuidv4();
    Db::$pdo->prepare("INSERT INTO tenants (id,name,slug) VALUES (?,?,?)")->execute([$id,$b['name'],$b['slug']]);
    Db::$pdo->prepare("INSERT INTO branding (id,tenant_id,product_name) VALUES (?,?,?)")->execute([uuidv4(),$id,$b['name']]);
    Db::$pdo->prepare("INSERT INTO tenant_members (id,tenant_id,user_id,role) VALUES (?,?,?, 'admin')")
      ->execute([uuidv4(),$id,Auth::userId()]);
    Resp::json(['id'=>$id]);
  }

  // ---- Generic tenant-scoped list helper
  $tenant = $_GET['tenant_id'] ?? null;

  if ($method === 'GET' && in_array($uri, [
    '/findings','/assessments','/evidence','/documents','/remediation_tasks',
    '/evidence_packs','/policies','/policy_rules','/policy_events','/policy_acknowledgements',
    '/activity_log','/coverage_snapshots','/frameworks','/controls','/environments','/assets','/scan_jobs','/organizations'
  ])) {
    if ($uri === '/frameworks' || $uri === '/controls') {
      $stmt = Db::$pdo->query("SELECT * FROM ".substr($uri,1)." ORDER BY created_at DESC");
      Resp::json($stmt->fetchAll());
    }
    if (!$tenant) Resp::error(400,'tenant_id required');
    Auth::requireMember($tenant);
    $table = substr($uri,1);
    $stmt = Db::$pdo->prepare("SELECT * FROM `$table` WHERE tenant_id=? ORDER BY created_at DESC LIMIT 500");
    $stmt->execute([$tenant]);
    Resp::json($stmt->fetchAll());
  }

  // ---- Findings (insert -> trigger policy engine)
  if ($route === 'POST /findings') {
    $b = input_json();
    if (empty($b['tenant_id']) || empty($b['title'])) Resp::error(400,'tenant_id and title required');
    Auth::requireRole($b['tenant_id'], ['admin','auditor','analyst']);
    $id = uuidv4();
    Db::$pdo->prepare("INSERT INTO findings (id,tenant_id,assessment_id,asset_id,title,description,recommendation,severity,status)
      VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        $id, $b['tenant_id'], $b['assessment_id'] ?? null, $b['asset_id'] ?? null,
        $b['title'], $b['description'] ?? null, $b['recommendation'] ?? null,
        $b['severity'] ?? 'medium', $b['status'] ?? 'open'
      ]);
    $stmt = Db::$pdo->prepare("SELECT * FROM findings WHERE id=?"); $stmt->execute([$id]); $rec = $stmt->fetch();
    PolicyEngine::dispatch('finding_created', $b['tenant_id'], 'finding', $id, $rec);
    Resp::json($rec);
  }

  if ($method === 'PATCH' && preg_match('#^/findings/([a-f0-9-]{36})$#i',$uri,$m)) {
    $b = input_json();
    $stmt = Db::$pdo->prepare("SELECT * FROM findings WHERE id=?"); $stmt->execute([$m[1]]); $row = $stmt->fetch();
    if (!$row) Resp::error(404,'not found');
    Auth::requireRole($row['tenant_id'], ['admin','auditor','analyst']);
    $fields = ['title','description','recommendation','severity','status'];
    $set = []; $vals = [];
    foreach ($fields as $f) if (array_key_exists($f,$b)) { $set[]="$f=?"; $vals[]=$b[$f]; }
    if (!$set) Resp::json($row);
    $vals[] = $m[1];
    Db::$pdo->prepare("UPDATE findings SET ".implode(',',$set)." WHERE id=?")->execute($vals);
    $stmt->execute([$m[1]]); $rec = $stmt->fetch();
    PolicyEngine::dispatch('finding_updated', $rec['tenant_id'], 'finding', $rec['id'], $rec);
    Resp::json($rec);
  }

  // ---- Evidence Packs (status transitions trigger policy engine)
  if ($route === 'POST /evidence_packs') {
    $b = input_json();
    if (empty($b['tenant_id']) || empty($b['title'])) Resp::error(400,'tenant_id and title required');
    Auth::requireRole($b['tenant_id'], ['admin','analyst']);
    $id = uuidv4();
    $status = $b['status'] ?? 'draft';
    Db::$pdo->prepare("INSERT INTO evidence_packs (id,tenant_id,assessment_id,title,description,file_url,status,created_by,submitted_at,submitted_by)
      VALUES (?,?,?,?,?,?,?,?, ?, ?)")->execute([
        $id, $b['tenant_id'], $b['assessment_id'] ?? null, $b['title'],
        $b['description'] ?? null, $b['file_url'] ?? null, $status, Auth::userId(),
        $status === 'submitted' ? date('Y-m-d H:i:s') : null,
        $status === 'submitted' ? Auth::userId() : null,
      ]);
    $stmt = Db::$pdo->prepare("SELECT * FROM evidence_packs WHERE id=?"); $stmt->execute([$id]); $rec = $stmt->fetch();
    if ($status === 'submitted') PolicyEngine::dispatch('pack_submitted', $b['tenant_id'], 'evidence_pack', $id, $rec);
    Resp::json($rec);
  }

  if ($method === 'POST' && preg_match('#^/evidence_packs/([a-f0-9-]{36})/decision$#i',$uri,$m)) {
    $b = input_json();
    $decision = $b['decision'] ?? '';
    if (!in_array($decision,['approved','rejected'],true)) Resp::error(400,'decision must be approved|rejected');
    $stmt = Db::$pdo->prepare("SELECT * FROM evidence_packs WHERE id=?"); $stmt->execute([$m[1]]); $pack = $stmt->fetch();
    if (!$pack) Resp::error(404,'not found');
    Auth::requireRole($pack['tenant_id'], ['admin','auditor']);
    Db::$pdo->prepare("UPDATE evidence_packs SET status=?, decided_by=?, decided_at=NOW(), decision_note=? WHERE id=?")
      ->execute([$decision, Auth::userId(), $b['note'] ?? null, $m[1]]);
    Db::$pdo->prepare("INSERT INTO evidence_pack_reviews (id,tenant_id,pack_id,decision,note,reviewer_id) VALUES (?,?,?,?,?,?)")
      ->execute([uuidv4(),$pack['tenant_id'],$m[1],$decision,$b['note']??null,Auth::userId()]);
    $stmt->execute([$m[1]]); $rec = $stmt->fetch();
    PolicyEngine::dispatch($decision==='approved'?'pack_approved':'pack_rejected', $pack['tenant_id'], 'evidence_pack', $m[1], $rec);
    Resp::json($rec);
  }

  // ---- Policies CRUD
  if ($route === 'POST /policies') {
    $b = input_json();
    if (empty($b['tenant_id']) || empty($b['title'])) Resp::error(400,'tenant_id and title required');
    Auth::requireRole($b['tenant_id'], ['admin','auditor','analyst']);
    $id = uuidv4();
    $cadence = (int)($b['review_cadence_days'] ?? 365);
    $next = $cadence > 0 ? date('Y-m-d', time()+86400*$cadence) : null;
    Db::$pdo->prepare("INSERT INTO policies (id,tenant_id,title,summary,body,category,version,status,review_cadence_days,next_review_at,created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $id, $b['tenant_id'], $b['title'], $b['summary']??null, $b['body']??null,
        $b['category']??'general', $b['version']??'1.0', $b['status']??'draft', $cadence, $next, Auth::userId()
      ]);
    Resp::json(['id'=>$id]);
  }

  if ($route === 'POST /policy_rules') {
    $b = input_json();
    if (empty($b['tenant_id']) || empty($b['name']) || empty($b['trigger_event']) || empty($b['action_kind']))
      Resp::error(400,'tenant_id, name, trigger_event, action_kind required');
    Auth::requireRole($b['tenant_id'], ['admin','auditor','analyst']);
    $id = uuidv4();
    Db::$pdo->prepare("INSERT INTO policy_rules (id,tenant_id,policy_id,name,description,trigger_event,condition_json,action_kind,action_params,enabled,created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $id, $b['tenant_id'], $b['policy_id']??null, $b['name'], $b['description']??null,
        $b['trigger_event'], json_encode($b['condition']??[]), $b['action_kind'],
        json_encode($b['action_params']??[]), !empty($b['enabled'])?1:1, Auth::userId()
      ]);
    Resp::json(['id'=>$id]);
  }

  if ($method === 'PATCH' && preg_match('#^/policy_rules/([a-f0-9-]{36})$#i',$uri,$m)) {
    $b = input_json();
    $stmt = Db::$pdo->prepare("SELECT * FROM policy_rules WHERE id=?"); $stmt->execute([$m[1]]); $row = $stmt->fetch();
    if (!$row) Resp::error(404,'not found');
    Auth::requireRole($row['tenant_id'], ['admin','auditor','analyst']);
    if (array_key_exists('enabled',$b)) {
      Db::$pdo->prepare("UPDATE policy_rules SET enabled=? WHERE id=?")->execute([$b['enabled']?1:0,$m[1]]);
    }
    Resp::json(['ok'=>true]);
  }

  if ($route === 'POST /policy_acknowledgements') {
    $b = input_json();
    if (empty($b['tenant_id']) || empty($b['policy_id']) || empty($b['version'])) Resp::error(400,'tenant_id, policy_id, version required');
    Auth::requireMember($b['tenant_id']);
    $id = uuidv4();
    Db::$pdo->prepare("INSERT INTO policy_acknowledgements (id,tenant_id,policy_id,user_id,version) VALUES (?,?,?,?,?)
      ON DUPLICATE KEY UPDATE acknowledged_at=NOW()")
      ->execute([$id,$b['tenant_id'],$b['policy_id'],Auth::userId(),$b['version']]);
    Resp::json(['ok'=>true]);
  }

  // ---- Generic create for simpler tables (assessments, environments, organizations, documents metadata, etc.)
  if ($method === 'POST' && in_array($uri, ['/assessments','/environments','/organizations','/documents','/remediation_tasks'])) {
    $b = input_json();
    if (empty($b['tenant_id'])) Resp::error(400,'tenant_id required');
    Auth::requireRole($b['tenant_id'], ['admin','auditor','analyst']);
    $id = uuidv4();
    $table = substr($uri,1);
    $cols = array_keys($b);
    // sanitize column names: only alphanumeric/underscore
    $cols = array_filter($cols, fn($c) => preg_match('/^[a-z_][a-z0-9_]*$/i',$c));
    $cols[] = 'id';
    $vals = array_map(fn($c) => $c==='id' ? $id : (is_array($b[$c]) ? json_encode($b[$c]) : $b[$c]), $cols);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $colSql = '`'.implode('`,`', $cols).'`';
    Db::$pdo->prepare("INSERT INTO `$table` ($colSql) VALUES ($place)")->execute($vals);
    Resp::json(['id'=>$id]);
  }

  // ---- Document upload
  if ($route === 'POST /upload') {
    $tenant = $_POST['tenant_id'] ?? null;
    if (!$tenant) Resp::error(400,'tenant_id required');
    Auth::requireRole($tenant, ['admin','auditor','analyst']);
    if (empty($_FILES['file'])) Resp::error(400,'file required');
    $f = $_FILES['file'];
    $dir = rtrim($cfg['upload_dir'],'/').'/'.$tenant;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = uuidv4().'-'.preg_replace('/[^A-Za-z0-9._-]/','_', $f['name']);
    $path = "$dir/$name";
    if (!move_uploaded_file($f['tmp_name'], $path)) Resp::error(500,'upload failed');
    $url = rtrim($cfg['public_url'],'/')."/$tenant/$name";
    Resp::json(['storage_path'=>$path,'file_url'=>$url,'size_bytes'=>$f['size'],'mime_type'=>$f['type']]);
  }

  Resp::error(404,"route not found: $route");

} catch (Throwable $e) {
  Resp::error(500, $e->getMessage());
}
