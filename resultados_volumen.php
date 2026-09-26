<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

/* =========================
   Helpers
========================= */
function compact_str($s){
  $s = trim($s ?? '');
  $s = preg_replace('/\s+/', '', $s);
  return $s;
}

// Devuelve lista de variantes equivalentes de semana, en orden.
function week_variants($s){
  $s = compact_str($s);
  $vars = [];
  if($s !== '') $vars[] = $s;

  // 2026-W05 / 2026-w05 / 2026-W5
  if(preg_match('/^(\d{4})-W(\d{1,2})$/i', $s, $m)){
    $y = $m[1];
    $w = str_pad($m[2], 2, '0', STR_PAD_LEFT);
    $vars[] = "{$y}-{$w}";      // DB estilo 2026-05
    $vars[] = "{$y}-W{$w}";     // DB estilo 2026-W05
    $vars[] = "{$y}-" . intval($w);   // 2026-5
  }

  // 2026-05 o 2026-5
  if(preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)){
    $y = $m[1];
    $w2 = str_pad($m[2], 2, '0', STR_PAD_LEFT);
    $vars[] = "{$y}-{$w2}";
    $vars[] = "{$y}-W{$w2}";
    $vars[] = "{$y}-" . intval($w2);
  }

  // elimina duplicados manteniendo orden
  $out = [];
  $seen = [];
  foreach($vars as $v){
    $k = strtolower($v);
    if(isset($seen[$k])) continue;
    $seen[$k] = true;
    $out[] = $v;
  }
  return $out;
}

try{
  $pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );

  // ========= columnas existentes (para ordenar bien) =========
  $stmtCols = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'resultados_volumen'
  ");
  $stmtCols->execute([":db"=>$db]);
  $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
  $colset = array_fill_keys($cols, true);

  // ORDER BY como api_player.php
  $orderBy = "semana DESC";
  if(isset($colset["created_at"])) $orderBy = "created_at DESC";
  if(isset($colset["id"])) $orderBy = (isset($colset["created_at"]) ? "created_at DESC, id DESC" : "id DESC");

  $semanaReq = compact_str($_GET['semana'] ?? '');
  $variants = week_variants($semanaReq);

  /* =========================
     1) Si no mandan semana -> última semana cargada
  ========================= */
  if($semanaReq === ''){
    $lastWeek = $pdo
      ->query("SELECT semana FROM resultados_volumen ORDER BY $orderBy LIMIT 1")
      ->fetchColumn();

    if(!$lastWeek){
      echo json_encode(["ok"=>true,"semana"=>null,"rows"=>[]], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $variants = week_variants($lastWeek);
  }

  /* =========================
     2) Buscar por variantes
  ========================= */
  $in = implode(',', array_fill(0, count($variants), '?'));

  $sql = "SELECT player, actA1, actA2, actA3, actB1, actB2, actB3, actB4, semana
          FROM resultados_volumen
          WHERE semana IN ($in)";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($variants);
  $rows = $stmt->fetchAll();

  /* =========================
     3) Fallback: si NO hay rows para esa semana,
        entonces devolver la última semana cargada (como comportamiento “player”)
  ========================= */
  if(empty($rows)){
    $lastWeek = $pdo
      ->query("SELECT semana FROM resultados_volumen ORDER BY $orderBy LIMIT 1")
      ->fetchColumn();

    if(!$lastWeek){
      echo json_encode(["ok"=>true,"semana"=>null,"rows"=>[]], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $variants2 = week_variants($lastWeek);
    $in2 = implode(',', array_fill(0, count($variants2), '?'));

    $sql2 = "SELECT player, actA1, actA2, actA3, actB1, actB2, actB3, actB4, semana
             FROM resultados_volumen
             WHERE semana IN ($in2)";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($variants2);
    $rows2 = $stmt2->fetchAll();

    echo json_encode([
      "ok" => true,
      "semana" => $lastWeek,
      "rows" => $rows2
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Si sí encontró
  echo json_encode([
    "ok" => true,
    // devuelve la semana que se pidió (normalizada al primer variant) solo como referencia
    "semana" => $variants[0],
    "rows" => $rows
  ], JSON_UNESCAPED_UNICODE);
  exit;

}catch(Exception $e){
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "DB_ERROR",
    "detail" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
