#include "thingProperties.h"

const char* SERVER_IP = "192.xxx.x.xxx";   // IP-ul calculatorului cu XAMPP

// Convertizoarele pe al doilea UART hardware, pe A4 (TX) / A5 (RX)
UART SerialPowtran(A4, A5);

// Pachete Modbus 
byte pachetStartB2[] = {0x02, 0x06, 0x20, 0x00, 0x00, 0x02, 0x03, 0xF8};
byte pachetStopB2[]  = {0x02, 0x06, 0x20, 0x00, 0x00, 0x05, 0x42, 0x3A};
byte pachetStartB3[] = {0x03, 0x06, 0x20, 0x00, 0x00, 0x02, 0x02, 0x29};
byte pachetStopB3[]  = {0x03, 0x06, 0x20, 0x00, 0x00, 0x05, 0x43, 0xEB};

// Parametri reteta TEST (toate pe timp) 
const unsigned long DURATA_B2 = 7000;            // Banda 2 ruleaza 7 secunde
const unsigned long PAUZA_MS  = 5000;            // pauza intre benzi (5 s)
const unsigned long DURATA_B3 = 7000;            // Banda 3 ruleaza 7 secunde
const unsigned long DURATA_STABILIZARE = 5000;   // stabilizare cantar (5 s)

// Buton si LED-uri 
const int btnMod = 9;
const int ledRosu1 = 6;
const int ledVerde1 = 7;
const int ledRosu2 = 10;
const int ledVerde2 = 11;
const int ledAlbastru2 = 12;

// Stari secventa 
enum StareSecv { IDLE, RULARE_B2, PAUZA, RULARE_B3, STABILIZARE };
StareSecv stareSecv = IDLE;

unsigned long cronoSecv = 0;
unsigned long timpStartSarja = 0;
unsigned long ultimulHeartbeat = 0;
String bufferCantar = "";
bool ultimulBtn = HIGH;
bool raportTrimis = false;

void setup() {
  Serial.begin(9600);
  delay(1500);
  Serial1.begin(1200);          // ESIT Smart-P pe pinii 0/1
  SerialPowtran.begin(9600);    // convertizoare pe A4/A5

  pinMode(btnMod, INPUT_PULLUP);
  pinMode(ledRosu1, OUTPUT); pinMode(ledVerde1, OUTPUT);
  digitalWrite(ledRosu1, HIGH); digitalWrite(ledVerde1, LOW);
  pinMode(ledRosu2, OUTPUT); pinMode(ledVerde2, OUTPUT); pinMode(ledAlbastru2, OUTPUT);
  seteazaLedButon2(false, false, true);   // ALBASTRU la pornire (sistem in repaus)

  initProperties();
  ArduinoCloud.begin(ArduinoIoTPreferredConnection);
  setDebugMessageLevel(2);
  ArduinoCloud.printDebugInfo();

  info_text = "Sistem pornit. Reteta TEST. Apasati butonul pentru pornire.";
  Serial.println("=== Sistem pornit - reteta TEST ===");
}

void loop() {
  ArduinoCloud.update();
  citesteESIT();
  ruleazaSecventa();
  heartbeat();
}

// CITIRE CANTAR 
void citesteESIT() {
  while (Serial1.available() > 0) {
    char c = Serial1.read();
    if (c == '\n' || c == '\r') {
      if (bufferCantar.length() > 0) {
        proceseazaCantar(bufferCantar);
        bufferCantar = "";
      }
    } else {
      if (c >= 32 && c <= 126) bufferCantar += c;
    }
  }
}

void proceseazaCantar(String mesaj) {
  bool esteValid = true;
  int cifreGasite = 0;
  for (int i = 0; i < mesaj.length(); i++) {
    char ch = mesaj[i];
    if (isdigit(ch)) cifreGasite++;
    else if (ch != '+' && ch != '-' && ch != '.' && ch != ' ') { esteValid = false; break; }
  }
  if (esteValid && cifreGasite > 0) {
    mesaj.trim();
    greutate_totala = mesaj.toFloat() / 10.0;   // ESIT x10 -> kg
  }
}

// SECVENTA TEST 
void ruleazaSecventa() {
  bool citireBtn = digitalRead(btnMod);
  if (citireBtn == LOW && ultimulBtn == HIGH) {
    delay(50);
    if (digitalRead(btnMod) == LOW) {
      if (stareSecv == IDLE) {
        pornesteTest();
      } else {
        Serial.println(">>> OPRIRE MANUALA - secventa intrerupta.");
        opresteTot();
        info_text = "Secventa oprita manual.";
      }
    }
  }
  ultimulBtn = citireBtn;

  switch (stareSecv) {
    case RULARE_B2:
      info_text = "TEST - Banda 2 (" + String(greutate_totala, 1) + " kg)";
      if (millis() - cronoSecv >= DURATA_B2) {
        opresteBanda(pachetStopB2);
        Serial.println(">>> Banda 2 oprita - 7 s expirate. Pauza 5 s.");
        info_text = "TEST - pauza (5 s)";
        cronoSecv = millis();
        stareSecv = PAUZA;
      }
      break;

    case PAUZA:
      if (millis() - cronoSecv >= PAUZA_MS) {
        pornesteBanda(pachetStartB3);
        Serial.println(">>> START Banda 3. Ruleaza 7 secunde.");
        cronoSecv = millis();
        stareSecv = RULARE_B3;
      }
      break;

    case RULARE_B3:
      info_text = "TEST - Banda 3 (" + String(greutate_totala, 1) + " kg)";
      if (millis() - cronoSecv >= DURATA_B3) {
        opresteBanda(pachetStopB3);
        Serial.println(">>> Banda 3 oprita. Stabilizare 5 s.");
        info_text = "TEST - stabilizare cantar (5 s)...";
        cronoSecv = millis();
        stareSecv = STABILIZARE;
      }
      break;

    case STABILIZARE:
      info_text = "TEST - stabilizare (" + String(greutate_totala, 1) + " kg)";
      if (millis() - cronoSecv >= DURATA_STABILIZARE) {
        Serial.print(">>> Stabilizare incheiata. Greutate finala: ");
        Serial.print(greutate_totala, 1); Serial.println(" kg.");
        finalizeazaTest();
      }
      break;

    default: break;
  }
}

