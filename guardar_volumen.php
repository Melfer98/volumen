<?php
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

function toFloatOrNull($v){
  if(!isset($v)) return null;
  $v = trim((string)$v);
  if($v === '') return null;
  $v = str_replace(',', '.', $v);
  if(!is_numeric($v)) return null;
  return (float)$v;
}
function toBool01($key){
  return isset($_POST[$key]) ? 1 : 0;
}
function toInt01($v){
  $v = trim((string)$v);
  return ($v === '1') ? 1 : 0;
}

$player = trim($_POST['player'] ?? '');

$tz = new DateTimeZone('America/El_Salvador');
$hoy = new DateTimeImmutable('now', $tz);

if((int)$hoy->format('N') !== 1){
  http_response_code(403);
  echo json_encode(["ok"=>false,"error"=>"NOT_MONDAY"]);
  exit;
}

/* El lunes se registran los resultados de la semana ISO anterior. */
$fechaReporte = $hoy->modify('-7 days');
$semana = $fechaReporte->format('o-W');

if($player === ''){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"MISSING_REQUIRED"]);
  exit;
}

$aging60        = toFloatOrNull($_POST['aging60'] ?? null);
$aging90        = toFloatOrNull($_POST['aging90'] ?? null);
$aging180       = toFloatOrNull($_POST['aging180'] ?? null);
$reservas       = toFloatOrNull($_POST['reservas'] ?? null);
$sufijos        = toFloatOrNull($_POST['sufijos'] ?? null);
$margen_ytd     = toFloatOrNull($_POST['margen_ytd'] ?? null);
$margen_semanal = toFloatOrNull($_POST['margen_semanal'] ?? null);
$perdidas       = toFloatOrNull($_POST['perdidas'] ?? null);

$actA1 = toBool01('actA1');
$actA2 = toBool01('actA2');
$actA3 = toBool01('actA3');
$actB1 = toBool01('actB1');
$actB2 = toBool01('actB2');
$actB3 = toBool01('actB3');
$actB4 = toBool01('actB4');

/* nuevos campos */
$prev_semana = trim($_POST['prev_semana'] ?? '');
if($prev_semana === '') $prev_semana = null;

$cumplio_prev = toInt01($_POST['cumplio_prev'] ?? '0');

$compromiso_next = trim($_POST['compromiso_next'] ?? '');
if($compromiso_next === '') $compromiso_next = null;

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

  $sql = "
    INSERT INTO resultados_volumen
      (semana, player, aging60, aging90, aging180, reservas, sufijos,
       margen_ytd, margen_semanal, perdidas,
       actA1, actA2, actA3, actB1, actB2, actB3, actB4,
       prev_semana, cumplio_prev, compromiso_next)
    VALUES
      (:semana, :player, :a60, :a90, :a180, :res, :suf,
       :mytd, :msem, :per,
       :actA1, :actA2, :actA3, :actB1, :actB2, :actB3, :actB4,
       :psem, :cprev, :cnext)
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ":semana"  => $semana,
    ":player" => $player,

    ":a60"  => $aging60,
    ":a90"  => $aging90,
    ":a180" => $aging180,
    ":res"  => $reservas,
    ":suf"  => $sufijos,

    ":mytd" => $margen_ytd,
    ":msem" => $margen_semanal,
    ":per"  => $perdidas,

    ":actA1" => $actA1,
    ":actA2" => $actA2,
    ":actA3" => $actA3,
    ":actB1" => $actB1,
    ":actB2" => $actB2,
    ":actB3" => $actB3,
    ":actB4" => $actB4,

    ":psem"  => $prev_semana,
    ":cprev" => $cumplio_prev,
    ":cnext" => $compromiso_next
  ]);

  echo json_encode(["ok"=>true,"semana"=>$semana,"player"=>$player]);
  exit;

}catch(PDOException $e){
  if(isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062){
    http_response_code(409);
    echo json_encode(["ok"=>false,"error"=>"DUPLICATE_WEEK"]);
    exit;
  }
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB_ERROR"]);
  exit;
}
