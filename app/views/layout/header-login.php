
<!doctype html>

<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'SISElo - UBS - CADH') ?></title>
  <style>
    body{font-family: Arial, sans-serif; margin:0; background:#f6f7fb;}
    .topbar{background:#0f766e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;}
    .topbar a{color:#fff; text-decoration:none; margin-right:12px;}
    .container{max-width:1100px; margin:18px auto; padding:0 14px;}
    .card{background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; box-shadow:0 2px 10px rgba(0,0,0,.04);}
    .menu{display:flex; gap:10px; flex-wrap:wrap;}
    .menu a{background:rgba(255,255,255,.15); padding:6px 10px; border-radius:8px;}
    table{border-collapse:collapse; width:100%;}
    th,td{border:1px solid #e5e7eb; padding:8px; vertical-align:top;}
    th{background:#f3f4f6; text-align:left;}
    .muted{color:#6b7280; font-size:12px;}
    .btn{display:inline-block; padding:8px 10px; border-radius:8px; border:1px solid #d1d5db; background:#fff; text-decoration:none; color:#111827;}
    .btn-primary{background:#0f766e; color:#fff; border-color:#0f766e;}
    .btn-danger{background:#b91c1c; color:#fff; border-color:#b91c1c;}
    .actions form{display:inline;}
  </style>
</head>
<body>

<div class="topbar">
  <div>
    <b>SisElo - UBS - CADH</b>
  </div>

  
</div>

<div class="container">
  <div class="card">
