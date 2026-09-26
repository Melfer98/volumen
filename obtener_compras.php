<?php
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

$hist  = trim($_GET['hist'] ?? '0');           // "1" para histórico
$limit = (int)($_GET['limit'] ?? 12);
if($limit <= 0) $limit = 12;
if($limit > 52) $limit = 52;

$semana = trim($_GET['semana'] ?? '');

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

  // =========================
  // MODO HISTÓRICO
  // =========================
  if($hist === '1'){
    $sql = "
      SELECT
        semana,
        cobertura_total, aging60, aging90, aging180, sufijos,
        cmpA1, cmpA2, cmpA3, cmpA4,
        cmpB1, cmpB2, cmpB3, cmpB4
      FROM resultados_compras
      ORDER BY semana DESC
      LIMIT :lim
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo json_encode(["ok"=>true, "data"=>$rows]);
    exit;
  }

  // =========================
  // MODO DETALLE (1 semana)
  // =========================
  if($semana === ''){
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"MISSING_REQUIRED"]);
    exit;
  }

  $sql = "
    SELECT
      semana,
      cobertura_total, aging60, aging90, aging180, sufijos,
      cmpA1, cmpA2, cmpA3, cmpA4,
      cmpB1, cmpB2, cmpB3, cmpB4
    FROM resultados_compras
    WHERE semana = :semana
    LIMIT 1
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([":semana" => $semana]);
  $row = $stmt->fetch();

  if(!$row){
    http_response_code(404);
    echo json_encode(["ok"=>false,"error"=>"NOT_FOUND"]);
    exit;
  }

  echo json_encode(["ok"=>true,"data"=>$row]);
  exit;

}catch(PDOException $e){
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB_ERROR"]);
  exit;
}
