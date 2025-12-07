import psycopg2
import os
from fpdf import FPDF
from fpdf.enums import XPos, YPos
from datetime import datetime
from lib.utils import load_config

class PDFReport(FPDF):
    def header(self):
        # Nagłówek
        self.set_font('Helvetica', 'B', 15) # Używamy Helvetica zamiast Arial
        # new_x="LMARGIN", new_y="NEXT" oznacza: po napisaniu przejdź do lewego marginesu w nowej linii
        self.cell(0, 10, 'RAPORT TELEMETRYCZNY PSP', new_x=XPos.LMARGIN, new_y=YPos.NEXT, align='C')
        
        self.set_font('Helvetica', 'I', 10)
        self.cell(0, 10, f'Data generowania: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}', new_x=XPos.LMARGIN, new_y=YPos.NEXT, align='C')
        
        self.line(10, 30, 200, 30)
        self.ln(10)

    def footer(self):
        # Stopka
        self.set_y(-15)
        self.set_font('Helvetica', 'I', 8)
        self.cell(0, 10, f'Strona {self.page_no()}/{{nb}}', align='C')

    def chapter_title(self, label):
        # Tytuł rozdziału
        self.set_font('Helvetica', 'B', 12)
        self.set_fill_color(255, 204, 153)
        self.cell(0, 10, label, new_x=XPos.LMARGIN, new_y=YPos.NEXT, align='L', fill=True)
        self.ln(4)

    def chapter_body(self, body):
        # Treść rozdziału
        self.set_font('Helvetica', '', 11)
        self.multi_cell(0, 10, body)
        self.ln()

def pl(text):
    """Usuwa polskie znaki dla standardowej czcionki Helvetica."""
    replacements = {
        'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z',
        'Ą': 'A', 'Ć': 'C', 'Ę': 'E', 'Ł': 'L', 'Ń': 'N', 'Ó': 'O', 'Ś': 'S', 'Ź': 'Z', 'Ż': 'Z'
    }
    for k, v in replacements.items():
        text = text.replace(k, v)
    return text

def get_db_data(cur):
    data = {}
    # 1. Podsumowanie ogólne
    cur.execute("SELECT COUNT(*) FROM actions;")
    data['total_actions'] = cur.fetchone()[0]

    cur.execute("SELECT COUNT(*) FROM firefighters WHERE global_status = 'ACTIVE';")
    data['active_firefighters'] = cur.fetchone()[0]

    # 2. Statystyki alarmów
    cur.execute("""
        SELECT severity, code, COUNT(*) 
        FROM alerts 
        GROUP BY severity, code 
        ORDER BY severity, count DESC;
    """)
    data['alerts_stats'] = cur.fetchall()

    # 3. Statystyki strażaków
    cur.execute("""
        SELECT 
            f.last_name, 
            f.first_name,
            COUNT(t.id) as samples_count,
            ROUND(AVG(t.heart_rate), 1) as avg_hr,
            MAX(t.heart_rate) as max_hr,
            MIN(t.air_left) as min_air,
            ROUND(AVG(t.ambient_temperature), 1) as avg_temp
        FROM firefighters f
        JOIN telemetry_samples t ON f.id = t.firefighter_id
        GROUP BY f.id
        ORDER BY f.last_name;
    """)
    data['firefighter_stats'] = cur.fetchall()
    return data

def generate_report():
    # Konfiguracja
    try:
        config = load_config("config.ini")
        db_params = config["DATABASE"]
    except Exception as e:
        print(f"Błąd konfiguracji: {e}")
        return

    conn = None
    try:
        conn = psycopg2.connect(**db_params)
        cur = conn.cursor()
        cur.execute("SET search_path TO psp_telemetry, public;")

        # Pobranie danych
        stats = get_db_data(cur)

        # Generowanie PDF
        pdf = PDFReport()
        pdf.alias_nb_pages()
        pdf.add_page()

        # SEKCJA 1
        pdf.chapter_title(pl("1. PODSUMOWANIE OPERACYJNE"))
        summary_text = (
            f"Laczna liczba zarejestrowanych akcji: {stats['total_actions']}\n"
            f"Liczba aktywnych strazakow w systemie: {stats['active_firefighters']}\n"
            f"Raport obejmuje pelny zakres danych historycznych zgromadzonych w bazie."
        )
        pdf.chapter_body(summary_text)

        # SEKCJA 2 - TABELA
        pdf.chapter_title(pl("2. SZCZEGOLOWE STATYSTYKI PERSONELU"))
        
        pdf.set_font('Helvetica', 'B', 10)
        pdf.set_fill_color(230, 230, 230)
        
        w = [40, 40, 25, 25, 25, 30] 
        headers = ["Nazwisko", "Imie", "Sred. HR", "Max HR", "Min Pow.", "Sred. Temp"]
        
        for i, h in enumerate(headers):
            # new_x="RIGHT", new_y="TOP" oznacza: kursor przesuwa się w prawo (kolejna komórka)
            pdf.cell(w[i], 7, pl(h), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C', fill=True)
        pdf.ln() # Nowa linia po nagłówku

        pdf.set_font('Helvetica', '', 10)
        for row in stats['firefighter_stats']:
            pdf.cell(w[0], 6, pl(str(row[0])), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP)
            pdf.cell(w[1], 6, pl(str(row[1])), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP)
            pdf.cell(w[2], 6, str(row[3]), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
            pdf.cell(w[3], 6, str(row[4]), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
            pdf.cell(w[4], 6, f"{row[5]}%", border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
            pdf.cell(w[5], 6, f"{row[6]} C", border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
            pdf.ln()
        
        pdf.ln(10)

        # SEKCJA 3
        pdf.chapter_title(pl("3. HISTORIA ZDARZEN I ALERTOW"))
        
        pdf.set_font('Helvetica', 'B', 10)
        pdf.cell(60, 7, pl("Typ Zdarzenia (Kod)"), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C', fill=True)
        pdf.cell(40, 7, pl("Poziom"), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C', fill=True)
        pdf.cell(30, 7, pl("Liczba"), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C', fill=True)
        pdf.ln()

        pdf.set_font('Helvetica', '', 10)
        if not stats['alerts_stats']:
            pdf.cell(130, 6, pl("Brak zarejestrowanych alertow w historii."), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
        else:
            for alert in stats['alerts_stats']:
                severity = alert[0]
                code = alert[1]
                count = alert[2]
                
                pdf.cell(60, 6, pl(code), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP)
                pdf.cell(40, 6, pl(severity), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
                pdf.cell(30, 6, str(count), border=1, new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')
                pdf.ln()

        # Zapis i wyświetlenie ścieżki
        filename = f"raport_psp_{datetime.now().strftime('%Y%m%d_%H%M')}.pdf"
        
        # Pobranie pełnej ścieżki (Absolute Path)
        current_dir = os.getcwd()
        full_path = os.path.join(current_dir, filename)
        
        pdf.output(filename)
        
        print("\n" + "="*50)
        print(f"Raport został wygenerowany.")

    except psycopg2.Error as e:
        print(f"Błąd bazy danych: {e}")
    except Exception as e:
        print(f"Inny błąd: {e}")
    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    generate_report()