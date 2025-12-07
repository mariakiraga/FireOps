import uuid
from datetime import datetime, timedelta

# ====== CONFIG ======
# Domyślny typ (Friendly Ground Unit Combatant - standard dla ludzi w TAK)
DEFAULT_COT_TYPE = "a-f-G-U-C" 

def create_cot_event(lat, lon, uid, name, cot_type=DEFAULT_COT_TYPE):
    """
    Generuje XML CoT.
    
    :param lat: Szerokość geograficzna
    :param lon: Długość geograficzna
    :param uid: UNIKALNY i STAŁY identyfikator (np. "FF-001"). Nie może się zmieniać dla tej samej osoby!
    :param name: Nazwa wyświetlana na mapie (np. "Jan Kowalski")
    :param cot_type: Typ ikony (domyślnie przyjazna jednostka naziemna)
    """
    now = datetime.utcnow()
    # Punkt ważny przez 2 minuty (jeśli nie przyjdzie update, stanie się "stale" - szary)
    stale = now + timedelta(minutes=2) 

    # Tworzenie XML
    cot_xml = f'''<event version="2.0" type="{cot_type}" uid="{uid}" 
      time="{now.strftime('%Y-%m-%dT%H:%M:%S.%f')[:-3]}Z" 
      start="{now.strftime('%Y-%m-%dT%H:%M:%S.%f')[:-3]}Z"
      stale="{stale.strftime('%Y-%m-%dT%H:%M:%S.%f')[:-3]}Z" how="m-g">
  <point lat="{lat}" lon="{lon}" hae="0" ce="5" le="5"/>
  <detail>
    <contact callsign="{name}"/>
    <remarks>Firefighter Telemetry</remarks>
    <track course="0.0" speed="0.0"/>
  </detail>
</event>'''
    
    return cot_xml