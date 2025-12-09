import os
from dotenv import load_dotenv
from vanna import VannaDefault
from flask import Flask, request, jsonify
from flask_cors import CORS
import psycopg2
from psycopg2 import pool
import json

# Cargar variables de entorno
load_dotenv()

# Configuración de la base de datos PostgreSQL
DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'port': os.getenv('DB_PORT', '5432'),
    'database': os.getenv('DB_NAME', 'eromodeshop'),
    'user': os.getenv('DB_USER', 'postgres'),
    'password': os.getenv('DB_PASSWORD', '1234')
}

# Configuración de Vanna
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY')

app = Flask(__name__)
CORS(app)

# Inicializar Vanna con Gemini
vn = VannaDefault(model='gemini/gemini-1.5-flash', api_key=GEMINI_API_KEY)

# Pool de conexiones
connection_pool = None

def get_connection():
    """Obtiene una conexión del pool"""
    global connection_pool
    if connection_pool is None:
        connection_pool = pool.SimpleConnectionPool(
            1, 10,
            host=DB_CONFIG['host'],
            port=DB_CONFIG['port'],
            database=DB_CONFIG['database'],
            user=DB_CONFIG['user'],
            password=DB_CONFIG['password']
        )
    return connection_pool.getconn()

def return_connection(conn):
    """Devuelve una conexión al pool"""
    connection_pool.putconn(conn)

def execute_sql(sql: str):
    """Ejecuta SQL en PostgreSQL y retorna los resultados"""
    conn = None
    cursor = None
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute(sql)
        
        # Obtener columnas
        columns = [desc[0] for desc in cursor.description] if cursor.description else []
        
        # Obtener resultados
        results = cursor.fetchall() if cursor.description else []
        
        conn.commit()
        
        # Formatear resultados
        data = []
        for row in results:
            data.append(dict(zip(columns, row)))
        
        return {
            'data': data,
            'columns': columns,
            'row_count': len(data)
        }
    except Exception as e:
        if conn:
            conn.rollback()
        raise e
    finally:
        if cursor:
            cursor.close()
        if conn:
            return_connection(conn)

# Configurar la función de ejecución SQL para Vanna
vn.run_sql = execute_sql
vn.run_sql_is_set = True

@app.route('/api/v0/generate_sql', methods=['GET'])
def generate_sql():
    """Genera SQL basado en una pregunta natural"""
    try:
        question = request.args.get('question')
        if not question:
            return jsonify({'error': 'Question parameter is required'}), 400
        
        sql = vn.generate_sql(question=question)
        return jsonify({'sql': sql})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/run_sql', methods=['POST'])
def run_sql():
    """Ejecuta SQL y retorna los resultados"""
    try:
        data = request.json
        sql = data.get('sql')
        
        if not sql:
            return jsonify({'error': 'SQL query is required'}), 400
        
        results = execute_sql(sql)
        return jsonify(results)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/generate_question', methods=['GET'])
def generate_question():
    """Genera preguntas sugeridas basadas en el esquema"""
    try:
        questions = vn.generate_questions()
        return jsonify({'questions': questions})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/train', methods=['POST'])
def train():
    """Entrena el modelo con información adicional"""
    try:
        data = request.json
        training_data = data.get('training_data')
        question = data.get('question')
        sql = data.get('sql')
        ddl = data.get('ddl')
        documentation = data.get('documentation')
        
        if training_data:
            vn.train(ddl=training_data)
        elif question and sql:
            vn.train(question=question, sql=sql)
        elif ddl:
            vn.train(ddl=ddl)
        elif documentation:
            vn.train(documentation=documentation)
        else:
            return jsonify({'error': 'Training data is required'}), 400
        
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/get_training_data', methods=['GET'])
def get_training_data():
    """Obtiene los datos de entrenamiento actuales"""
    try:
        training_data = vn.get_training_data()
        return jsonify({'training_data': training_data})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/remove_training_data', methods=['DELETE'])
