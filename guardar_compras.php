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

$tz = new DateTimeZone('America/El_Salvador');
$hoy = new DateTimeImmutable('now', $tz);

if((int)$hoy->format('N') !== 1){
  http_response_code(403);
  echo json_encode(["ok"=>false,"error"=>"NOT_MONDAY"]);
  exit;
}

/* El lunes se registran los resultados de la semana ISO anterior. */
$semana = $hoy->modify('-7 days')->format('o-W');

$cobertura_total = toFloatOrNull($_POST['cobertura_total'] ?? null);

$aging60  = toFloatOrNull($_POST['aging60'] ?? null);
$aging90  = toFloatOrNull($_POST['aging90'] ?? null);
$aging180 = toFloatOrNull($_POST['aging180'] ?? null);
$sufijos  = toFloatOrNull($_POST['sufijos'] ?? null);

$cmpA1 = toBool01('cmpA1');
$cmpA2 = toBool01('cmpA2');
$cmpA3 = toBool01('cmpA3');
$cmpA4 = toBool01('cmpA4');

$cmpB1 = toBool01('cmpB1');
$cmpB2 = toBool01('cmpB2');
$cmpB3 = toBool01('cmpB3');
$cmpB4 = toBool01('cmpB4');

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
    INSERT INTO resultados_compras
      (semana, cobertura_total, aging60, aging90, aging180, sufijos,
       cmpA1, cmpA2, cmpA3, cmpA4,
       cmpB1, cmpB2, cmpB3, cmpB4)
    VALUES
      (:semana, :ct, :a60, :a90, :a180, :suf,
       :cmpA1, :cmpA2, :cmpA3, :cmpA4,
       :cmpB1, :cmpB2, :cmpB3, :cmpB4)
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ":semana" => $semana,
    ":ct"     => $cobertura_total,

    ":a60"  => $aging60,
    ":a90"  => $aging90,
    ":a180" => $aging180,
    ":suf"  => $sufijos,

    ":cmpA1" => $cmpA1,
    ":cmpA2" => $cmpA2,
    ":cmpA3" => $cmpA3,
    ":cmpA4" => $cmpA4,

    ":cmpB1" => $cmpB1,
    ":cmpB2" => $cmpB2,
    ":cmpB3" => $cmpB3,
    ":cmpB4" => $cmpB4
  ]);

  echo json_encode(["ok"=>true,"semana"=>$semana]);
  exit;

}catch(PDOException $e){
  // Duplicado por UNIQUE(semana)
  if(isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062){
    http_response_code(409);
    echo json_encode(["ok"=>false,"error"=>"DUPLICATE_WEEK"]);
    exit;
  }
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB_ERROR"]);
  exit;
}
