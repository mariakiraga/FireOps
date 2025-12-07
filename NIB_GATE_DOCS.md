# Bramka NIB 

**Bramka NIB** to urządzenie komunikacyjne działające jako warstwa pośrednia pomiędzy systemem nadrzędnym a systemem NIB.  
Rozwiązuje problemy niekompatybilnych protokołów, umożliwia bezpieczną wymianę danych i zapewnia izolację logiczną lub elektryczną systemów.

---

## Funkcjonalności
- Dwukierunkowa transmisja danych między NIB a systemem nadrzędnym.  
- Obsługa konfigurowalnych interfejsów (np. UART, CAN, SPI, Ethernet — wg implementacji).  
- Sygnalizacja stanu pracy (LED / GPIO).  
- Obsługa błędów transmisji (retransmisja, CRC, raportowanie).  
- Opcjonalne logowanie zdarzeń i pracy urządzenia.

---

##  Architektura sprzętowa

+-------------------------------------------------+
| Zasilanie / PSU |
+-------------------------+-----------------------+
| Interfejs Systemu | Interfejs NIB |
| (UART/Eth/...) | (CAN/SPI/...) |
+-----------+-------------+-------------+---------+
\ /
\ /
+-------------------+
| MCU / CPU / SoC |
+-------------------+
| |
+--------+ +----------+
| |
+-------------------+ +--------------------+
| Pamięć / Bufor | | Sygnalizacja |
| (Flash / RAM) | | LED / GPIO |
+-------------------+ +--------------------+

## Interfejsy komunikacyjne

### **Interfejs do systemu nadrzędnego**
- UART / Ethernet / RS-485 — zależnie od konfiguracji  
- Konfigurowalne parametry transmisji  

### **Interfejs NIB**
- CAN / SPI / TTL (wg specyfikacji NIB)  
- Obsługa protokołu NIB, walidacja CRC  

### **Sygnalizacja**
- LED: **PWR**, **LINK**, **ERR**  
- GPIO: diagnostyka / testy fabryczne  


