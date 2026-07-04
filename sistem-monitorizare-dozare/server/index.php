<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Istoric Dozare Malaxor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Sistem Malaxor - Istoric Dozare</span>
    </div>
</nav>

<div class="container">
    
    <?php
    $conn = new mysqli("localhost", "root", "", "licenta_db");
    if ($conn->connect_error) {
        die("Eroare conexiune: " . $conn->connect_error);
    }
    
    // Greutatile teoretice ale fiecarei retete (FARA paiele finale)
    $greutati_teoretice = [
        'TEST'=> 70
    ];
    
    $filtru = isset($_GET['reteta']) ? $_GET['reteta'] : '';
    
    if ($filtru != '') {
        $stmt = $conn->prepare("SELECT * FROM istoric_dozare WHERE reteta = ? ORDER BY data_finalizarii DESC");
        $stmt->bind_param("s", $filtru);
        $stmt->execute();
        $rezultat = $stmt->get_result();
    } else {
        $rezultat = $conn->query("SELECT * FROM istoric_dozare ORDER BY data_finalizarii DESC");
    }
    
    $total_sarje = $rezultat->num_rows;
    ?>
    
    <form method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <select name="reteta" class="form-select" onchange="this.form.submit()">
                    <option value="">Toate retetele (<?php echo $total_sarje; ?>)</option>
                    <option value="Tavane"    <?php if($filtru=='Tavane') echo 'selected'; ?>>Tavane</option>
                    <option value="Tencuiala" <?php if($filtru=='Tencuiala') echo 'selected'; ?>>Tencuiala</option>
                    <option value="Finisaj"   <?php if($filtru=='Finisaj') echo 'selected'; ?>>Finisaj</option>
                    <option value="Esential"  <?php if($filtru=='Esential') echo 'selected'; ?>>Esential</option>
                    <option value="TEST"  <?php if($filtru=='TEST') echo 'selected'; ?>>TEST</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Reteta</th>
                        <th>Greutate (kg)</th>
                        <th>Durata</th>
                        <th>Progres</th>
                        <th>Data si ora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($rezultat->num_rows > 0) {
                        while($rand = $rezultat->fetch_assoc()) {
                            $minute = floor($rand['durata_secunde'] / 60);
                            $secunde = $rand['durata_secunde'] % 60;
                            $durata_format = sprintf("%02d:%02d", $minute, $secunde);
                            $data_format = date('d.m.Y H:i', strtotime($rand['data_finalizarii']));
                            
                            $reteta = $rand['reteta'];
                            $greutate = $rand['greutate_kg'];
                            $teoretic = isset($greutati_teoretice[$reteta]) ? $greutati_teoretice[$reteta] : 0;
                            
                            $procentaj = 0;
                            if ($teoretic > 0) {
                                $procentaj = ($greutate / $teoretic) * 100;
                                if ($procentaj > 100) $procentaj = 100;
                            }
                            
                            if ($procentaj >= 95) {
                                $culoare = "bg-success";
                            } elseif ($procentaj >= 50) {
                                $culoare = "bg-warning";
                            } else {
                                $culoare = "bg-danger";
                            }
                            
                            echo "<tr>";
                            echo "<td>" . $rand['id'] . "</td>";
                            echo "<td>" . $reteta . "</td>";
                            echo "<td>" . number_format($greutate, 2) . "</td>";
                            echo "<td>" . $durata_format . "</td>";
                            echo "<td style='min-width:180px;'>";
                            echo "  <div class='progress' style='height:20px;'>";
                            echo "    <div class='progress-bar $culoare' role='progressbar' style='width:" . $procentaj . "%;'>";
                            echo "      " . number_format($procentaj, 0) . "%";
                            echo "    </div>";
                            echo "  </div>";
                            echo "</td>";
                            echo "<td>" . $data_format . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>Nicio inregistrare gasita.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="mt-3 small text-muted">
                <span class="badge bg-success">Verde</span> Sarja completa (≥95%)
                <span class="badge bg-warning ms-2">Galben</span> Sarja partiala (50-95%)
                <span class="badge bg-danger ms-2">Rosu</span> Sarja intrerupta (&lt;50%)
            </div>
            
            <div class="mt-2 small text-muted">
                <em>Notă: procentajele se calculează față de greutatea teoretică fără paiele adăugate manual la final.</em>
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>
    
</div>

</body>
</html>