def remove_training_data():
    """Elimina datos de entrenamiento específicos"""
    try:
        data = request.json
        id = data.get('id')
        
        if not id:
            return jsonify({'error': 'ID is required'}), 400
        
        result = vn.remove_training_data(id=id)
        return jsonify({'success': result})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/get_schema', methods=['GET'])
def get_schema():
    """Obtiene el esquema de la base de datos"""
    try:
        # Obtener todas las tablas
        conn = get_connection()
        cursor = conn.cursor()
        
        # Obtener tablas
        cursor.execute("""
            SELECT table_name, table_type
            FROM information_schema.tables 
            WHERE table_schema = 'public'
            ORDER BY table_name;
        """)
        tables = cursor.fetchall()
        
        schema_info = {}
        
        # Para cada tabla, obtener columnas
        for table_name, table_type in tables:
            cursor.execute("""
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns
                WHERE table_name = %s AND table_schema = 'public'
                ORDER BY ordinal_position;
            """, (table_name,))
            columns = cursor.fetchall()
            
            schema_info[table_name] = {
                'type': table_type,
                'columns': [
                    {
                        'name': col[0],
                        'type': col[1],
                        'nullable': col[2] == 'YES',
                        'default': col[3]
                    }
                    for col in columns
                ]
            }
        
        cursor.close()
        return_connection(conn)
        
        return jsonify({'schema': schema_info})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/get_databases', methods=['GET'])
def get_databases():
    """Obtiene todas las bases de datos disponibles"""
    try:
        conn = psycopg2.connect(
            host=DB_CONFIG['host'],
            port=DB_CONFIG['port'],
            database='postgres',
            user=DB_CONFIG['user'],
            password=DB_CONFIG['password']
        )
        cursor = conn.cursor()
        cursor.execute("SELECT datname FROM pg_database WHERE datistemplate = false;")
        databases = [db[0] for db in cursor.fetchall()]
        cursor.close()
        conn.close()
        return jsonify({'databases': databases})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/health', methods=['GET'])
def health_check():
    """Endpoint de verificación de salud"""
    try:
        # Verificar conexión a la base de datos
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute('SELECT version()')
        db_version = cursor.fetchone()[0]
        cursor.execute('SELECT current_database()')
        db_name = cursor.fetchone()[0]
        cursor.execute('SELECT current_user')
        db_user = cursor.fetchone()[0]
        cursor.close()
        return_connection(conn)
        
        return jsonify({
            'status': 'healthy',
            'database': {
                'name': db_name,
                'host': DB_CONFIG['host'],
                'port': DB_CONFIG['port'],
                'version': db_version,
                'user': db_user
            },
            'vanna': {
                'model': 'gemini/gemini-1.5-flash',
                'api_key_configured': bool(GEMINI_API_KEY)
            },
            'endpoints': {
                'generate_sql': '/api/v0/generate_sql?question=...',
                'run_sql': '/api/v0/run_sql (POST)',
                'chat': '/api/v0/chat (POST)',
                'get_schema': '/api/v0/get_schema',
                'get_databases': '/api/v0/get_databases'
            }
        })
    except Exception as e:
        return jsonify({'status': 'unhealthy', 'error': str(e)}), 500

@app.route('/api/v0/chat', methods=['POST'])
def chat():
    """Endpoint para chat completo: genera SQL y ejecuta"""
    try:
        data = request.json
        question = data.get('question')
        
        if not question:
            return jsonify({'error': 'Question is required'}), 400
        
        # Generar SQL
        sql = vn.generate_sql(question=question)
        
        # Ejecutar SQL
        results = execute_sql(sql)
        
        return jsonify({
            'question': question,
            'sql': sql,
            'results': results
        })
    except Exception as e:
        return jsonify({
            'error': str(e),
            'question': question if 'question' in locals() else None
        }), 500

