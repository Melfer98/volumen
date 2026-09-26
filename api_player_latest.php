<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

$player = trim($_GET['player'] ?? '');
if($player === ''){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"MISSING_PLAYER"]);
  exit;
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

  // Último registro del player
  $sqlLatest = "
    SELECT *
    FROM resultados_volumen
    WHERE player = :player
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ";
  $st = $pdo->prepare($sqlLatest);
  $st->execute([":player"=>$player]);
  $latest = $st->fetch();

  if(!$latest){
    echo json_encode(["ok"=>true, "data"=>null]);
    exit;
  }

  // Texto del compromiso anterior (sale del compromiso_next del registro de la semana previa)
  $prevText = null;
  if(!empty($latest["prev_semana"])){
    $sqlPrev = "
      SELECT compromiso_next
      FROM resultados_volumen
      WHERE player = :player AND semana = :semana
      ORDER BY created_at DESC, id DESC
      LIMIT 1
    ";
    $st2 = $pdo->prepare($sqlPrev);
    $st2->execute([
      ":player"=>$player,
      ":semana"=>$latest["prev_semana"]
    ]);
    $prevRow = $st2->fetch();
    if($prevRow) $prevText = $prevRow["compromiso_next"] ?? null;
  }

  // Week display para UI (si semana = "2026-05" mostramos "05")
  $weekKey = $latest["semana"] ?? "";
  $wk = $weekKey;
  if(preg_match('/^\d{4}-(\d{2})$/', $weekKey, $m)) $wk = $m[1];

  echo json_encode([
    "ok" => true,
    "week" => $wk,
    "week_key" => $weekKey,
    "player" => $latest["player"],
    "data" => [
      "aging60" => isset($latest["aging60"]) ? (float)$latest["aging60"] : null,
      "aging90" => isset($latest["aging90"]) ? (float)$latest["aging90"] : null,
      "aging180" => isset($latest["aging180"]) ? (float)$latest["aging180"] : null,
      "reservas" => isset($latest["reservas"]) ? (float)$latest["reservas"] : null,
      "sufijos" => isset($latest["sufijos"]) ? (float)$latest["sufijos"] : null,
      "margen_ytd" => isset($latest["margen_ytd"]) ? (float)$latest["margen_ytd"] : null,
      "margen_semanal" => isset($latest["margen_semanal"]) ? (float)$latest["margen_semanal"] : null,
      "perdidas" => isset($latest["perdidas"]) ? (float)$latest["perdidas"] : null
    ],
    "acts" => [
      "actA1" => (int)($latest["actA1"] ?? 0),
      "actA2" => (int)($latest["actA2"] ?? 0),
      "actA3" => (int)($latest["actA3"] ?? 0),
      "actB1" => (int)($latest["actB1"] ?? 0),
      "actB2" => (int)($latest["actB2"] ?? 0),
      "actB3" => (int)($latest["actB3"] ?? 0),
      "actB4" => (int)($latest["actB4"] ?? 0)
    ],
    "prev" => [
      "prev_semana" => $latest["prev_semana"] ?? null,
      "cumplio_prev" => (int)($latest["cumplio_prev"] ?? 0),
      "compromiso_prev_text" => $prevText
    ],
    "next" => [
      "compromiso_next" => $latest["compromiso_next"] ?? null
    ]
  ]);
  exit;

}catch(Exception $e){
  http_response_code(500);
  echo json_encode([
    "ok"=>false,
    "error"=>"DB_ERROR",
    "detail"=>$e->getMessage()
  ]);
  exit;
}
