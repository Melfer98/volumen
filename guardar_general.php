<?php
header('Content-Type: application/json; charset=utf-8');

/* conexión directa a la base */
$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";     // tu usuario de MySQL de SiteGround
$pass = "General2026";       // tu password de MySQL

function toFloatOrNull($v){
  if(!isset($v)) return null;
  $v = trim((string)$v);
  if($v === '') return null;
  $v = str_replace(',', '.', $v);
  if(!is_numeric($v)) return null;
  return (float)$v;
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

$aging60        = toFloatOrNull($_POST['aging60'] ?? null);
$aging90        = toFloatOrNull($_POST['aging90'] ?? null);
$aging180       = toFloatOrNull($_POST['aging180'] ?? null);
$reservas       = toFloatOrNull($_POST['reservas'] ?? null);
$sufijos        = toFloatOrNull($_POST['sufijos'] ?? null);
$margen_ytd     = toFloatOrNull($_POST['margen_ytd'] ?? null);
$margen_semanal = toFloatOrNull($_POST['margen_semanal'] ?? null);
$perdidas       = toFloatOrNull($_POST['perdidas'] ?? null);

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

  // Inserta. Si ya existe la semana (UNIQUE), fallará con 1062.
  $sql = "
    INSERT INTO resultados_general
      (semana, aging60, aging90, aging180, reservas, sufijos, margen_ytd, margen_semanal, perdidas)
    VALUES
      (:semana, :a60, :a90, :a180, :res, :suf, :mytd, :msem, :per)
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ":semana"=>$semana,
    ":a60"=>$aging60,
    ":a90"=>$aging90,
    ":a180"=>$aging180,
    ":res"=>$reservas,
    ":suf"=>$sufijos,
    ":mytd"=>$margen_ytd,
    ":msem"=>$margen_semanal,
    ":per"=>$perdidas
  ]);

  echo json_encode(["ok"=>true,"semana"=>$semana]);
  exit;

}catch(PDOException $e){
  // 1062 = Duplicate entry (semana ya existe por UNIQUE)
  if(isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062){
    http_response_code(409);
    echo json_encode(["ok"=>false,"error"=>"DUPLICATE_WEEK"]);
    exit;
  }
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB_ERROR"]);
  exit;
}