void pornesteTest() {
  timpStartSarja = millis();
  raportTrimis = false;
  digitalWrite(ledRosu1, LOW); digitalWrite(ledVerde1, HIGH);
  seteazaLedButon2(true, false, true);     // MOV cat timp ruleaza secventa
  pornesteBanda(pachetStartB2);
  Serial.println(">>> START TEST. Banda 2 ruleaza 7 secunde.");
  info_text = "TEST pornit - Banda 2";
  cronoSecv = millis();
  stareSecv = RULARE_B2;
}

void finalizeazaTest() {
  digitalWrite(ledVerde1, LOW); digitalWrite(ledRosu1, HIGH);
  seteazaLedButon2(false, false, true);    // ALBASTRU dupa finalizare
  stareSecv = IDLE;
  unsigned long durata = (millis() - timpStartSarja) / 1000;
  if (!raportTrimis) {
    trimiteLaServer("TEST", greutate_totala, durata);
    delay(500);
    cereTrimitereEmail();
    raportTrimis = true;
  }
  info_text = "TEST finalizat: " + String(greutate_totala, 1) + " kg\nRaport trimis.";
  Serial.println(">>> Secventa TEST finalizata.");
}

void pornesteBanda(byte* pachet) {
  SerialPowtran.write(pachet, 8);
}

void opresteBanda(byte* pachet) {
  SerialPowtran.write(pachet, 8);
  delay(50);
  SerialPowtran.write(pachet, 8);
}

void opresteTot() {
  SerialPowtran.write(pachetStopB2, 8); delay(20);
  SerialPowtran.write(pachetStopB3, 8); delay(20);
  SerialPowtran.write(pachetStopB2, 8); delay(20);
  SerialPowtran.write(pachetStopB3, 8);
  digitalWrite(ledVerde1, LOW); digitalWrite(ledRosu1, HIGH);
  seteazaLedButon2(false, false, true);    // ALBASTRU dupa oprire manuala
  stareSecv = IDLE;
}

// CULOARE LED BUTON 2 
void seteazaLedButon2(bool rosu, bool verde, bool albastru) {
  digitalWrite(ledRosu2, rosu ? HIGH : LOW);
  digitalWrite(ledVerde2, verde ? HIGH : LOW);
  digitalWrite(ledAlbastru2, albastru ? HIGH : LOW);
}

// COMUNICATIE SERVER
void trimiteLaServer(String reteta, float greutate, unsigned long durata) {
  WiFiClient client;
  if (client.connect(SERVER_IP, 80)) {
    String url = "/proiect_licenta/post_data.php?";
    url += "reteta=" + reteta;
    url += "&greutate=" + String(greutate);
    url += "&durata=" + String(durata);
    client.print("GET " + url + " HTTP/1.1\r\n");
    client.print("Host: " + String(SERVER_IP) + "\r\n");
    client.print("Connection: close\r\n\r\n");
    Serial.println("Date trimise la server.");
    client.stop();
  } else {
    Serial.println("EROARE: serverul nu raspunde.");
  }
}

void cereTrimitereEmail() {
  WiFiClient client;
  if (client.connect(SERVER_IP, 80)) {
    String url = "/proiect_licenta/trimite_raport.php?";
    url += "reteta_curenta=TEST";
    url += "&greutate_curenta=" + String(greutate_totala);
    client.print("GET " + url + " HTTP/1.1\r\n");
    client.print("Host: " + String(SERVER_IP) + "\r\n");
    client.print("Connection: close\r\n\r\n");
    Serial.println("Cerere email trimisa.");
    client.stop();
  } else {
    Serial.println("EROARE email: serverul nu raspunde.");
  }
}

void heartbeat() {
  if (millis() - ultimulHeartbeat >= 5000) {
    ultimulHeartbeat = millis();
    Serial.print("[Heartbeat] Greutate=");
    Serial.print(greutate_totala);
    Serial.print(" kg | Stare=");
    Serial.println(stareSecv);
  }
}

// CALLBACK-URI CLOUD
void onSelectieRetetaChange() { info_text = "Reteta TEST. Apasati butonul pentru pornire."; }
void onGreutateTotalaChange() { }
void onInfoTextChange() { }

void onTrimiteMailChange() {
  if (trimite_mail == true) {
    cereTrimitereEmail();
    info_text = "Email trimis manual";
    trimite_mail = false;
  }
}

void onSalveazaAcumChange() {
  if (salveaza_acum == true) {
    unsigned long durata = (stareSecv != IDLE) ? (millis() - timpStartSarja) / 1000 : 0;
    trimiteLaServer("TEST", greutate_totala, durata);
    info_text = "Salvare manuala TEST";
    salveaza_acum = false;
  }
}
