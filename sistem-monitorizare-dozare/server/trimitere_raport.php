<?php

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CONFIGURARE EMAIL
$gmail_user = "xxxxx@gmail.com";
$gmail_pass = "xxxx xxxx xxxx xxxx";
$destinatar = "xxxxx@gmail.com";

$reteta_curenta = isset($_GET['reteta_curenta']) ? $_GET['reteta_curenta'] : "N/A";
$greutate_curenta = isset($_GET['greutate_curenta']) ? floatval($_GET['greutate_curenta']) : 0;

$greutati_teoretice = [
    'TEST'=> 70.0
];

$conn = new mysqli("localhost", "root", "", "licenta_db");
if ($conn->connect_error) {
    die("Eroare conexiune: " . $conn->connect_error);
}

$rezultat = $conn->query(
    "SELECT * FROM istoric_dozare 
     WHERE data_finalizarii >= NOW() - INTERVAL 24 HOUR 
     ORDER BY data_finalizarii DESC"
);

$total_sarje = $rezultat->num_rows;
$total_kg = 0;

$continut = "<h2>Raport Malaxor</h2>";
$continut .= "<p>Generat la: " . date('d.m.Y H:i:s') . "</p>";

$teoretic_curent = isset($greutati_teoretice[$reteta_curenta]) ? $greutati_teoretice[$reteta_curenta] : 0;
$procent_curent = 0;
if ($teoretic_curent > 0) {
    $procent_curent = ($greutate_curenta / $teoretic_curent) * 100;
    if ($procent_curent > 100) $procent_curent = 100;
}

$continut .= "<div style='background:#e8f4f8; padding:15px; border-radius:8px; margin-bottom:20px;'>";
$continut .= "<h3>Stare Actuala</h3>";
$continut .= "<p>Reteta selectata: <b>$reteta_curenta</b></p>";
$continut .= "<p>Greutate curenta: <b>" . number_format($greutate_curenta, 1) . " kg</b>";
if ($teoretic_curent > 0) {
    $continut .= " din " . number_format($teoretic_curent, 1) . " kg teoretic";
}
$continut .= "</p>";

if ($teoretic_curent > 0) {
    $culoare_curenta = ($procent_curent >= 95) ? "#28a745" : (($procent_curent >= 50) ? "#ffc107" : "#dc3545");
    $continut .= "<div style='background:#e0e0e0; border-radius:4px; height:24px; overflow:hidden;'>";
    $continut .= "  <div style='background:$culoare_curenta; width:" . $procent_curent . "%; height:100%; color:white; text-align:center; line-height:24px; font-weight:bold;'>";
    $continut .= "    " . number_format($procent_curent, 0) . "%";
    $continut .= "  </div>";
    $continut .= "</div>";
}
$continut .= "</div>";

$continut .= "<h3>Istoric ultimele 24 ore (Total: $total_sarje sarje)</h3>";

if ($total_sarje > 0) {
    $continut .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
    $continut .= "<tr style='background:#34495e; color:white;'>
                    <th>ID</th>
                    <th>Reteta</th>
                    <th>Greutate (kg)</th>
                    <th>Durata</th>
                    <th>Progres</th>
                    <th>Data si ora</th>
                  </tr>";
    
    while ($rand = $rezultat->fetch_assoc()) {
        $total_kg += $rand['greutate_kg'];
        $minute = floor($rand['durata_secunde'] / 60);
        $secunde = $rand['durata_secunde'] % 60;
        $durata_format = sprintf("%02d:%02d", $minute, $secunde);
        
        $reteta = $rand['reteta'];
        $greutate = $rand['greutate_kg'];
        $teoretic = isset($greutati_teoretice[$reteta]) ? $greutati_teoretice[$reteta] : 0;
        
        $procent = 0;
        if ($teoretic > 0) {
            $procent = ($greutate / $teoretic) * 100;
            if ($procent > 100) $procent = 100;
        }
        
        $culoare = ($procent >= 95) ? "#28a745" : (($procent >= 50) ? "#ffc107" : "#dc3545");
        
        $continut .= "<tr>";
        $continut .= "<td>" . $rand['id'] . "</td>";
        $continut .= "<td>" . $reteta . "</td>";
        $continut .= "<td>" . number_format($greutate, 2) . "</td>";
        $continut .= "<td>" . $durata_format . "</td>";
        $continut .= "<td style='min-width:150px;'>";
        $continut .= "  <div style='background:#e0e0e0; border-radius:4px; height:20px; overflow:hidden;'>";
        $continut .= "    <div style='background:$culoare; width:" . $procent . "%; height:100%; color:white; text-align:center; line-height:20px; font-size:12px; font-weight:bold;'>";
        $continut .= "      " . number_format($procent, 0) . "%";
        $continut .= "    </div>";
        $continut .= "  </div>";
        $continut .= "</td>";
        $continut .= "<td>" . date('d.m.Y H:i', strtotime($rand['data_finalizarii'])) . "</td>";
        $continut .= "</tr>";
    }
    $continut .= "</table>";
    $continut .= "<p><b>Total material 24h: " . number_format($total_kg, 2) . " kg</b></p>";
    
    $continut .= "<p style='font-size:12px; color:#666;'>";
    $continut .= "<span style='background:#28a745; color:white; padding:2px 8px; border-radius:3px;'>Verde</span> Sarja completa (≥95%) ";
    $continut .= "<span style='background:#ffc107; color:white; padding:2px 8px; border-radius:3px; margin-left:8px;'>Galben</span> Sarja partiala (50-95%) ";
    $continut .= "<span style='background:#dc3545; color:white; padding:2px 8px; border-radius:3px; margin-left:8px;'>Rosu</span> Sarja intrerupta (&lt;50%)";
    $continut .= "</p>";
    
    $continut .= "<p style='font-size:11px; color:#888; font-style:italic;'>";
    $continut .= "Procentajele se calculeaza fata de greutatea teoretica fara paiele adaugate manual la final.";
    $continut .= "</p>";
} else {
    $continut .= "<p>Nu s-au inregistrat sarje in ultimele 24 ore.</p>";
}

$continut .= "<hr><small>Email generat automat de sistemul de monitorizare Malaxor.</small>";

$conn->close();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_user;
    $mail->Password   = $gmail_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($gmail_user, 'Sistem Malaxor');
    $mail->addAddress($destinatar);

    $mail->isHTML(true);
    $mail->Subject = 'Raport Malaxor - ' . date('d.m.Y H:i');
    $mail->Body    = $continut;

    $mail->send();
    echo "Email trimis cu succes!";
} catch (Exception $e) {
    echo "Eroare la trimitere: " . $mail->ErrorInfo;
}
?>