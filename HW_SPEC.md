# Zestawienie Sprzętu – FireOps (HW Specification)

## 1. Urządzenia osobiste strażaka

### Smartfon (wersja ekonomiczna)
- Samsung A35 / A55 / A54 (bez UWB)
- Opcjonalny **zewnętrzny moduł UWB** (150–300 zł)
- Aplikacja FireOps:
  - Zbieranie danych z IMU telefonu  
  - Zbieranie biometrii z opaski  
  - Inferencja RONIN (lokalnie lub w chmurze)  
  - Komunikacja LTE + BLE  
  - Lokalne buforowanie danych  

### Opaska biomedyczna
- Xiaomi Smart Band 8 / Huawei Band 8  
- Pomiar HR, SpO₂, temp., oddech  
- Komunikacja BLE z telefonem  

---

## 2. Sensory (wersja ekonomiczna)
- IMU telefonu + opcjonalny moduł IMU (MPU-9250, BNO055)  
- Sensory środowiskowe (ekonomiczne IoT):  
  - CO₂, CO, tlen, temp./wilgotność  
- Detekcja:  
  - Upadek (algorytm w aplikacji)  
  - SOS (manualne)  
  - Krytyczne wartości biometrii (opaska)  

---

## 3. Infrastruktura komunikacyjna

### Gateway (Low-Cost)
- Router LTE + WiFi z OpenWRT  
- VPN (WireGuard)  
- Komunikacja:
  - BLE – wewnątrz zespołu  
  - LTE – do backendu  
  - LoRa – opcjonalnie (moduł 80–120 zł)  

---

## 4. Sprzęt dowództwa akcji

### Laptop dowódcy (poleasingowy)
- Lenovo T480 / Dell 5490 / HP EliteBook  
- Dashboard FireOps:
  - Lokalizacja strażaków  
  - Biometria  
  - Historia ruchu  
  - Alerty w czasie rzeczywistym  

### Serwer TAK (opcjonalnie)
- Mini-PC lub ten sam laptop (dual-use)
- Integracja przez Cursor-on-Target

---

## 5. Backend / Chmura (Low-Cost)
- Python / Flask  
- Model RONIN (opcjonalnie częściowo w chmurze)  
- PostgreSQL (na VPS lub lokalnie)  
- API + WebSocket  
- VPN WireGuard  

### Tani hosting:
- VPS 10–20 zł/mies.  
- ~150–250 zł/rok  

---

## 6. Symulator
- Generowanie testowych ścieżek  
- Integracja z telefonami (A54/A55) lub modułem UWB  
- Komunikacja z backendem  

---

# BOM – Szacowane koszty

| Komponent | Ilość | Cena jednostkowa (PLN) | Koszt całkowity |
|----------|-------|-------------------------|------------------|
| Smartfon Samsung A35/A55 | 1 | 1200–1800 | 1200–1800 |
| Moduł UWB (opcjonalnie) | 1 | 150–300 | 150–300 |
| Opaska biomedyczna (Band 8) | 1 | 150–250 | 150–250 |
| Router LTE/OpenWRT | 1 | 200–350 | 200–350 |
| Laptop dowódcy – poleasingowy | 1 | 1500–2500 | 1500–2500 |
| Serwer TAK/backend (mini-PC lub VPS) | 1 | 0–1500 (lub 150–250/rok) | 0–1500 |
| Sensory środowiskowe | zestaw | 100–200 | 100–200 |

---

# **Łączny koszt zestawu (1 strażak + dowództwo):**
## **3 300 – 6 600 PLN**  
