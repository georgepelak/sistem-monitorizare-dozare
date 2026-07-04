CREATE DATABASE IF NOT EXISTS licenta_db;
USE licenta_db;
 
CREATE TABLE IF NOT EXISTS istoric_dozare (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reteta VARCHAR(50),
    greutate_kg FLOAT,
    durata_secunde INT,
    data_finalizarii TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
