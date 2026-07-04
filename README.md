# Sistem de monitorizare și raportare pentru un proces de dozare industrial

Lucrare de licență - Universitatea Politehnica Timișoara, Facultatea de
Automatică și Calculatoare, sesiunea iunie 2026.

Sistemul preia automat greutatea de la un indicator de cântărire ESIT
Smart-P prin magistrala RS485, o interpretează în funcție de rețeta
selectată și o transmite către două destinații: platforma Arduino Cloud
(monitorizare în timp real pe aplicația mobilă) și un server local
(stocare în MySQL, tablou de bord web și rapoarte automate pe email).

## Structura proiectului

- `arduino/` - programul plăcii Arduino UNO R4 WiFi (citire ESIT,
  mașină de stări, comunicație cloud și HTTP)
- `server/` - scripturile PHP (recepție date, dashboard web, raport
  email) și schema bazei de date MySQL

## Tehnologii utilizate

- Arduino UNO R4 WiFi (Renesas RA4M1 + ESP32-S3)
- Comunicație RS485 (indicator ESIT Smart-P)
- Arduino IoT Cloud
- PHP + MySQL (XAMPP), Bootstrap
- PHPMailer (SMTP)

## Notă

Datele de autentificare (parola de aplicație Gmail) și adresele IP au
fost înlocuite cu valori generice. Cantitățile rețetelor de producție
nu sunt incluse, fiind informații de proces ale beneficiarului.
