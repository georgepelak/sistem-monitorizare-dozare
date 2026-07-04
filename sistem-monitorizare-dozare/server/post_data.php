<?php
$conn = new mysqli("localhost", "root", "", "licenta_db");

if ($conn->connect_error) {
    die("Eroare conexiune: " . $conn->connect_error);
}

// SALVARE date noi
if (isset($_GET['reteta']) && isset($_GET['greutate'])) {
    $reteta = $_GET['reteta'];
    $greutate = floatval($_GET['greutate']);
    $durata = isset($_GET['durata']) ? intval($_GET['durata']) : 0;

    $stmt = $conn->prepare("INSERT INTO istoric_dozare (reteta, greutate_kg, durata_secunde) VALUES (?, ?, ?)");
    $stmt->bind_param("sdi", $reteta, $greutate, $durata);
    
    if ($stmt->execute()) {
        echo "Date salvate cu succes!";
    } else {
        echo "Eroare la salvare.";
    }
    $stmt->close();
} else {
    echo "Astept date de la Arduino...";
}

$conn->close();
?>
