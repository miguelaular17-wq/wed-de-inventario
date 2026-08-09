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

try:
    import pystray
    from pystray import MenuItem as item
    from PIL import Image, ImageDraw
    TRAY_SUPPORT = True
except ImportError:
    TRAY_SUPPORT = False

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
            self.root.after(1000, self.toggle_sync)
            
        # Ocultar en la bandeja del sistema (System Tray)
        if TRAY_SUPPORT:
            self.root.protocol('WM_DELETE_WINDOW', self.hide_window)
            # Arranque oculto y silencioso
            self.root.after(100, self.hide_window)
        else:
            self.root.protocol('WM_DELETE_WINDOW', self.ask_quit_password)
            if "--autostart" in sys.argv:
                self.root.iconify()

    def log(self, message):
        def _update_gui():
            timestamp = datetime.now().strftime("%H:%M:%S")
            self.console.insert(tk.END, f"[{timestamp}] {message}\n")
            self.console.see(tk.END)
            self.console.update_idletasks()
        # Enviar al hilo principal de la interfaz para refresco en tiempo real
        self.root.after(0, _update_gui)

    def create_image(self):
        # Generar un icono simple dinámicamente si no hay archivo .ico
        image = Image.new('RGB', (64, 64), color=(30, 58, 138))
        dc = ImageDraw.Draw(image)
        dc.rectangle((16, 16, 48, 48), fill=(255, 255, 255))
        return image

    def hide_window(self):
        self.root.withdraw()
        image = self.create_image()
        menu = pystray.Menu(
            item('Mostrar Panel', self.show_window, default=True),
            item('Salir Completamente', self.quit_window)
        )
        self.icon = pystray.Icon("SyncApp", image, "Sincronizador Integra", menu)
        threading.Thread(target=self.icon.run, daemon=True).start()

    def show_window(self, icon, item):
        self.icon.stop()
        self.root.after(0, self.root.deiconify)

    def quit_window(self, icon, item):
        self.root.after(0, self.ask_quit_password)

    def ask_quit_password(self):
        from tkinter import simpledialog
        was_hidden = self.root.state() == 'withdrawn'
        if was_hidden:
            self.root.deiconify()
        
        pwd = simpledialog.askstring("Seguridad", "Ingrese el código de administrador para salir:", parent=self.root, show='*')
        
        if pwd == "Jrz2026":
            if getattr(self, 'icon', None):
                try:
                    self.icon.stop()
                except Exception:
                    pass
            self.root.destroy()
            os._exit(0)
        else:
            if pwd is not None:
                messagebox.showerror("Error", "Código incorrecto.", parent=self.root)
            if was_hidden:
                self.root.withdraw()

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
            values=["JRZ", "DORAL", "VIRTUDES", "ZAMORA", "CENTRO", "SAMBIL", "NUNES", "MOVISTAR"], 
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
        
        # Row 7: Sync Cobranzas Checkbox
        self.sync_cobranzas_var = tk.BooleanVar()
        self.sync_cobranzas_chk = ttk.Checkbutton(
            config_frame, 
            text="Subir Cobranzas al inicio del día", 
            variable=self.sync_cobranzas_var
        )
        self.sync_cobranzas_chk.grid(row=7, column=0, columnspan=4, sticky=tk.W, pady=5)
        
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

        # Button: Sync Histórico Completo
        self.btn_historico = ttk.Button(
            action_frame,
            text="Sincronizar Histórico Completo",
            command=self.run_sinc_historico,
            width=30
        )
        self.btn_historico.pack(side=tk.LEFT, padx=(10, 0))
        # Button: Inspect SQL Server columns
        self.btn_inspect = ttk.Button(
            action_frame,
            text="Inspeccionar Columnas SQL",
            command=self.inspect_sqlserver_columns,
            width=28
        )
        self.btn_inspect.pack(side=tk.LEFT, padx=(10, 0))
        
        # Button: Reparar Productos [Auto]
        self.btn_auto_heal = ttk.Button(
            action_frame,
            text="Reparar Códigos [Auto]",
            command=self.run_auto_heal,
            width=25
        )
        self.btn_auto_heal.pack(side=tk.LEFT, padx=(10, 0))
        
        # Button: Actualizar Precios
        self.btn_update_prices = ttk.Button(
            action_frame,
            text="Actualizar Precios",
            command=self.run_update_prices,
            width=20
        )
        self.btn_update_prices.pack(side=tk.LEFT, padx=(10, 0))
        
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
        
        # Load sync cobranzas
        self.sync_cobranzas_var.set(self.config.get("sync_cobranzas", True))
        
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
        self.config["sync_cobranzas"] = self.sync_cobranzas_var.get()
        
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
                
                if success and self.config.get("sync_cobranzas", True):
                    self._execute_daily_cobranzas()

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
                    for part in full_code.split(' / '):
                        clean_part = part.strip()
                        if clean_part:
                            prod_map[clean_part] = db_id
            
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
                    a.descripcion                                    AS descripcion,
                    ISNULL(a.precio1_moneda2_uni1, 0)                AS precio_unidad,
                    ISNULL(a.precio2_moneda2_uni1, ISNULL(a.precio1_moneda2_uni1, 0)) AS precio_mayor,
                    c_padre.descripcion                              AS categoria,
                    c_sub.descripcion                                AS subcategoria
                FROM [dbo].[articulos] a WITH (NOLOCK)
                LEFT JOIN [dbo].[existencias] ex WITH (NOLOCK) 
                    ON a.id = ex.id_articulo AND ex.almacen = '01'
                LEFT JOIN [dbo].[categorias] c_sub WITH (NOLOCK) ON a.categoria = c_sub.codigo
                LEFT JOIN [dbo].[categorias] c_padre WITH (NOLOCK) ON c_sub.id_padre = c_padre.id
                LEFT JOIN (
                    SELECT ISNULL(a2.codigo, vi.articulo) AS articulo, SUM(vi.cantidad) AS total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento
                       AND v.numero_documento = vi.numero_documento
                    LEFT JOIN [dbo].[articulos_codigos] ac WITH (NOLOCK) ON vi.articulo = ac.codigo
                    LEFT JOIN [dbo].[articulos] a2 WITH (NOLOCK) ON ac.articulo = a2.id
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -15, GETDATE())
                    GROUP BY ISNULL(a2.codigo, vi.articulo)
                ) s15 ON s15.articulo = a.codigo
                LEFT JOIN (
                    SELECT ISNULL(a2.codigo, vi.articulo) AS articulo, SUM(vi.cantidad) AS total_qty
                    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
                    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
                        ON v.tipo_documento = vi.tipo_documento
                       AND v.numero_documento = vi.numero_documento
                    LEFT JOIN [dbo].[articulos_codigos] ac WITH (NOLOCK) ON vi.articulo = ac.codigo
                    LEFT JOIN [dbo].[articulos] a2 WITH (NOLOCK) ON ac.articulo = a2.id
                    WHERE v.tipo_documento = 'FAC'
                      AND v.fecha_emision >= DATEADD(day, -60, GETDATE())
                    GROUP BY ISNULL(a2.codigo, vi.articulo)
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
            pid_updates_map = {}

            for row in rows:
                codigo = str(row[0]).strip()
                nombre_local  = str(row[6]).strip() if len(row) > 6 and row[6] else None
                precio_unidad = float(row[7]) if len(row) > 7 and row[7] else 0.0
                precio_mayor  = float(row[8]) if len(row) > 8 and row[8] else 0.0
                categoria_str = str(row[9]).strip() if len(row) > 9 and row[9] else None
                subcategoria_str = str(row[10]).strip() if len(row) > 10 and row[10] else ''
                
                if not categoria_str:
                    categoria_str = subcategoria_str
                    subcategoria_str = ''
                
                if not categoria_str:
                    categoria_str = 'Sin categoría'
                
                existencia    = max(0, int(row[1]) if row[1] else 0)
                ventas_15d    = float(row[2]) if row[2] else 0.0
                ventas_60d    = float(row[3]) if row[3] else 0.0
                ultima_venta  = str(row[4])   if row[4] else None
                ultima_compra = str(row[5])   if row[5] else None

                # ¡LA MAGIA AQUÍ! Solo nos importan los productos que ya existen en Supabase
                # O los que NO existen pero TIENEN STOCK en esta sede
                if codigo in prod_map:
                    pid = prod_map[codigo]
                elif nombre_local and nombre_local.lower() in name_map:
                    pid, current_codigo = name_map[nombre_local.lower()]
                    
                    # Split for robust checking, but also check if the full code is already there
                    parts = [p.strip() for p in current_codigo.split(' / ')]
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
                            web_conn.commit() # Fix for PgBouncer
                        except Exception as e:
                            self.log(f"[Snapshot Auto-Heal] Error actualizando código: {e}")
                            web_conn.rollback() # Rollback the failed update but keep the transaction going
                            pass
                    
                    prod_map[codigo] = pid # Update local map
                else:
                    # FIX: Registrar el producto si tiene stock O si tiene ventas recientes.
                    # Antes solo se registraba con existencia > 0, dejando fuera productos con stock 0
                    # que igualmente se venden (devoluciones, descuadres, etc.)
                    tiene_actividad = existencia > 0 or ventas_15d > 0 or ventas_60d > 0
                    if tiene_actividad:
                        try:
                            wc.execute("SAVEPOINT auto_reg_sp;")
                            # FIX: Usar el nombre real del artículo. Solo usar código como fallback si no hay nombre.
                            nombre_insercion = str(nombre_local).strip() if nombre_local and str(nombre_local).strip() else codigo
                            self.log(f"[Snapshot Auto-Registro] Producto '{codigo}' no existe en la web (stock={existencia}, ventas60d={ventas_60d}). Subiéndolo como '{nombre_insercion}'...")
                            wc.execute(
                                """
                                INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor, precio_unidad, precio_mayor, activo, created_at, updated_at)
                                VALUES (%s, %s, %s, %s, '', %s, %s, true, NOW(), NOW())
                                ON CONFLICT (codigo) DO UPDATE
                                    SET activo = true,
                                        precio_unidad = GREATEST(inventario_v2.productos.precio_unidad, EXCLUDED.precio_unidad),
                                        precio_mayor  = GREATEST(inventario_v2.productos.precio_mayor,  EXCLUDED.precio_mayor),
                                        categoria     = CASE WHEN inventario_v2.productos.categoria = 'Sin categoría' OR inventario_v2.productos.categoria IS NULL OR inventario_v2.productos.categoria = '' THEN EXCLUDED.categoria ELSE inventario_v2.productos.categoria END,
                                        subcategoria  = CASE WHEN inventario_v2.productos.subcategoria IS NULL OR inventario_v2.productos.subcategoria = '' THEN EXCLUDED.subcategoria ELSE inventario_v2.productos.subcategoria END,
                                        updated_at = NOW()
                                RETURNING id;
                                """,
                                (codigo, nombre_insercion, categoria_str, subcategoria_str, precio_unidad, precio_mayor)
                            )
                            new_pid_row = wc.fetchone()
                            if new_pid_row:
                                pid = new_pid_row[0]
                                prod_map[codigo] = pid
                                if nombre_local:
                                    name_map[nombre_local.lower()] = (pid, codigo)
                                wc.execute("RELEASE SAVEPOINT auto_reg_sp;")
                                web_conn.commit()
                            else:
                                wc.execute("ROLLBACK TO SAVEPOINT auto_reg_sp;")
                                skipped += 1
                                continue
                        except Exception as e:
                            self.log(f"[Snapshot Auto-Registro] Error subiendo producto '{codigo}': {e}")
                            wc.execute("ROLLBACK TO SAVEPOINT auto_reg_sp;")
                            skipped += 1
                            continue
                    else:
                        skipped += 1
                        continue

                # Guardar info para la actualización masiva de precios y categorías
                if pid not in pid_updates_map or precio_unidad > pid_updates_map[pid][0]:
                    pid_updates_map[pid] = (precio_unidad, precio_mayor, categoria_str, subcategoria_str)

                pid_stock_map[pid] = pid_stock_map.get(pid, 0) + existencia
                
                if pid not in pid_ventas_map:
                    pid_ventas_map[pid] = {
                        'ventas_60d': ventas_60d,
                        'ventas_15d': ventas_15d,
                        'ultima_venta': None,
                        'ultima_compra': None
                    }
                else:
                    pid_ventas_map[pid]['ventas_60d'] = max(pid_ventas_map[pid]['ventas_60d'], ventas_60d)
                    pid_ventas_map[pid]['ventas_15d'] = max(pid_ventas_map[pid]['ventas_15d'], ventas_15d)
                
                v_data = pid_ventas_map[pid]
                
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
            # Ejecutar actualización de precios y categorias en lotes para evitar que se cuelgue
            updates_tuples = []
            for pid_r, (pu, pm, cat, subcat) in pid_updates_map.items():
                updates_tuples.append((pu, pm, cat, subcat, pid_r, pu, pm, cat, subcat))
                
            if updates_tuples:
                batch_update_query = """
                    UPDATE inventario_v2.productos
                    SET precio_unidad = CASE WHEN precio_unidad IS NULL OR precio_unidad <= 0 THEN %s ELSE precio_unidad END,
                        precio_mayor  = CASE WHEN precio_mayor  IS NULL OR precio_mayor  <= 0 THEN %s ELSE precio_mayor  END,
                        categoria     = CASE WHEN categoria = 'Sin categoría' OR categoria IS NULL OR categoria = '' THEN %s ELSE categoria END,
                        subcategoria  = CASE WHEN subcategoria IS NULL OR subcategoria = '' THEN %s ELSE subcategoria END,
                        updated_at = NOW()
                    WHERE id = %s AND (
                        (%s > 0 AND (precio_unidad IS NULL OR precio_unidad <= 0)) OR
                        (%s > 0 AND (precio_mayor  IS NULL OR precio_mayor  <= 0)) OR
                        (%s != 'Sin categoría' AND (categoria = 'Sin categoría' OR categoria IS NULL OR categoria = '')) OR
                        (%s != '' AND (subcategoria IS NULL OR subcategoria = ''))
                    );
                """
                batch_execute_and_commit(batch_update_query, updates_tuples)
                
            self.log(f"[Snapshot] Atributos (precios, categorias) evaluados y actualizados para {len(updates_tuples)} productos en la web.")

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

    def _execute_daily_cobranzas(self):
        """Pulls the collection details from SQL Server and pushes to Supabase historial_cobranzas."""
        billing_conn = None
        web_conn = None
        try:
            sede = self.config.get("sede", "JRZ")
            web = self.config["web_db"]
            
            self.log("[Cobranzas] 1. Ejecutando consulta de cuentas por cobrar en SQL Server...")
            billing_conn = self.get_sql_connection()
            
            query = """
            SELECT 
                cx.codigo_cliente AS [CODIGO CLIENTE],
                c.descripcion AS [NOMBRE CLIENTE],
                cx.id AS [ID],
                CAST(cx.fecha_emision AS DATE) AS [FECHA EMISION],
                cx.tipo_documento AS [TIPO CXC],
                cx.numero_documento AS [NUMERO DOCUMENTO], 
                cx.monto_neto_moneda2 AS [MONTO NETO $],
                cx.saldo_actual_moneda2 AS [SALDO $],
                DATEDIFF(day, cx.fecha_emision, GETDATE()) AS [DIAS DE DEUDA],
                CASE 
                    WHEN DATEDIFF(day, cx.fecha_emision, GETDATE()) >= 300 THEN 'CRITICO'
                    WHEN DATEDIFF(day, cx.fecha_emision, GETDATE()) > 60 THEN 'MOROSO'
                    ELSE 'RECIENTE'
                END AS [ESTADO CALCULADO],
                cx.usuario AS [USUARIO],
                cx.estacion AS [ESTACION],
                cx.codigo_caja AS [CODIGO CAJA]
            FROM cuentas_cobrar cx WITH (NOLOCK)
            JOIN clientes c WITH (NOLOCK) ON cx.codigo_cliente = c.codigo
            WHERE cx.saldo_actual_moneda2 > 0.5
              AND cx.tipo_documento IN ('FAC', 'ND')
            """
            
            cursor = billing_conn.cursor()
            cursor.execute(query)
            rows = cursor.fetchall()
            self.log(f"[Cobranzas] {len(rows)} documentos por cobrar obtenidos.")
            
            if not rows:
                return True

            self.log("[Cobranzas] 2. Conectando a Supabase para subir historial de cobranzas...")
            web_conn = psycopg2.connect(
                host=web["host"], port=web["port"], database=web["database"],
                user=web["user"], password=web["password"], sslmode="require",
                connect_timeout=30
            )
            wc = web_conn.cursor()
            
            today = datetime.now().strftime("%Y-%m-%d")
            
            # Borrar los datos de la sede para reemplazarlos con el snapshot fresco
            wc.execute("DELETE FROM cobranzas WHERE sede_nombre = %s;", (sede,))
            wc.execute("DELETE FROM cobranza_resumenes WHERE sede_nombre = %s;", (sede,))
            
            from psycopg2.extras import execute_batch
            
            insert_query = """
                INSERT INTO cobranzas (
                    sede_nombre, codigo, cliente, saldo_bs, saldo_usd,
                    meses_antiguedad, estatus, fecha_emision,
                    created_at, updated_at
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW()
                )
            """
            
            total_clientes = 0
            total_saldo = 0.0
            crit_c = 0; crit_s = 0.0
            moro_c = 0; moro_s = 0.0
            reci_c = 0; reci_s = 0.0
            
            cobranzas_data = []
            for row in rows:
                codigo = str(row[0]).strip() if row[0] else ''
                cliente = str(row[1]).strip() if row[1] else ''
                fecha_emision = row[3]
                saldo_usd = float(row[7]) if row[7] else 0.0
                dias = int(row[8]) if row[8] else 0
                meses = dias / 30.0
                
                estatus = 'RECIENTE'
                if dias >= 300:
                    estatus = 'CRITICO'
                    crit_c += 1
                    crit_s += saldo_usd
                elif dias > 60:
                    estatus = 'MOROSO'
                    moro_c += 1
                    moro_s += saldo_usd
                else:
                    reci_c += 1
                    reci_s += saldo_usd
                
                total_clientes += 1
                total_saldo += saldo_usd
                
                # asume tasa de 36 si no hay saldo en bs
                saldo_bs = float(row[6]) if row[6] else (saldo_usd * 36)

                cobranzas_data.append((
                    sede,
                    codigo,
                    cliente,
                    saldo_bs,
                    saldo_usd,
                    meses,
                    estatus,
                    fecha_emision
                ))
            
            if cobranzas_data:
                execute_batch(wc, insert_query, cobranzas_data, page_size=1000)
                
                # Insert summary
                wc.execute("""
                    INSERT INTO cobranza_resumenes (
                        sede_nombre, total_clientes, total_saldo,
                        critico_clientes, critico_saldo,
                        moroso_clientes, moroso_saldo,
                        reciente_clientes, reciente_saldo,
                        apartado_clientes, apartado_saldo,
                        created_at, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s, %s, %s, %s, 0, 0.0, NOW(), NOW()
                    )
                """, (
                    sede, total_clientes, total_saldo,
                    crit_c, crit_s, moro_c, moro_s, reci_c, reci_s
                ))
                
            web_conn.commit()
            
            self.log(f"[Cobranzas] ✓ Historial de cobranzas subido con éxito: {len(cobranzas_data)} registros.")
            return True
            
        except Exception as e:
            self.log(f"[Cobranzas] Error al subir cobranzas: {str(e)}")
            if web_conn:
                try: web_conn.rollback()
                except Exception: pass
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
                    
                # Convertir cantidad a entero >= 1 para la BD web
                try:
                    raw_cant = float(cantidad)
                    int_cantidad = max(1, int(round(raw_cant)))
                except (ValueError, TypeError):
                    int_cantidad = 1
                    
                self.log(f"Procesando: Código={codigo}, Cant={float(cantidad):.2f} (Web: {int_cantidad}), Fecha={fecha_str}")
                
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
                            parts = [p.strip() for p in current_codigo.split(' / ')]
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


                # Paso 5: Si aún no existe, crear el producto automáticamente para no perder el movimiento.
                # FIX: Usar nombre real del artículo. Solo usar el código si no hay nombre disponible.
                if not prod_row:
                    if nombre_local and str(nombre_local).strip():
                        nombre_insercion = str(nombre_local).strip()
                    else:
                        nombre_insercion = codigo_clean  # sin prefijo [Auto]
                    self.log(f"  [Auto-Registro] Código '{codigo_clean}' no existe. Creando producto '{nombre_insercion}'...")
                    web_cursor.execute(
                        """
                        INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor, precio_unidad, precio_mayor, activo, created_at, updated_at)
                        VALUES (%s, %s, 'Sin categoría', '', '', 0, 0, true, NOW(), NOW())
                        ON CONFLICT (codigo) DO UPDATE
                            SET activo = true,
                                nombre = CASE WHEN inventario_v2.productos.nombre LIKE '[Auto]%%' OR inventario_v2.productos.nombre = inventario_v2.productos.codigo
                                              THEN EXCLUDED.nombre
                                              ELSE inventario_v2.productos.nombre END,
                            updated_at = NOW()
                        RETURNING id, activo;
                        """,
                        (codigo_clean, nombre_insercion)
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
                    (int_cantidad, prod_id, sede)
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
                    (prod_id, sede, int_cantidad, metadata)
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

                # Update real-time historial mensual
                anio_mes = fecha_str[:7]
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.historial_ventas_mensuales (producto_id, sede, anio_mes, cantidad, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                    ON CONFLICT (sede, producto_id, anio_mes) DO UPDATE
                        SET cantidad = inventario_v2.historial_ventas_mensuales.cantidad + EXCLUDED.cantidad,
                            updated_at = NOW();
                    """,
                    (prod_id, sede, anio_mes, int_cantidad)
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

    def run_sinc_historico(self):
        self.btn_historico.config(state=tk.DISABLED)
        threading.Thread(target=self._perform_sinc_historico, daemon=True).start()

    def _perform_sinc_historico(self):
        billing_conn = None
        web_conn = None
        try:
            self.log("Iniciando sincronización histórica COMPLETA...")
            if not self.save_ui_values_to_config():
                return
            
            sede = self.config["sede"]
            billing_conn = self.get_sql_connection()
            if not billing_conn:
                self.log("Error: No se pudo conectar a SQL Server.")
                return
            
            web = self.config.get("web_db", {})
            if not web:
                self.log("Error: No hay credenciales para Supabase en config.json.")
                return
                
            web_conn = psycopg2.connect(
                host=web.get("host"), port=web.get("port"), database=web.get("database"),
                user=web.get("user"), password=web.get("password"), sslmode="require",
                connect_timeout=15
            )

            self.log("Consultando TODO el historial en SQL Server. Esto puede tardar...")
            cursor = billing_conn.cursor()
            query = """
            SELECT 
                CONVERT(VARCHAR(7), v.fecha_emision, 120) AS AnioMes,
                LTRIM(RTRIM(d.articulo)) AS Codigo,
                d.cantidad AS Cantidad_Vendida
            FROM 
                documentos_venta v 
            INNER JOIN 
                documentos_venta_items d ON v.numero_documento = d.numero_documento AND v.tipo_documento = d.tipo_documento
            WHERE 
                v.tipo_documento = 'FAC' 
            """
            cursor.execute(query)
            rows = cursor.fetchall()
            
            self.log(f"Extracción finalizada. Se encontraron {len(rows)} filas.")
            if not rows:
                return
                
            self.log("Agrupando y consolidando datos POR MES...")
            from collections import defaultdict
            aggregated = defaultdict(float)
            for r in rows:
                anio_mes = r[0]
                codigo = r[1]
                cant = float(r[2]) if r[2] else 0.0
                aggregated[(anio_mes, codigo)] += cant
            
            self.log("Cargando mapeo de IDs de productos web...")
            web_cursor = web_conn.cursor()
            web_cursor.execute("SELECT id, codigo FROM inventario_v2.productos")
            prod_rows = web_cursor.fetchall()
            
            codigo_a_id = {}
            for pid, cods_web in prod_rows:
                if cods_web:
                    parts = [p.strip().upper() for p in str(cods_web).split(" / ")]
                    for p in parts:
                        codigo_a_id[p] = pid
            
            self.log("Generando inserciones para la web...")
            tuples_to_insert = []
            for (anio_mes, codigo), cant in aggregated.items():
                if cant <= 0:
                    continue
                codigo_upper = str(codigo).upper()
                if codigo_upper in codigo_a_id:
                    pid = codigo_a_id[codigo_upper]
                    tuples_to_insert.append((pid, sede, anio_mes, int(round(cant))))
            
            if not tuples_to_insert:
                self.log("No hay datos válidos para insertar.")
                return
                
            self.log(f"Subiendo {len(tuples_to_insert)} registros mensuales a Supabase (BATCH)...")
            
            insert_query = """
                INSERT INTO inventario_v2.historial_ventas_mensuales
                    (producto_id, sede, anio_mes, cantidad, created_at, updated_at)
                VALUES (%s, %s, %s, %s, NOW(), NOW())
                ON CONFLICT (sede, producto_id, anio_mes) DO UPDATE
                    SET cantidad = EXCLUDED.cantidad,
                        updated_at = NOW();
            """
            if psycopg2:
                psycopg2.extras.execute_batch(web_cursor, insert_query, tuples_to_insert, page_size=2000)
            else:
                for tup in tuples_to_insert:
                    web_cursor.execute(insert_query, tup)
                    
            web_conn.commit()
            self.log("¡Sincronización histórica finalizada exitosamente!")
            
        except Exception as e:
            self.log(f"Error en sincronización histórica: {str(e)}")
            if web_conn:
                web_conn.rollback()
        finally:
            self.btn_historico.config(state=tk.NORMAL)
            if billing_conn:
                billing_conn.close()
            if web_conn:
                web_conn.close()

    def run_auto_heal(self):
        self.btn_auto_heal.config(state=tk.DISABLED)
        threading.Thread(target=self._perform_auto_heal, daemon=True).start()

    def _perform_auto_heal(self):
        billing_conn = None
        web_conn = None
        try:
            self.log("Iniciando reparación automática de productos [Auto]...")
            if not self.save_ui_values_to_config():
                return
            
            billing_conn = self.get_sql_connection()
            if not billing_conn:
                self.log("Error: No se pudo conectar a SQL Server.")
                return
            
            web = self.config.get("web_db", {})
            web_conn = psycopg2.connect(
                host=web.get("host"), port=web.get("port"), database=web.get("database"),
                user=web.get("user"), password=web.get("password"), sslmode="require",
                connect_timeout=15
            )
            
            web_cursor = web_conn.cursor()
            billing_cursor = billing_conn.cursor()
            
            # Paso 1: Buscar productos [Auto] en la web
            self.log("Buscando productos [Auto] huérfanos en la web...")
            web_cursor.execute("SELECT id, codigo, nombre FROM inventario_v2.productos WHERE nombre LIKE '%[Auto]%' OR TRIM(nombre) = TRIM(codigo) OR TRIM(nombre) = TRIM(codigo)")
            auto_prods = web_cursor.fetchall()
            
            if not auto_prods:
                self.log("¡Todo excelente! No hay productos fantasma [Auto] para reparar.")
                return
                
            self.log(f"Se encontraron {len(auto_prods)} productos [Auto]. Buscando nombres reales en SQL Server...")
            
            reparados = 0
            for auto_id, auto_codigo, auto_nombre in auto_prods:
                codigo_corto = str(auto_codigo).strip()
                self.log(f"Analizando: '{auto_nombre}' (Código: {codigo_corto})")
                
                # Paso 2: Buscar nombre real en SQL Server
                query_sql = """
                    SELECT a.descripcion, a.codigo 
                    FROM articulos a WITH (NOLOCK) 
                    WHERE a.codigo = ? 
                       OR a.id IN (SELECT articulo FROM articulos_codigos WHERE codigo = ?)
                """
                billing_cursor.execute(query_sql, (codigo_corto, codigo_corto))
                sql_row = billing_cursor.fetchone()
                
                if not sql_row:
                    self.log(f"  -> No se encontró '{codigo_corto}' en SQL Server. Se omitirá.")
                    continue
                    
                nombre_real = str(sql_row[0]).strip()
                codigo_largo_real = str(sql_row[1]).strip()
                self.log(f"  -> Nombre real en SQL Server: '{nombre_real}'")
                
                # Paso 3: Buscar nombre real en la web
                web_cursor.execute(
                    "SELECT id, codigo FROM inventario_v2.productos WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(%s)) LIMIT 1",
                    (nombre_real,)
                )
                parent_row = web_cursor.fetchone()
                
                if not parent_row:
                    self.log(f"  -> El producto '{nombre_real}' aún no existe en la web. Sincronízalo primero.")
                    continue
                    
                parent_id = parent_row[0]
                parent_codigo = str(parent_row[1]).strip() if parent_row[1] else ""
                
                # Paso 4: Combinar códigos y reparar
                parts = [p.strip() for p in parent_codigo.split(' / ')]
                if codigo_corto not in parts:
                    new_codigo = f"{parent_codigo} / {codigo_corto}" if parent_codigo else codigo_corto
                    self.log(f"  -> Combinando códigos en producto principal: {new_codigo}")
                    web_cursor.execute(
                        "UPDATE inventario_v2.productos SET codigo = %s, updated_at = NOW() WHERE id = %s",
                        (new_codigo, parent_id)
                    )
                else:
                    self.log("  -> El producto principal ya tenía el código enlazado.")
                    
                # Eliminar historiales y stock del producto fantasma para evitar errores de clave foránea
                self.log("  -> Limpiando y eliminando producto falso [Auto]...")
                web_cursor.execute("DELETE FROM inventario_v2.movimientos WHERE producto_id = %s", (auto_id,))
                web_cursor.execute("DELETE FROM inventario_v2.stock_actual WHERE producto_id = %s", (auto_id,))
                web_cursor.execute("DELETE FROM inventario_v2.ventas_historicas WHERE producto_id = %s", (auto_id,))
                web_cursor.execute("DELETE FROM inventario_v2.historial_ventas_mensuales WHERE producto_id = %s", (auto_id,))
                
                # Finalmente borrar el producto
                web_cursor.execute("DELETE FROM inventario_v2.productos WHERE id = %s", (auto_id,))
                web_conn.commit()
                
                self.log(f"  ¡Reparado! Ventas futuras de {codigo_corto} irán a '{nombre_real}'.")
                reparados += 1
                
            self.log(f"Resumen: {reparados} de {len(auto_prods)} productos [Auto] reparados exitosamente.")
            
        except Exception as e:
            import traceback
            self.log(f"Error en auto-reparación: {traceback.format_exc()}")
            if web_conn:
                web_conn.rollback()
        finally:
            self.btn_auto_heal.config(state=tk.NORMAL)
            if billing_conn:
                billing_conn.close()
            if web_conn:
                web_conn.close()

    def run_update_prices(self):
        self.btn_update_prices.config(state=tk.DISABLED)
        threading.Thread(target=self._perform_update_prices, daemon=True).start()

    def _perform_update_prices(self):
        billing_conn = None
        web_conn = None
        try:
            self.log("Iniciando actualizacion de precios...")
            if not self.save_ui_values_to_config():
                return
            
            billing_conn = self.get_sql_connection()
            if not billing_conn:
                self.log("Error: No se pudo conectar a SQL Server.")
                return
            
            web = self.config.get("web_db", {})
            web_conn = psycopg2.connect(
                host=web.get("host"), port=web.get("port"), database=web.get("database"),
                user=web.get("user"), password=web.get("password"), sslmode="require",
                connect_timeout=15
            )
            
            web_cursor = web_conn.cursor()
            billing_cursor = billing_conn.cursor()

            self.log("Consultando productos en Supabase...")
            web_cursor.execute("SELECT id, nombre, COALESCE(precio_unidad, 0), COALESCE(precio_mayor, 0) FROM inventario_v2.productos WHERE nombre IS NOT NULL")
            web_prods = web_cursor.fetchall()

            self.log("Consultando precios en SQL Server...")
            billing_cursor.execute("SELECT descripcion, ISNULL(precio1_moneda2_uni1, 0), ISNULL(precio2_moneda2_uni1, ISNULL(precio1_moneda2_uni1, 0)) FROM articulos WITH (NOLOCK) WHERE descripcion IS NOT NULL")
            sql_prods = billing_cursor.fetchall()

            self.log("Procesando comparaciones...")
            sql_dict = {}
            for desc, p1, p2 in sql_prods:
                sql_dict[str(desc).strip().lower()] = (float(p1), float(p2))

            updates = []
            for wid, wname, wp1, wp2 in web_prods:
                clean_name = str(wname).strip().lower()
                if clean_name in sql_dict:
                    sp1, sp2 = sql_dict[clean_name]
                    new_p1 = max(float(wp1), sp1)
                    new_p2 = max(float(wp2), sp2)

                    if new_p1 > float(wp1) or new_p2 > float(wp2):
                        updates.append((new_p1, new_p2, wid))

            if not updates:
                self.log("Todos los precios estan actualizados en la web.")
                return

            self.log(f"Se actualizaran los precios de {len(updates)} productos. Subiendo a Supabase...")
            psycopg2.extras.execute_batch(
                web_cursor,
                "UPDATE inventario_v2.productos SET precio_unidad = %s, precio_mayor = %s, updated_at = NOW() WHERE id = %s",
                updates
            )
            web_conn.commit()
            self.log(f"Actualizacion completada! {len(updates)} precios actualizados.")

        except Exception as e:
            import traceback
            self.log(f"Error en actualizacion de precios: {traceback.format_exc()}")
            if web_conn:
                try: web_conn.rollback()
                except Exception: pass
        finally:
            self.root.after(0, lambda: self.btn_update_prices.config(state=tk.NORMAL))
            if billing_conn:
                try: billing_conn.close()
                except Exception: pass
            if web_conn:
                try: web_conn.close()
                except Exception: pass

if __name__ == "__main__":
    root = tk.Tk()
    app = SyncApp(root)
    
    # Check for --autostart argument
    if "--autostart" in sys.argv:
        # Schedule the toggle_sync after a short delay so UI draws first
        root.after(1000, app.toggle_sync)
        
    root.mainloop()




