<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB connection failed"]);
  exit;
}

$sql = "SELECT semana, aging60, aging90, aging180, reservas, sufijos, margen_ytd, margen_semanal, perdidas
        FROM resultados_general
        ORDER BY created_at DESC, id DESC
        LIMIT 1";

$res = $conn->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"Query failed"]);
  exit;
}

$row = $res->fetch_assoc();
if (!$row) {
  echo json_encode(["ok"=>true,"data"=>null]);
  exit;
}

// semana viene como "2026-05" -> nos quedamos con "05" para el título
$weekStr = $row["semana"] ?? "";
$wk = $weekStr;
if (preg_match('/^\d{4}-(\d{2})$/', $weekStr, $m)) $wk = $m[1];

echo json_encode([
  "ok" => true,
  "week" => $wk,
  "week_key" => $weekStr,
  "data" => [
    "aging60" => (float)$row["aging60"],
    "aging90" => (float)$row["aging90"],
    "aging180" => (float)$row["aging180"],
    "reservas" => (float)$row["reservas"],
    "sufijos" => (float)$row["sufijos"],
    "margen_ytd" => (float)$row["margen_ytd"],
    "margen_semanal" => (float)$row["margen_semanal"],
    "perdidas" => (float)$row["perdidas"]
  ]
]);

$conn->close();
