<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$host = "localhost";
$db   = "db15didqzqundb";
$user = "uo9ngxnigigae";
$pass = "General2026";

$player = strtoupper(trim($_GET['player'] ?? ''));
if($player === '') $player = 'FER';

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

  // ========= helper: columnas existentes =========
  $stmtCols = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'resultados_volumen'
  ");
  $stmtCols->execute([":db"=>$db]);
  $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
  $colset = array_fill_keys($cols, true);

  // Validar columnas mínimas
  if(!isset($colset["semana"]) || !isset($colset["player"])){
    http_response_code(500);
    echo json_encode([
      "ok"=>false,
      "error"=>"SCHEMA_ERROR",
      "detail"=>"La tabla resultados_volumen debe tener al menos columnas: semana, player"
    ]);
    exit;
  }

  // ========= ISO week helpers =========
  function isoWeekToDate($weekStr){
    $parts = explode('-', $weekStr);
    $y = intval($parts[0] ?? 0);
    $w = intval($parts[1] ?? 0);
    if($y <= 0 || $w <= 0) return null;

    $simple = new DateTimeImmutable(sprintf('%04d-01-01 12:00:00', $y), new DateTimeZone('UTC'));
    $simple = $simple->modify('+' . (($w - 1) * 7) . ' days');

    $dow = intval($simple->format('N')); // 1..7
    if($dow <= 4) $simple = $simple->modify('-' . ($dow - 1) . ' days');
    else $simple = $simple->modify('+' . (8 - $dow) . ' days');

    return $simple;
  }

  function weekKeyFromDate(DateTimeImmutable $d){
    $y = intval($d->format('o')); // ISO year
    $w = intval($d->format('W')); // ISO week
    return sprintf('%04d-%02d', $y, $w);
  }

  function weeksBackFromWeek($baseWeek, $count){
    $out = [];
    $d = isoWeekToDate($baseWeek);
    if(!$d){
      $d = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
    for($i=0; $i<$count; $i++){
      $out[] = weekKeyFromDate($d);
      $d = $d->modify('-7 days');
    }
    return array_reverse($out);
  }

  // ========= decidir ORDER BY que exista =========
  $orderBy = "semana DESC";
  if(isset($colset["created_at"])) $orderBy = "created_at DESC";
  if(isset($colset["id"])) $orderBy = (isset($colset["created_at"]) ? "created_at DESC, id DESC" : "id DESC");

  // ========= 1) última semana cargada por player =========
  $sqlLast = "
    SELECT semana
    FROM resultados_volumen
    WHERE player = :player
    ORDER BY $orderBy
    LIMIT 1
  ";
  $stmtLast = $pdo->prepare($sqlLast);
  $stmtLast->execute([":player"=>$player]);
  $lastWeek = $stmtLast->fetchColumn();

  // ========= columnas deseadas (se incluirán solo si existen) =========
  $desired = [
    "aging60","aging90","aging180","reservas","sufijos",
    "margen_semanal","margen_ytd","perdidas",
    "actA1","actA2","actA3","actB1","actB2","actB3","actB4",
    "compromiso_prev_text","prev_semana","cumplio_prev","compromiso_next"
  ];

  // filtro a solo existentes
  $selectCols = ["semana","player"];
  foreach($desired as $c){
    if(isset($colset[$c])) $selectCols[] = $c;
  }
  $selectList = implode(", ", $selectCols);

  // ========= si no hay registros: igual devolvemos 10 semanas null =========
  if(!$lastWeek){
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $base = weekKeyFromDate($now);
    $weeks = weeksBackFromWeek($base, 10);

    $history = [];
    foreach($weeks as $wk){
      $history[] = [
        "week_key"=>$wk,
        "aging90"=>null,
        "margen_ytd"=>null,
        "actA1"=>null,"actA2"=>null,"actA3"=>null,
        "actB1"=>null,"actB2"=>null,"actB3"=>null,"actB4"=>null
      ];
    }

    echo json_encode([
      "ok"=>true,
      "player"=>$player,
      "week_key"=>null,
      "latest"=>[
        "week_key"=>null,
        "data"=>[
          "aging60"=>null,"aging90"=>null,"aging180"=>null,"reservas"=>null,"sufijos"=>null,
          "margen_semanal"=>null,"margen_ytd"=>null,"perdidas"=>null
        ],
        "acts"=>[
          "actA1"=>null,"actA2"=>null,"actA3"=>null,"actB1"=>null,"actB2"=>null,"actB3"=>null,"actB4"=>null
        ],
        "prev"=>[
          "compromiso_prev_text"=>null,
          "cumplio_prev"=>null
        ],
        "next"=>[
          "compromiso_next"=>null
        ]
      ],
      "history"=>$history
    ]);
    exit;
  }

  // ========= 2) 10 semanas: last + 9 atrás =========
  $weeks = weeksBackFromWeek($lastWeek, 10);

  // ========= 3) Traer data para esas semanas (solo columnas existentes) =========
  $placeholders = implode(',', array_fill(0, count($weeks), '?'));
  $sqlHist = "
    SELECT $selectList
    FROM resultados_volumen
    WHERE player = ?
      AND semana IN ($placeholders)
  ";
  $params = array_merge([$player], $weeks);
  $stmtHist = $pdo->prepare($sqlHist);
  $stmtHist->execute($params);
  $rows = $stmtHist->fetchAll();

  // index por semana
  $byWeek = [];
  foreach($rows as $r){
    $byWeek[$r["semana"]] = $r;
  }

  // ========= 4) history siempre 10 filas =========
  $history = [];
  foreach($weeks as $wk){
    $r = $byWeek[$wk] ?? null;
    $history[] = [
      "week_key"=>$wk,
      "aging90"    => $r["aging90"] ?? null,
      "margen_ytd" => $r["margen_ytd"] ?? null,
      "actA1"=>$r["actA1"] ?? null,
      "actA2"=>$r["actA2"] ?? null,
      "actA3"=>$r["actA3"] ?? null,
      "actB1"=>$r["actB1"] ?? null,
      "actB2"=>$r["actB2"] ?? null,
      "actB3"=>$r["actB3"] ?? null,
      "actB4"=>$r["actB4"] ?? null
    ];
  }

  // ========= 5) latest =========
  $latestRow = $byWeek[$lastWeek] ?? null;

  // ========= 5.1) resolver compromiso previo =========
  $prevText = null;

  // Si la tabla ya tiene el texto directo, lo usamos
  if($latestRow && array_key_exists("compromiso_prev_text", $latestRow) && $latestRow["compromiso_prev_text"] !== null && $latestRow["compromiso_prev_text"] !== ''){
    $prevText = $latestRow["compromiso_prev_text"];
  }
  // Si no existe texto directo, lo buscamos usando prev_semana -> compromiso_next de la semana previa
  else if($latestRow && !empty($latestRow["prev_semana"]) && isset($colset["prev_semana"]) && isset($colset["compromiso_next"])){
    $sqlPrev = "
      SELECT compromiso_next
      FROM resultados_volumen
      WHERE player = :player
        AND semana = :prev_semana
      ORDER BY $orderBy
      LIMIT 1
    ";
    $stmtPrev = $pdo->prepare($sqlPrev);
    $stmtPrev->execute([
      ":player"=>$player,
      ":prev_semana"=>$latestRow["prev_semana"]
    ]);
    $prevRow = $stmtPrev->fetch();
    if($prevRow && isset($prevRow["compromiso_next"]) && $prevRow["compromiso_next"] !== null && $prevRow["compromiso_next"] !== ''){
      $prevText = $prevRow["compromiso_next"];
    }
  }

  $latestData = [
    "aging60"        => $latestRow["aging60"] ?? null,
    "aging90"        => $latestRow["aging90"] ?? null,
    "aging180"       => $latestRow["aging180"] ?? null,
    "reservas"       => $latestRow["reservas"] ?? null,
    "sufijos"        => $latestRow["sufijos"] ?? null,
    "margen_semanal" => $latestRow["margen_semanal"] ?? null,
    "margen_ytd"     => $latestRow["margen_ytd"] ?? null,
    "perdidas"       => $latestRow["perdidas"] ?? null
  ];

  $latestActs = [
    "actA1" => $latestRow["actA1"] ?? null,
    "actA2" => $latestRow["actA2"] ?? null,
    "actA3" => $latestRow["actA3"] ?? null,
    "actB1" => $latestRow["actB1"] ?? null,
    "actB2" => $latestRow["actB2"] ?? null,
    "actB3" => $latestRow["actB3"] ?? null,
    "actB4" => $latestRow["actB4"] ?? null
  ];

  $latestPrev = [
    "compromiso_prev_text" => $prevText,
    "cumplio_prev"         => $latestRow["cumplio_prev"] ?? null
  ];

  $latestNext = [
    "compromiso_next" => $latestRow["compromiso_next"] ?? null
  ];

  echo json_encode([
    "ok"=>true,
    "player"=>$player,
    "week_key"=>$lastWeek,
    "latest"=>[
      "week_key"=>$lastWeek,
      "data"=>$latestData,
      "acts"=>$latestActs,
      "prev"=>$latestPrev,
      "next"=>$latestNext
    ],
    "history"=>$history
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