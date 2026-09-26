<?php
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

function prevSemanaISO($sem){
  if(!preg_match('/^(\d{4})-(\d{2})$/', $sem, $m)) return null;
  $y = (int)$m[1];
  $w = (int)$m[2];
  $w--;
  if($w >= 1) return sprintf("%04d-%02d", $y, $w);
  $y--;
  return sprintf("%04d-52", $y);
}

$player = trim($_GET['player'] ?? '');
$semana = trim($_GET['semana'] ?? '');

if($player === '' || $semana === ''){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"MISSING_REQUIRED"]);
  exit;
}

$prev = prevSemanaISO($semana);
if($prev === null){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"BAD_WEEK"]);
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

  $sql = "SELECT compromiso_next
          FROM resultados_volumen
          WHERE player = :player AND semana = :semana
          LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([":player"=>$player, ":semana"=>$prev]);
  $row = $stmt->fetch();

  echo json_encode([
    "ok"=>true,
    "prev_semana"=>$prev,
    "compromiso_prev"=>$row ? ($row["compromiso_next"] ?? null) : null
  ]);
  exit;

}catch(PDOException $e){
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB_ERROR"]);
  exit;
}
