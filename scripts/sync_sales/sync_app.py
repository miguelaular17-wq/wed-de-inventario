import os
import sys
import json
import time
import threading
from datetime import datetime
import tkinter as tk
from tkinter import ttk, messagebox, scrolledtext
import pyodbc
import pandas as pd

if sys.platform == "win32":
    import winreg
else:
    winreg = None

# Database drivers and config paths
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(sys.executable)
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))

CONFIG_PATH = os.path.join(BASE_DIR, "config.json")
STATE_PATH = os.path.join(BASE_DIR, "state.json")

# Try importing psycopg2
try:
    import psycopg2
    import psycopg2.extras
except ImportError:
    psycopg2 = None

class SyncApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Sincronizador de Inventario y Ventas - JRZ-TECH")
        self.root.geometry("750x650")
        self.root.minsize(700, 550)
        
        # State variables
        self.is_syncing = False
        self.stop_event = threading.Event()
        self.sync_thread = None
        
        # Load configs
        self.config = self.load_config()
        self.state = self.load_state()
        
        # Create UI
        self.create_widgets()
        self.load_values_into_ui()
        self.log("Aplicación iniciada. Lista para trabajar.")
        
        # Check autostart flag
        if "--autostart" in sys.argv:
            self.root.iconify()  # Minimize window on automatic startup
            self.root.after(1000, self.toggle_sync)
        
    def log(self, message):
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.console.insert(tk.END, f"[{timestamp}] {message}\n")
        self.console.see(tk.END)

    def load_config(self):
        if not os.path.exists(CONFIG_PATH):
            # Create a default config if missing
            default_cfg = {
                "sede": "JRZ",
                "interval_seconds": 1800,
                "start_time": "08:00:00",
                "billing_db": {
                    "driver": "{SQL Server}",
                    "server": "localhost\\SQLEXPRESS",
                    "database": "suitedb_centro",
                    "trusted_connection": True,
                    "query": "SELECT h.fecha_emision, i.articulo, i.cantidad FROM [dbo].[documentos_venta] h WITH (NOLOCK) INNER JOIN [dbo].[documentos_venta_items] i WITH (NOLOCK) ON h.tipo_documento = i.tipo_documento AND h.numero_documento = i.numero_documento WHERE h.tipo_documento = 'FAC' AND h.fecha_emision > ? ORDER BY h.fecha_emision ASC"
                },
                "web_db": {
                    "host": "aws-1-us-west-2.pooler.supabase.com",
                    "port": 6543,
                    "database": "postgres",
                    "user": "postgres.PONER_SU_ID_AQUI",
                    "password": "PONER_PASSWORD_AQUI"
                }
            }
            with open(CONFIG_PATH, "w", encoding="utf-8") as f:
                json.dump(default_cfg, f, indent=2)
            return default_cfg
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            return json.load(f)

    def load_state(self):
        if not os.path.exists(STATE_PATH):
            current_time = datetime.now().strftime("%Y-%m-%d 08:00:00.000")
            default_state = {"last_processed_timestamp": current_time}
            self.save_state(default_state)
            return default_state
        with open(STATE_PATH, "r", encoding="utf-8") as f:
            return json.load(f)

    def save_config(self):
        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(self.config, f, indent=2)

    def save_state(self, state):
        with open(STATE_PATH, "w", encoding="utf-8") as f:
            json.dump(state, f, indent=2)

    def create_widgets(self):
        # Apply visual styles
        style = ttk.Style()
        style.theme_use('vista' if 'vista' in style.theme_names() else 'clam')
        
        # Main Container
        main_frame = ttk.Frame(self.root, padding="15")
        main_frame.pack(fill=tk.BOTH, expand=True)
        
        # Title Label
        title_label = ttk.Label(
            main_frame, 
            text="Sincronizador Automático de Ventas", 
            font=("Helvetica", 16, "bold"), 
            foreground="#1e3a8a"
        )
        title_label.pack(anchor=tk.W, pady=(0, 15))
        
        # Configuration Panel (LabelFrame)
        config_frame = ttk.LabelFrame(main_frame, text=" Configuración ", padding="15")
        config_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Configuration Grid
        # Row 0: Sede
        ttk.Label(config_frame, text="Sede Actual:").grid(row=0, column=0, sticky=tk.W, pady=5)
        self.sede_var = tk.StringVar()
        self.sede_combo = ttk.Combobox(
            config_frame, 
            textvariable=self.sede_var, 
            values=["JRZ", "DORAL", "VIRTUDES", "ZAMORA", "CENTRO", "SAMBIL"], 
            state="readonly",
            width=15
        )
        self.sede_combo.grid(row=0, column=1, sticky=tk.W, pady=5, padx=10)
        
        # Row 1: SQL Server
        ttk.Label(config_frame, text="Servidor SQL (Instancia):").grid(row=1, column=0, sticky=tk.W, pady=5)
        self.sql_server_var = tk.StringVar()
        self.sql_server_entry = ttk.Entry(config_frame, textvariable=self.sql_server_var, width=30)
        self.sql_server_entry.grid(row=1, column=1, sticky=tk.W, pady=5, padx=10)
        
        # Row 2: SQL Database
        ttk.Label(config_frame, text="Base de Datos SQL:").grid(row=2, column=0, sticky=tk.W, pady=5)
        self.sql_database_var = tk.StringVar()
        self.sql_database_entry = ttk.Entry(config_frame, textvariable=self.sql_database_var, width=30)
        self.sql_database_entry.grid(row=2, column=1, sticky=tk.W, pady=5, padx=10)
        # Row 3: Auth type
        self.sql_auth_var = tk.BooleanVar(value=True)
        self.sql_auth_chk = ttk.Checkbutton(
            config_frame,
            text="Usar Autenticación de Windows",
            variable=self.sql_auth_var,
            command=self.on_auth_changed
        )
        self.sql_auth_chk.grid(row=3, column=0, columnspan=2, sticky=tk.W, pady=5)
        
        # Row 4: User and Password (only visible if auth is false)
        self.auth_frame = ttk.Frame(config_frame)
        self.auth_frame.grid(row=4, column=0, columnspan=4, sticky=tk.W, pady=2)
        
        ttk.Label(self.auth_frame, text="Usuario SQL:").pack(side=tk.LEFT, padx=(0,5))
        self.sql_user_var = tk.StringVar()
        self.sql_user_entry = ttk.Entry(self.auth_frame, textvariable=self.sql_user_var, width=15)
        self.sql_user_entry.pack(side=tk.LEFT, padx=(0,15))
        
        ttk.Label(self.auth_frame, text="Clave:").pack(side=tk.LEFT, padx=(0,5))
        self.sql_pass_var = tk.StringVar()
        self.sql_pass_entry = ttk.Entry(self.auth_frame, textvariable=self.sql_pass_var, width=15, show="*")
        self.sql_pass_entry.pack(side=tk.LEFT)
        
        # Row 5: Interval in minutes
        ttk.Label(config_frame, text="Intervalo de consulta (minutos):").grid(row=5, column=0, sticky=tk.W, pady=5)
        self.interval_var = tk.IntVar()
        self.interval_entry = ttk.Entry(config_frame, textvariable=self.interval_var, width=10)
        self.interval_entry.grid(row=5, column=1, sticky=tk.W, pady=5, padx=10)
        
        # Row 6: Startup Checkbox
        self.startup_var = tk.BooleanVar()
        self.startup_chk = ttk.Checkbutton(
            config_frame, 
            text="Iniciar con Windows automáticamente al arrancar el sistema", 
            variable=self.startup_var,
            command=self.on_startup_changed
        )
        self.startup_chk.grid(row=6, column=0, columnspan=4, sticky=tk.W, pady=5)
        
        # Buttons / Actions
        action_frame = ttk.Frame(main_frame)
        action_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Button: Generate report once
        self.btn_report = ttk.Button(
            action_frame, 
            text="Generar Reporte General (Excel/CSV)", 
            command=self.run_report_once,
            width=35
        )
        self.btn_report.pack(side=tk.LEFT, padx=(0, 10))
        
        # Button: Toggle Sync
        self.btn_sync = ttk.Button(
            action_frame, 
            text="Iniciar Sincronización Automática", 
            command=self.toggle_sync,
            width=30
        )
        self.btn_sync.pack(side=tk.LEFT)
        
        # Button: Save Config Only
        self.btn_save = ttk.Button(
            action_frame,
            text="Guardar Configuración",
            command=self.save_config_manual,
            width=20
        )
        self.btn_save.pack(side=tk.LEFT, padx=(10, 0))
        # Button: Inspect SQL Server columns
        self.btn_inspect = ttk.Button(
            action_frame,
            text="Inspeccionar Columnas SQL",
            command=self.inspect_sqlserver_columns,
            width=28
        )
        self.btn_inspect.pack(side=tk.LEFT, padx=(10, 0))
        
        # Log Console
        console_frame = ttk.LabelFrame(main_frame, text=" Consola de Logs / Actividad ", padding="5")
        console_frame.pack(fill=tk.BOTH, expand=True)
        
        self.console = scrolledtext.ScrolledText(
            console_frame, 
            wrap=tk.WORD, 
            font=("Consolas", 9), 
            bg="#0f172a", 
            fg="#e2e8f0"
        )
        self.console.pack(fill=tk.BOTH, expand=True)
        
        
    def on_auth_changed(self):
        if self.sql_auth_var.get():
            # Hide auth frame if windows auth
            self.auth_frame.grid_remove()
        else:
            self.auth_frame.grid()
            
    def load_values_into_ui(self):
        # Load from config
        self.sede_var.set(self.config.get("sede", "JRZ"))
        
        # Load SQL server config
        billing_config = self.config.get("billing_db", {})
        self.sql_server_var.set(billing_config.get("server", "localhost\\SQLEXPRESS"))
        self.sql_database_var.set(billing_config.get("database", "suitedb_centro"))
        
        is_trusted = billing_config.get("trusted_connection", True)
        self.sql_auth_var.set(is_trusted)
        self.sql_user_var.set(billing_config.get("user", ""))
        self.sql_pass_var.set(billing_config.get("password", ""))
        self.on_auth_changed()
        
        interval_min = int(self.config.get("interval_seconds", 1800) / 60)
        self.interval_var.set(interval_min)
        
        # Load startup state
        self.startup_var.set(self.is_startup_enabled())
        
        # State timestamp is loaded into state dictionary on startup,
        # we don't display it in the UI anymore to avoid confusion.
        
    def save_ui_values_to_config(self):
        # Retrieve values from UI
        sede = self.sede_var.get()
        sql_server = self.sql_server_var.get().strip()
        sql_database = self.sql_database_var.get().strip()
        sql_auth_trusted = self.sql_auth_var.get()
        sql_user = self.sql_user_var.get().strip()
        sql_pass = self.sql_pass_var.get().strip()
        
        try:
            interval_min = self.interval_var.get()
            if interval_min <= 0:
                raise ValueError()
        except Exception:
            messagebox.showerror("Error", "El intervalo de consulta debe ser un número entero mayor a 0.")
            return False
            
        # Update config dictionary
        self.config["sede"] = sede
        self.config["interval_seconds"] = interval_min * 60
        
        if "billing_db" not in self.config:
            self.config["billing_db"] = {}
        self.config["billing_db"]["server"] = sql_server
        self.config["billing_db"]["database"] = sql_database
        self.config["billing_db"]["trusted_connection"] = sql_auth_trusted
        if not sql_auth_trusted:
            self.config["billing_db"]["user"] = sql_user
            self.config["billing_db"]["password"] = sql_pass
        else:
            # Clear them if trusted
            self.config["billing_db"].pop("user", None)
            self.config["billing_db"].pop("password", None)
        
        self.save_config()
        
        return True
        
    def save_config_manual(self):
        if self.save_ui_values_to_config():
            messagebox.showinfo("Guardado", "La configuración de Sede y Servidor SQL se ha guardado correctamente.")

    def is_startup_enabled(self):
        if winreg is None:
            return False
        try:
            key = winreg.OpenKey(
                winreg.HKEY_CURRENT_USER,
                r"Software\Microsoft\Windows\CurrentVersion\Run",
                0,
                winreg.KEY_READ
            )
            val, _ = winreg.QueryValueEx(key, "JRZInventorySync")
            winreg.CloseKey(key)
            return True
        except FileNotFoundError:
            return False
        except Exception as e:
            self.log(f"Error al comprobar inicio automático: {str(e)}")
            return False

    def on_startup_changed(self):
        if winreg is None:
            messagebox.showwarning("Advertencia", "El inicio automático solo está disponible en Windows.")
            self.startup_var.set(False)
            return
        
        enable = self.startup_var.get()
        try:
            key = winreg.OpenKey(
                winreg.HKEY_CURRENT_USER,
                r"Software\Microsoft\Windows\CurrentVersion\Run",
                0,
                winreg.KEY_WRITE
            )
            if enable:
                if getattr(sys, 'frozen', False):
                    # If compiled with PyInstaller, the executable is sys.executable itself
                    cmd = f'"{sys.executable}" --autostart'
                else:
                    py_exe = sys.executable
                    # Use pythonw.exe if running from python.exe so it runs windowless for console
                    if py_exe.lower().endswith("python.exe"):
                        pyw_exe = py_exe[:-10] + "pythonw.exe"
                        if os.path.exists(pyw_exe):
                            py_exe = pyw_exe
                    
                    script_path = os.path.abspath(__file__)
                    cmd = f'"{py_exe}" "{script_path}" --autostart'
                
                winreg.SetValueEx(key, "JRZInventorySync", 0, winreg.REG_SZ, cmd)
                self.log("Inicio automático habilitado con comando:")
                self.log(f"  {cmd}")
            else:
                try:
                    winreg.DeleteValue(key, "JRZInventorySync")
                    self.log("Inicio automático deshabilitado.")
                except FileNotFoundError:
                    pass
            winreg.CloseKey(key)
        except Exception as e:
            self.log(f"Error al configurar inicio automático: {str(e)}")
            self.startup_var.set(not enable)
            
    def get_sql_connection(self):
        """Helper to create the SQL Server connection string from config"""
        billing = self.config["billing_db"]
        driver = billing.get("driver", "{SQL Server}")
        server = billing.get("server", "localhost\\SQLEXPRESS")
        database = billing.get("database", "suitedb_centro")
        
        conn_str = f"DRIVER={driver};SERVER={server};DATABASE={database};"
        
        if billing.get("trusted_connection", True):
            conn_str += "Trusted_Connection=yes;"
            self.log(f"Intentando conectar a SQL Server: DRIVER={driver};SERVER={server};DATABASE={database};Trusted_Connection=yes;")
        else:
            user = billing.get("user", "")
            password = billing.get("password", "")
            conn_str += f"UID={user};PWD={password};"
            self.log(f"Intentando conectar a SQL Server: DRIVER={driver};SERVER={server};DATABASE={database};UID={user};PWD=******;")
            
        return pyodbc.connect(conn_str, timeout=15)

    def run_report_once(self):
        if not self.save_ui_values_to_config():
            return
            
        self.log("Generando reporte de stock y ventas consolidado...")
        self.btn_report.config(state=tk.DISABLED)
        
        # Run report generation in a separate thread to keep UI interactive
        threading.Thread(target=self._report_thread_worker, daemon=True).start()
        
    def _report_thread_worker(self):
        try:
            conn = self.get_sql_connection()
            
            query = """
                SELECT 
                    a.codigo AS [COD CENTRO],
                    a.descripcion AS [PRODUCTO],
                    'N/A' AS [Proveedor],
                    CAST(a.existencia AS INT) AS [Centro existencia],
                    COALESCE(sales15.total_qty, 0) AS [Centro promedio 15 dias (60d)],
                    COALESCE(sales60.total_qty, 0) AS [Centro ventas],
                    CONVERT(VARCHAR(10), a.fecha_ultima_venta, 120) AS [Centro ultima venta],
                    CONVERT(VARCHAR(10), a.fecha_ultima_compra, 120) AS [Centro ultima compra]
                FROM [dbo].[articulos] a WITH (NOLOCK)
                
                -- Sales 15d
                LEFT JOIN (
                    SELECT vi.articulo, SUM(vi.cantidad) as total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    INNER JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento 
                       AND v.numero_documento = vi.numero_documento
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -15, GETDATE())
                    GROUP BY vi.articulo
                ) sales15 ON sales15.articulo = a.codigo
                
                -- Sales 60d
                LEFT JOIN (
                    SELECT vi.articulo, SUM(vi.cantidad) as total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    INNER JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento 
                       AND v.numero_documento = vi.numero_documento
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -60, GETDATE())
                    GROUP BY vi.articulo
                ) sales60 ON sales60.articulo = a.codigo
                
                WHERE a.existencia > 0 
                   OR sales15.total_qty > 0 
                   OR sales60.total_qty > 0
                ORDER BY [Centro ventas] DESC
            """
            
            df = pd.read_sql(query, conn)
            conn.close()
            
            log_dir = "c:/Users/freyg/Downloads"
            csv_filename = f"Reporte_Stock_Ventas_{self.config['sede']}.csv"
            output_path = os.path.join(log_dir, csv_filename)
            
            df.to_csv(output_path, index=False, encoding="utf-8")
            self.log(f"Reporte general generado exitosamente: {csv_filename} ({len(df)} productos).")
            self.log(f"Guardado en: {output_path}")
            
        except Exception as e:
            self.log(f"Error al generar reporte: {str(e)}")
        finally:
            self.root.after(0, lambda: self.btn_report.config(state=tk.NORMAL))

    def inspect_sqlserver_columns(self):
        """Connects to SQL Server and prints all columns of the articulos table
        so the user can identify which fields hold categoria, subcategoria and proveedor."""
        self.btn_inspect.config(state=tk.DISABLED)
        self.log("Inspeccionando columnas de [dbo].[articulos] en SQL Server...")
        threading.Thread(target=self._inspect_sqlserver_columns_worker, daemon=True).start()

    def _inspect_sqlserver_columns_worker(self):
        try:
            conn = self.get_sql_connection()
            cursor = conn.cursor()

            # Get column names from articulos
            cursor.execute("""
                SELECT COLUMN_NAME, DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'articulos'
                ORDER BY ORDINAL_POSITION
            """)
            cols = cursor.fetchall()

            self.log(f"  Columnas encontradas en [dbo].[articulos] ({len(cols)} total):")
            self.log("  " + "-" * 50)
            for col in cols:
                self.log(f"  {col[0]:35s} | tipo: {col[1]}")
            self.log("  " + "-" * 50)

            # Also show a sample row to help identify values
            cursor.execute("SELECT TOP 1 * FROM [dbo].[articulos] WITH (NOLOCK) WHERE existencia > 0")
            sample_cols = [desc[0] for desc in cursor.description]
            sample_row  = cursor.fetchone()
            if sample_row:
                self.log("  Ejemplo de fila con existencia > 0:")
                for name, val in zip(sample_cols, sample_row):
                    if val is not None and str(val).strip() not in ('', '0', '0.0', '0.00'):
                        self.log(f"    {name:35s} = {str(val)[:60]}")

            # --- Buscar tabla de categorías ---
            self.log("")
            self.log("  Buscando tablas de categorías en SQL Server...")
            cursor.execute("""
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'
                  AND (
                      TABLE_NAME LIKE '%categ%'
                   OR TABLE_NAME LIKE '%grupo%'
                   OR TABLE_NAME LIKE '%familia%'
                   OR TABLE_NAME LIKE '%subcateg%'
                   OR TABLE_NAME LIKE '%clasif%'
                  )
                ORDER BY TABLE_NAME
            """)
            cat_tables = cursor.fetchall()

            if cat_tables:
                self.log(f"  Tablas relacionadas a categorías encontradas: {len(cat_tables)}")
                for t in cat_tables:
                    tname = t[0]
                    self.log(f"")
                    self.log(f"  >>> Tabla: [dbo].[{tname}]")
                    # Show columns
                    cursor.execute(f"""
                        SELECT COLUMN_NAME, DATA_TYPE
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_NAME = '{tname}'
                        ORDER BY ORDINAL_POSITION
                    """)
                    tcols = cursor.fetchall()
                    for tc in tcols:
                        self.log(f"      {tc[0]:30s} | tipo: {tc[1]}")
                    # Show sample row
                    try:
                        cursor.execute(f"SELECT TOP 3 * FROM [dbo].[{tname}] WITH (NOLOCK)")
                        trows = cursor.fetchall()
                        tdesc = [d[0] for d in cursor.description]
                        if trows:
                            self.log(f"      Ejemplo ({len(trows)} filas):")
                            for tr in trows:
                                vals = " | ".join(f"{tdesc[i]}={str(v)[:30]}" for i, v in enumerate(tr) if v is not None and str(v).strip())
                                self.log(f"        {vals[:120]}")
                    except Exception:
                        pass
            else:
                self.log("  No se encontraron tablas de categorías con nombres estándar.")
                self.log("  El código de categoría en 'articulos.categoria' es: SCAT115")
                self.log("  Busca manualmente en SQL Server qué tabla tiene la descripción de ese código.")

            conn.close()
            self.log("")
            self.log("  ✓ Inspección completada.")

        except Exception as e:
            self.log(f"  [Error] No se pudo inspeccionar SQL Server: {str(e)})")
        finally:
            self.root.after(0, lambda: self.btn_inspect.config(state=tk.NORMAL))

    def toggle_sync(self):
        if self.is_syncing:
            # Stop syncing
            self.log("Deteniendo sincronización automática...")
            self.stop_event.set()
            self.btn_sync.config(text="Iniciar Sincronización Automática", state=tk.DISABLED)
        else:
            # Start syncing
            if not self.save_ui_values_to_config():
                return
                
            if not psycopg2:
                messagebox.showerror("Error", "La librería 'psycopg2-binary' no está instalada. Ejecuta 'pip install psycopg2-binary'")
                return
                
            self.is_syncing = True
            self.stop_event.clear()
            self.btn_sync.config(text="Detener Sincronización Automática")
            self.log(f"Sincronización iniciada. Ejecución cada {self.interval_var.get()} minutos.")
            
            # Start the background sync loop thread
            self.sync_thread = threading.Thread(target=self._sync_loop_worker, daemon=True)
            self.sync_thread.start()

    def _sync_loop_worker(self):
        interval_seconds = self.config["interval_seconds"]
        last_snapshot_date = None  # Track which day we last ran the snapshot
        
        while not self.stop_event.is_set():
            today = datetime.now().strftime("%Y-%m-%d")
            success = True

            # At the start of each new day (or first run), push full inventory snapshot
            if last_snapshot_date != today:
                self.log("=" * 60)
                self.log(f"NUEVO DÍA DETECTADO ({today}). Actualizando stock de productos conocidos...")
                self.log("=" * 60)
                success = self._execute_daily_snapshot()
                
                if success:
                    # If first run ever, set baseline to now so we don't pull from 2020
                    state = self.load_state()
                    if not state.get("last_processed_timestamp"):
                        state["last_processed_timestamp"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S.000")
                        self.save_state(state)
                        
                    last_snapshot_date = today

            if success:
                success = self._execute_sync_cycle()
            
            # Sleep in 1-second chunks to react fast to the stop event
            # If failed, retry in 60 seconds instead of the full interval
            current_interval = interval_seconds if success else 60
            
            for _ in range(current_interval):
                if self.stop_event.is_set():
                    break
                time.sleep(1)
                
        # Clean up once stopped
        self.is_syncing = False
        self.root.after(0, self._on_sync_stopped_ui)

    def _on_sync_stopped_ui(self):
        self.btn_sync.config(text="Iniciar Sincronización Automática", state=tk.NORMAL)
        self.log("Sincronización automática detenida.")

    def _execute_daily_snapshot(self):
        """Pulls the full inventory snapshot from SQL Server and pushes it to Supabase.
        This runs once per day (at opening time) to set the baseline stock for the web app."""
        billing_conn = None
        web_conn = None
        try:
            sede = self.config.get("sede", "JRZ")
            web = self.config["web_db"]
            
            self.log("[Snapshot] 1. Conectando a Supabase para ver qué productos ya existen...")
            for attempt in range(3):
                try:
                    web_conn = psycopg2.connect(
                        host=web["host"], port=web["port"], database=web["database"],
                        user=web["user"], password=web["password"], sslmode="require",
                        connect_timeout=30
                    )
                    break
                except Exception as e:
                    if attempt == 2: raise e
                    self.log(f"[Snapshot] Falló conexión Supabase (intento {attempt+1}). Reintentando...")
                    time.sleep(2)
            
            wc = web_conn.cursor()
            wc.execute("SELECT codigo, nombre, id FROM inventario_v2.productos;")
            supabase_rows = wc.fetchall()
            prod_map = {}
            for r in supabase_rows:
                db_id = r[2]
                if r[0]:
                    full_code = str(r[0]).strip()
                    prod_map[full_code] = db_id # Agregar el código original sin dividir
                    for part in full_code.replace(' ', '').split('/'):
                        if part:
                            prod_map[part] = db_id
            
            name_map = {}
            for r in supabase_rows:
                if r[1]:
                    name_map[str(r[1]).strip().lower()] = (r[2], str(r[0]).strip() if r[0] else "")

            
            if not prod_map:
                self.log("[Snapshot] No hay ningún producto registrado en la web todavía. Omitiendo actualización masiva.")
                return

            self.log(f"[Snapshot] La web tiene {len(prod_map)} productos registrados. Consultando SQL Server...")
            
            billing_conn = self.get_sql_connection()

            snapshot_query = f"""
                SELECT
                    a.codigo                                         AS codigo,
                    CAST(ISNULL(ex.actual, 0) AS INT)                AS existencia,
                    ISNULL(s15.total_qty, 0)                         AS ventas_15d,
                    ISNULL(s60.total_qty, 0)                         AS ventas_60d,
                    CONVERT(VARCHAR(19), a.fecha_ultima_venta,  120) AS ultima_venta,
                    CONVERT(VARCHAR(19), a.fecha_ultima_compra, 120) AS ultima_compra,
                    a.descripcion                                    AS descripcion
                FROM [dbo].[articulos] a WITH (NOLOCK)
                LEFT JOIN [dbo].[existencias] ex WITH (NOLOCK) 
                    ON a.id = ex.id_articulo AND ex.almacen = '01'
                LEFT JOIN (
                    SELECT vi.articulo, SUM(vi.cantidad) AS total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento
                       AND v.numero_documento = vi.numero_documento
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -15, GETDATE())
                    GROUP BY vi.articulo
                ) s15 ON s15.articulo = a.codigo
                LEFT JOIN (
                    SELECT vi.articulo, SUM(vi.cantidad) AS total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento
                       AND v.numero_documento = vi.numero_documento
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -60, GETDATE())
                    GROUP BY vi.articulo
                ) s60 ON s60.articulo = a.codigo
                WHERE a.codigo IS NOT NULL AND LTRIM(RTRIM(a.codigo)) <> ''
            """

            cursor = billing_conn.cursor()
            cursor.execute(snapshot_query)
            rows = cursor.fetchall()
            self.log(f"[Snapshot] {len(rows)} totales obtenidos de SQL Server.")

            if not rows:
                return True

            from psycopg2.extras import execute_batch

            stock_tuples = []
            ventas_tuples = []
            skipped = 0
            
            pid_stock_map = {}
            pid_ventas_map = {}

            for row in rows:
                codigo = str(row[0]).strip()
                nombre_local = str(row[6]).strip() if len(row) > 6 and row[6] else None
                
                # ¡LA MAGIA AQUÍ! Solo nos importan los productos que ya existen en Supabase
                if codigo in prod_map:
                    pid = prod_map[codigo]
                elif nombre_local and nombre_local.lower() in name_map:
                    pid, current_codigo = name_map[nombre_local.lower()]
                    
                    # Split for robust checking, but also check if the full code is already there
                    parts = current_codigo.replace(' ', '').split('/')
                    if codigo not in parts and codigo not in current_codigo:
                        new_codigo = f"{current_codigo} / {codigo}" if current_codigo else codigo
                        self.log(f"[Snapshot Auto-Heal] Producto '{nombre_local}' encontrado por nombre. Agregando código '{codigo}' a la web.")
                        # Auto-heal en Supabase (ignora si hay conflicto)
                        try:
                            wc.execute(
                                "UPDATE inventario_v2.productos SET codigo = %s, updated_at = NOW() WHERE id = %s;",
                                (new_codigo, pid)
                            )
                            name_map[nombre_local.lower()] = (pid, new_codigo)
                        except Exception as e:
                            self.log(f"[Snapshot Auto-Heal] Error actualizando código: {e}")
                            web_conn.rollback() # Rollback the failed update but keep the transaction going
                            pass
                    
                    prod_map[codigo] = pid # Update local map
                else:
                    skipped += 1
                    continue
                    
                existencia    = max(0, int(row[1]) if row[1] else 0)
                ventas_15d    = float(row[2]) if row[2] else 0.0
                ventas_60d    = float(row[3]) if row[3] else 0.0
                ultima_venta  = str(row[4])   if row[4] else None
                ultima_compra = str(row[5])   if row[5] else None

                pid_stock_map[pid] = pid_stock_map.get(pid, 0) + existencia
                
                if pid not in pid_ventas_map:
                    pid_ventas_map[pid] = {
                        'ventas_60d': 0.0,
                        'ventas_15d': 0.0,
                        'ultima_venta': None,
                        'ultima_compra': None
                    }
                
                v_data = pid_ventas_map[pid]
                v_data['ventas_60d'] += ventas_60d
                v_data['ventas_15d'] += ventas_15d
                
                # Para fechas, tomamos la más reciente
                if ultima_venta:
                    if not v_data['ultima_venta'] or ultima_venta > v_data['ultima_venta']:
                        v_data['ultima_venta'] = ultima_venta
                if ultima_compra:
                    if not v_data['ultima_compra'] or ultima_compra > v_data['ultima_compra']:
                        v_data['ultima_compra'] = ultima_compra

            # Construir las tuplas agregadas
            for pid, stock in pid_stock_map.items():
                stock_tuples.append((pid, sede, stock))
            
            for pid, v_data in pid_ventas_map.items():
                venta_promedio_15d = int(round(v_data['ventas_15d'] / 15)) if v_data['ventas_15d'] else 0
                ventas_tuples.append((
                    pid, sede, v_data['ventas_60d'], venta_promedio_15d, 
                    v_data['ultima_venta'], v_data['ultima_compra']
                ))

            # Helper to execute in small committed chunks to avoid pooler timeouts
            def batch_execute_and_commit(query, data_list, chunk_size=1000):
                for i in range(0, len(data_list), chunk_size):
                    chunk = data_list[i:i + chunk_size]
                    execute_batch(wc, query, chunk, page_size=chunk_size)
                    web_conn.commit()

            if not stock_tuples:
                self.log(f"[Snapshot] No hay datos conocidos para actualizar (omitidos {skipped} desconocidos).")
                return True

            self.log(f"[Snapshot] Preparados {len(stock_tuples)} productos conocidos. Enviando...")
                    
            # 1. Batch Upsert stock
            upsert_stock_query = """
                INSERT INTO inventario_v2.stock_actual (producto_id, sede, existencia, updated_at)
                VALUES (%s, %s, %s, NOW())
                ON CONFLICT (producto_id, sede) DO UPDATE
                    SET existencia = EXCLUDED.existencia, updated_at = NOW();
            """
            batch_execute_and_commit(upsert_stock_query, stock_tuples)

            # 2. Batch Upsert ventas
            upsert_ventas_query = """
                INSERT INTO inventario_v2.ventas_historicas
                    (producto_id, sede, ventas_60d, venta_promedio, ultima_venta, ultima_compra, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, NOW())
                ON CONFLICT (producto_id, sede) DO UPDATE
                    SET ventas_60d      = EXCLUDED.ventas_60d,
                        venta_promedio  = EXCLUDED.venta_promedio,
                        ultima_venta    = EXCLUDED.ultima_venta,
                        ultima_compra   = EXCLUDED.ultima_compra,
                        updated_at      = NOW();
            """
            batch_execute_and_commit(upsert_ventas_query, ventas_tuples)
            
            # Log successful sync to sync_logs
            updated = len(stock_tuples)
            meta_json = json.dumps({"omitidos": skipped})
            wc.execute(
                """
                INSERT INTO inventario_v2.sync_logs (sede, tipo, registros_procesados, metadata, created_at)
                VALUES (%s, 'APERTURA', %s, %s, NOW())
                """,
                (sede, updated, meta_json)
            )
            web_conn.commit()
            
            updated = len(stock_tuples)
            self.log(f"[Snapshot] ✓ Reporte de apertura cargado: {updated} productos actualizados, {skipped} omitidos.")
            self.log(f"[Snapshot] La web ahora refleja el inventario de apertura del día de hoy.")

            # Set the incremental movement timestamp to exactly NOW
            new_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S.000")
            state = self.load_state()
            state["last_processed_timestamp"] = new_ts
            self.save_state(state)
            self.log(f"[Snapshot] Punto de inicio de movimientos: {new_ts}")
            return True

        except Exception as e:
            self.log(f"[Snapshot] Error al subir reporte de apertura: {str(e)}")
            if web_conn:
                try:
                    web_conn.rollback()
                except Exception:
                    pass
            return False
        finally:
            if billing_conn:
                try: billing_conn.close()
                except Exception: pass
            if web_conn:
                try: web_conn.close()
                except Exception: pass
    def _execute_sync_cycle(self):
        billing_conn = None
        web_conn = None
        
        try:
            # Reload state timestamp
            state = self.load_state()
            last_time = state["last_processed_timestamp"]
            
            # If there's no state yet, default to today at 00:00:00
            if not last_time:
                last_time = datetime.now().strftime("%Y-%m-%d 00:00:00.000")
            self.log(f"Consultando ventas locales registradas después de: {last_time}")
            
            # Connect to SQL Server
            billing_conn = self.get_sql_connection()
            
            # Connect to production web application DB (PostgreSQL / Supabase)
            web = self.config["web_db"]
            web_conn = psycopg2.connect(
                host=web["host"],
                port=web["port"],
                database=web["database"],
                user=web["user"],
                password=web["password"],
                sslmode="require",
                connect_timeout=30
            )
            
            billing_cursor = billing_conn.cursor()
            web_cursor = web_conn.cursor()
            
            # Execute query on SQL Server
            billing = self.config.get("billing_db", {})
            default_query = (
                "SELECT h.fecha_emision, COALESCE(a.codigo, i.articulo) AS articulo, i.cantidad, a.descripcion "
                "FROM [dbo].[documentos_venta] h WITH (NOLOCK) "
                "INNER JOIN [dbo].[documentos_venta_items] i WITH (NOLOCK) ON h.tipo_documento = i.tipo_documento AND h.numero_documento = i.numero_documento "
                "LEFT JOIN [dbo].[articulos_codigos] ac WITH (NOLOCK) ON i.articulo = ac.codigo "
                "LEFT JOIN [dbo].[articulos] a WITH (NOLOCK) ON (ac.articulo IS NOT NULL AND a.id = ac.articulo) OR (ac.articulo IS NULL AND a.codigo = i.articulo) "
                "WHERE h.tipo_documento = 'FAC' AND h.fecha_emision > ? ORDER BY h.fecha_emision ASC"
            )
            query = billing.get("query", default_query)
            
            # Legacy ODBC drivers sometimes fail to parse '?' correctly.
            if '?' in query:
                query = query.replace('?', f"'{last_time}'")
                billing_cursor.execute(query)
            else:
                billing_cursor.execute(query, (last_time,))
            
            rows = billing_cursor.fetchall()
            
            if not rows:
                self.log("No se encontraron nuevas ventas.")
                return True
                
            self.log(f"Se encontraron {len(rows)} ventas nuevas para procesar.")
            sede = self.config["sede"]
            new_last_time = last_time
            
            for row in rows:
                if len(row) >= 4:
                    fecha_venta, codigo, cantidad, nombre_local = row[0:4]
                else:
                    fecha_venta, codigo, cantidad = row[0:3]
                    nombre_local = None
                    
                if isinstance(fecha_venta, datetime):
                    fecha_str = fecha_venta.strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
                else:
                    fecha_str = str(fecha_venta)
                    
                self.log(f"Procesando: Código={codigo}, Cant={float(cantidad):.2f}, Fecha={fecha_str}")
                
                # --- Búsqueda robusta de producto ---
                # Paso 1: Buscar exacto (incluyendo inactivos) dentro de SKUs concatenados
                codigo_clean = str(codigo).strip()
                web_cursor.execute(
                    "SELECT id, activo, codigo FROM inventario_v2.productos WHERE %s = ANY(string_to_array(REPLACE(codigo, ' ', ''), '/')) LIMIT 1;",
                    (codigo_clean,)
                )
                prod_row = web_cursor.fetchone()

                # Paso 2: Buscar sin distinguir mayúsculas/minúsculas (ILIKE)
                if not prod_row:
                    web_cursor.execute(
                        "SELECT id, activo, codigo FROM inventario_v2.productos WHERE LOWER(%s) = ANY(string_to_array(LOWER(REPLACE(codigo, ' ', '')), '/')) LIMIT 1;",
                        (codigo_clean,)
                    )
                    prod_row = web_cursor.fetchone()

                # Paso 3: Buscar sin ceros iniciales (ej: '00123' → '123')
                if not prod_row:
                    codigo_stripped = codigo_clean.lstrip('0')
                    if codigo_stripped and codigo_stripped != codigo_clean:
                        web_cursor.execute(
                            "SELECT id, activo, codigo FROM inventario_v2.productos WHERE LOWER(LTRIM(codigo, '0')) = LOWER(%s) LIMIT 1;",
                            (codigo_stripped,)
                        )
                        # Fallback for array with no zeros is too complex, just use exact without zeros against the full string
                        if not web_cursor.fetchone():
                            web_cursor.execute(
                                "SELECT id, activo, codigo FROM inventario_v2.productos WHERE %s = ANY(string_to_array(REPLACE(codigo, ' ', ''), '/')) LIMIT 1;",
                                (codigo_stripped,)
                            )
                        prod_row = web_cursor.fetchone()
                        if prod_row:
                            self.log(f"  [Info] Código '{codigo}' encontrado como '{codigo_stripped}' (sin ceros iniciales).")

                # Paso 4: Auto-Sanación por nombre
                if not prod_row and nombre_local:
                    nombre_clean = str(nombre_local).strip()
                    if nombre_clean:
                        web_cursor.execute(
                            "SELECT id, activo, codigo FROM inventario_v2.productos WHERE LOWER(TRIM(nombre)) = LOWER(%s) LIMIT 1;",
                            (nombre_clean,)
                        )
                        prod_row = web_cursor.fetchone()
                        if prod_row:
                            current_codigo = str(prod_row[2]) if prod_row[2] else ""
                            parts = current_codigo.replace(' ', '').split('/')
                            if codigo_clean not in parts:
                                new_codigo = f"{current_codigo} / {codigo_clean}" if current_codigo else codigo_clean
                                self.log(f"  [Sync Auto-Heal] Producto '{nombre_clean}' encontrado por nombre. Agregando código '{codigo_clean}' a la web.")
                                try:
                                    web_cursor.execute(
                                        "UPDATE inventario_v2.productos SET codigo = %s, updated_at = NOW() WHERE id = %s;",
                                        (new_codigo, prod_row[0])
                                    )
                                except Exception as e:
                                    self.log(f"  [Sync Auto-Heal] Error actualizando código: {e}")
                                    web_conn.rollback()
                                    pass


                # Paso 5: Si aun no existe, crear el producto automáticamente para no perder el movimiento
                if not prod_row:
                    self.log(f"  [Auto-Registro] Código '{codigo_clean}' no existe. Creando producto desconocido...")
                    web_cursor.execute(
                        """
                        INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor, precio_unidad, precio_mayor, activo, created_at, updated_at)
                        VALUES (%s, %s, 'Sin categoría', '', '', 0, 0, true, NOW(), NOW())
                        ON CONFLICT (codigo) DO UPDATE SET activo = true, updated_at = NOW()
                        RETURNING id, activo;
                        """,
                        (codigo_clean, f"[Auto] {codigo_clean}")
                    )
                    prod_row = web_cursor.fetchone()

                    if prod_row:
                        self.log(f"  [Auto-Registro] Producto creado con ID={prod_row[0]}.")
                    else:
                        self.log(f"  [Error] No se pudo crear el producto '{codigo_clean}'. Saltando.")
                        continue

                prod_id = prod_row[0]
                prod_activo = prod_row[1]

                # Si el producto existe pero está inactivo, reactivarlo
                if not prod_activo:
                    self.log(f"  [Info] Producto '{codigo_clean}' estaba inactivo. Reactivando...")
                    web_cursor.execute(
                        "UPDATE inventario_v2.productos SET activo = true, updated_at = NOW() WHERE id = %s;",
                        (prod_id,)
                    )
                
                # Ensure the row exists in stock_actual
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.stock_actual (producto_id, sede, existencia, updated_at)
                    VALUES (%s, %s, 0, NOW())
                    ON CONFLICT (producto_id, sede) DO NOTHING;
                    """,
                    (prod_id, sede)
                )
                
                # Decrement stock
                web_cursor.execute(
                    """
                    UPDATE inventario_v2.stock_actual
                    SET existencia = GREATEST(0, existencia - %s), updated_at = NOW()
                    WHERE producto_id = %s AND sede = %s;
                    """,
                    (cantidad, prod_id, sede)
                )
                
                # Log stock movement
                metadata = json.dumps({
                    "motivo": "Sincronizacion automatica de venta",
                    "fecha_venta_local": fecha_str
                })
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.movimientos (producto_id, origen, destino, tipo, cantidad, usuario, metadata, created_at)
                    VALUES (%s, %s, NULL, 'AJUSTE', %s, 'sistema_sync', %s, NOW());
                    """,
                    (prod_id, sede, cantidad, metadata)
                )
                
                # Update real-time ultima_venta
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.ventas_historicas (producto_id, sede, ultima_venta, updated_at, ventas_60d, venta_promedio)
                    VALUES (%s, %s, %s, NOW(), 0, 0)
                    ON CONFLICT (producto_id, sede) DO UPDATE
                        SET ultima_venta = EXCLUDED.ultima_venta, updated_at = NOW();
                    """,
                    (prod_id, sede, fecha_str)
                )
                
                # Evitar que fechas en el futuro (ej. errores de tipeo 2626) congelen el sincronizador
                now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S.999")
                valid_fecha_str = fecha_str if fecha_str <= now_str else now_str
                new_last_time = max(new_last_time, valid_fecha_str)
                
            # Log successful sync to sync_logs
            meta_json = json.dumps({"timestamp": new_last_time})
            web_cursor.execute(
                """
                INSERT INTO inventario_v2.sync_logs (sede, tipo, registros_procesados, metadata, created_at)
                VALUES (%s, 'VENTA', %s, %s, NOW())
                """,
                (sede, len(rows), meta_json)
            )
            
            # Commit the transactions
            web_conn.commit()
            
            # Save updated timestamp
            state["last_processed_timestamp"] = new_last_time
            self.save_state(state)
            
            # Update UI field value
            self.log(f"Sincronización completada. Último registro procesado: {new_last_time}")
            return True
            
        except Exception as e:
            self.log(f"Error en ciclo de sincronización: {str(e)}")
            if web_conn:
                try:
                    web_conn.rollback()
                except Exception:
                    pass
            return False
        finally:
            if billing_conn:
                try:
                    billing_conn.close()
                except Exception:
                    pass
            if web_conn:
                try:
                    web_conn.close()
                except Exception:
                    pass
if __name__ == "__main__":
    root = tk.Tk()
    app = SyncApp(root)
    
    # Check for --autostart argument
    if "--autostart" in sys.argv:
        # Schedule the toggle_sync after a short delay so UI draws first
        root.after(1000, app.toggle_sync)
        
    root.mainloop()