@app.route('/api/v0/ask', methods=['POST'])
def ask():
    """Endpoint similar al de la API oficial de Vanna"""
    try:
        data = request.json
        question = data.get('question')
        
        if not question:
            return jsonify({'error': 'Question is required'}), 400
        
        # Generar SQL
        sql = vn.generate_sql(question=question)
        
        # Ejecutar SQL
        results = execute_sql(sql)
        
        # Formatear respuesta similar a Vanna
        return jsonify({
            'type': 'sql',
            'explanation': f"Generated SQL for: {question}",
            'sql': sql,
            'df': results['data'],
            'columns': results['columns'],
            'row_count': results['row_count']
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/v0/update_schema', methods=['POST'])
def update_schema():
    """Actualiza el esquema automáticamente"""
    try:
        # Obtener todas las tablas y sus DDL
        conn = get_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public'
            ORDER BY table_name;
        """)
        tables = [row[0] for row in cursor.fetchall()]
        
        # Generar DDL para cada tabla
        for table in tables:
            cursor.execute(f"""
                SELECT column_name, data_type, is_nullable
                FROM information_schema.columns
                WHERE table_name = '{table}' AND table_schema = 'public'
                ORDER BY ordinal_position;
            """)
            columns = cursor.fetchall()
            
            # Crear DDL simple
            ddl = f"CREATE TABLE {table} (\n"
            ddl += ",\n".join([f"    {col[0]} {col[1]} {'NULL' if col[2] == 'YES' else 'NOT NULL'}" 
                              for col in columns])
            ddl += "\n);"
            
            # Entrenar con el DDL
            vn.train(ddl=ddl)
        
        cursor.close()
        return_connection(conn)
        
        return jsonify({
            'success': True,
            'message': f'Schema updated for {len(tables)} tables',
            'tables': tables
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/')
def index():
    """Página de inicio con información del servidor"""
    return '''
    <!DOCTYPE html>
    <html>
    <head>
        <title>Vanna.AI Server</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                margin: 0; 
                padding: 0; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .container { 
                max-width: 1000px; 
                margin: 0 auto; 
                padding: 40px 20px;
            }
            .header {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            .endpoint { 
                background: rgba(255, 255, 255, 0.95); 
                padding: 25px; 
                margin: 15px 0; 
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                border-left: 5px solid #667eea;
                transition: transform 0.3s ease;
            }
            .endpoint:hover {
                transform: translateY(-5px);
            }
            code { 
                background: #f8f9fa; 
                padding: 8px 12px; 
                border-radius: 8px; 
                font-family: 'Courier New', monospace;
                color: #e83e8c;
                font-size: 14px;
            }
            h1 { 
                color: #333; 
                margin: 0 0 10px 0;
                font-size: 36px;
            }
            h2 { 
                color: #444; 
                margin: 30px 0 15px 0;
                font-size: 28px;
            }
            h3 { 
                color: #555; 
                margin: 0 0 10px 0;
                font-size: 20px;
            }
            p { 
                color: #666; 
                line-height: 1.6;
                margin: 10px 0;
            }
            .badge {
                display: inline-block;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                margin-right: 10px;
                margin-bottom: 10px;
            }
            .badge-get { background: #28a745; color: white; }
            .badge-post { background: #007bff; color: white; }
            .badge-delete { background: #dc3545; color: white; }
            pre {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                overflow-x: auto;
                border: 1px solid #e9ecef;
                font-size: 14px;
            }
            .try-it {
                background: #667eea;
                color: white;
                padding: 12px 25px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                margin-top: 20px;
                text-decoration: none;
                display: inline-block;
                transition: background 0.3s ease;
            }
            .try-it:hover {
                background: #5a67d8;
                color: white;
                text-decoration: none;
            }
            .config-info {
                background: rgba(255, 255, 255, 0.9);
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🚀 Vanna.AI Server</h1>
                <p>Servidor para consultas en lenguaje natural a PostgreSQL usando Gemini AI</p>
                
                <div class="config-info">
                    <h3>📊 Configuración actual:</h3>
                    <div class="badge badge-get">Database: ''' + DB_CONFIG['database'] + '''</div>
                    <div class="badge badge-post">Host: ''' + DB_CONFIG['host'] + '''</div>
                    <div class="badge badge-get">Port: ''' + str(DB_CONFIG['port']) + '''</div>
                    <div class="badge badge-post">Model: Gemini 1.5 Flash</div>
                </div>
                
                <a href="/api/v0/health" class="try-it">🔍 Verificar Estado del Servidor</a>
            </div>
            
            <h2>🔧 Endpoints disponibles:</h2>
            
            <div class="endpoint">
                <span class="badge badge-get">GET</span>
                <h3><code>/api/v0/health</code></h3>
                <p>Verificar estado del servidor y conexión a la base de datos</p>
            </div>
            
            <div class="endpoint">
                <span class="badge badge-get">GET</span>
                <h3><code>/api/v0/generate_sql?question=TU_PREGUNTA</code></h3>
                <p>Generar SQL a partir de una pregunta en lenguaje natural</p>
                <p><strong>Ejemplo:</strong> <code>?question=Listar todos los productos ordenados por precio</code></p>
            </div>
            
            <div class="endpoint">
                <span class="badge badge-post">POST</span>
                <h3><code>/api/v0/chat</code></h3>
                <p>Chat completo: genera SQL y ejecuta la consulta</p>
                <pre>{
  "question": "¿Cuántos productos hay en stock?"
}</pre>
            </div>
            
            <div class="endpoint">
                <span class="badge badge-get">GET</span>
                <h3><code>/api/v0/get_schema</code></h3>
                <p>Obtener esquema completo de la base de datos</p>
            </div>
            
            <div class="endpoint">
                <span class="badge badge-post">POST</span>
                <h3><code>/api/v0/update_schema</code></h3>
                <p>Actualizar automáticamente el esquema en Vanna</p>
            </div>
            
            <div class="endpoint">
                <span class="badge badge-post">POST</span>
                <h3><code>/api/v0/run_sql</code></h3>
                <p>Ejecutar una consulta SQL directamente</p>
                <pre>{
  "sql": "SELECT * FROM productos LIMIT 10"
}</pre>
            </div>
            
            <h2>📚 Ejemplos de uso con curl:</h2>
            
            <div class="endpoint">
                <h3>Verificar salud del servidor:</h3>
                <pre>curl http://localhost:8080/api/v0/health</pre>
            </div>
            
            <div class="endpoint">
                <h3>Generar SQL para una pregunta:</h3>
                <pre>curl "http://localhost:8080/api/v0/generate_sql?question=Listar todos los productos"</pre>
            </div>
            
            <div class="endpoint">
                <h3>Chat completo (generar y ejecutar):</h3>
                <pre>curl -X POST http://localhost:8080/api/v0/chat \\
  -H "Content-Type: application/json" \\
  -d '{"question": "¿Cuántos productos hay en stock?"}'</pre>
            </div>
            
            <div class="endpoint">
                <h3>Obtener esquema de la base de datos:</h3>
                <pre>curl http://localhost:8080/api/v0/get_schema</pre>
            </div>
            
            <div class="endpoint">
                <h3>Ejecutar SQL directamente:</h3>
                <pre>curl -X POST http://localhost:8080/api/v0/run_sql \\
  -H "Content-Type: application/json" \\
  -d '{"sql": "SELECT COUNT(*) FROM productos"}'</pre>
            </div>
            
            <h2>🔄 Actualizar esquema automáticamente:</h2>
            <div class="endpoint">
                <pre>curl -X POST http://localhost:8080/api/v0/update_schema</pre>
                <p>Este endpoint analiza todas las tablas y entrena a Vanna con su estructura.</p>
            </div>
            
            <div style="text-align: center; margin-top: 40px; padding: 20px; color: white;">
                <p>Vanna.AI Server v1.0 • Conectado a PostgreSQL • Powered by Gemini AI</p>
            </div>
        </div>
        
        <script>
            // Función para probar endpoints
            document.addEventListener('DOMContentLoaded', function() {
                // Agregar botones de prueba a cada endpoint
                document.querySelectorAll('.endpoint').forEach(endpoint => {
                    const h3 = endpoint.querySelector('h3');
                    if (h3) {
                        const code = h3.querySelector('code');
                        if (code) {
                            const endpointUrl = code.textContent;
                            const method = endpoint.querySelector('.badge').textContent.trim();
                            const isGet = method === 'GET';
                            const isPost = method === 'POST';
                            
                            const button = document.createElement('button');
                            button.className = 'try-it';
                            button.style.marginTop = '10px';
                            button.textContent = 'Probar Endpoint';
                            
                            button.onclick = function() {
                                if (isGet && endpointUrl.includes('?question=')) {
                                    const question = prompt('Ingresa tu pregunta:', 'Listar todos los productos');
                                    if (question) {
                                        const url = 'http://localhost:8080' + endpointUrl.replace('TU_PREGUNTA', encodeURIComponent(question));
                                        window.open(url, '_blank');
                                    }
                                } else if (isGet) {
                                    const url = 'http://localhost:8080' + endpointUrl;
                                    window.open(url, '_blank');
                                } else if (isPost && endpointUrl.includes('/chat')) {
                                    const question = prompt('Ingresa tu pregunta para el chat:', '¿Cuántos productos hay?');
                                    if (question) {
                                        const url = 'http://localhost:8080' + endpointUrl;
                                        fetch(url, {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify({ question: question })
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            alert('Respuesta recibida:\n\nSQL: ' + data.sql + '\n\nResultados: ' + JSON.stringify(data.results, null, 2));
                                        })
                                        .catch(error => {
                                            alert('Error: ' + error);
                                        });
                                    }
                                }
                            };
                            
                            endpoint.appendChild(button);
                        }
                    }
                });
            });
        </script>
    </body>
    </html>
    '''

if __name__ == '__main__':
    print("=" * 70)
    print("🚀 INICIANDO VANNA.AI SERVER")
    print("=" * 70)
    print(f"📊 BASE DE DATOS: {DB_CONFIG['database']}")
    print(f"🔗 HOST: {DB_CONFIG['host']}:{DB_CONFIG['port']}")
    print(f"👤 USUARIO: {DB_CONFIG['user']}")
    print(f"🤖 MODELO AI: Gemini 1.5 Flash")
    print(f"🔑 API KEY: {'✓ CONFIGURADA' if GEMINI_API_KEY else '✗ NO CONFIGURADA'}")
    print("=" * 70)
    
    # Probar conexión inicial
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute('SELECT version()')
        version = cursor.fetchone()[0]
        cursor.execute('SELECT current_database()')
        db_name = cursor.fetchone()[0]
        cursor.close()
        return_connection(conn)
        print(f"✅ POSTGRESQL CONECTADO: {db_name}")
        print(f"📋 VERSIÓN: {version.split(',')[0]}")
    except Exception as e:
        print(f"❌ ERROR DE CONEXIÓN A POSTGRESQL: {e}")
        print("   Verifica:")
        print("   1. Que PostgreSQL esté corriendo")
        print("   2. Que las credenciales en .env sean correctas")
        print("   3. Que la base de datos exista")
    
    print(f"🌐 SERVIDOR: http://localhost:8080")
    print(f"📚 API DOCS: http://localhost:8080")
    print("=" * 70)
    print("📝 Endpoints principales:")
    print("   GET  /api/v0/health          - Verificar estado")
    print("   GET  /api/v0/generate_sql    - Generar SQL")
    print("   POST /api/v0/chat            - Chat completo")
    print("   GET  /api/v0/get_schema      - Obtener esquema")
    print("   POST /api/v0/update_schema   - Actualizar esquema")
    print("=" * 70)
    
    # Iniciar servidor
    app.run(host='0.0.0.0', port=8080, debug=True, use_reloader=False)