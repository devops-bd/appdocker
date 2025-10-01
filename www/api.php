<?php
// Check if this is an API request
$path = $_SERVER['REQUEST_URI'] ?? '/';
$query = $_GET['action'] ?? null;
$isApiRequest = strpos($path, '/api/') !== false || $query !== null;

if ($isApiRequest) {
    // Database Configuration
    class DatabaseConfig {
        private const DB_HOST = "mysql-container";
        private const DB_USER = "root";
        private const DB_PASS = "nopass";
        private const DB_NAME = "mydb";
        private const DB_PORT = 3306;
        private const DB_CHARSET = "utf8mb4";
        
        private static $instance = null;
        private $connection = null;
        
        private function __construct() {
            $this->connect();
        }
        
        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function connect() {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    self::DB_HOST, self::DB_PORT, self::DB_NAME, self::DB_CHARSET
                );
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 30,
                ];
                
                $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                $this->connection = null;
            }
        }
        
        public function testConnection() {
            try {
                if ($this->connection === null) return false;
                $stmt = $this->connection->query("SELECT 1");
                return $stmt !== false;
            } catch (PDOException $e) {
                return false;
            }
        }
    }

    // API Response Handler
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    
    date_default_timezone_set('UTC');
    
    $response = ['success' => false, 'error' => 'Unknown endpoint'];
    
    // Parse the path
    if ($query) {
        $endpoint = $query;
    } else {
        $pathParts = explode('/', trim($path, '/'));
        $endpoint = end($pathParts);
    }
    
    try {
        switch ($endpoint) {
            case 'status':
                $db = DatabaseConfig::getInstance();
                $response = [
                    'success' => true,
                    'message' => 'System status retrieved successfully',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'server' => [
                            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
                            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                        ],
                        'php' => [
                            'version' => phpversion(),
                            'memory_limit' => ini_get('memory_limit'),
                            'max_execution_time' => ini_get('max_execution_time'),
                            'post_max_size' => ini_get('post_max_size'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'timezone' => date_default_timezone_get(),
                        ],
                        'database' => [
                            'connected' => $db->testConnection(),
                            'host' => 'mysql-container',
                            'database' => 'mydb'
                        ],
                        'system' => [
                            'os' => PHP_OS,
                            'disk_free' => @disk_free_space('/') ? round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB' : 'N/A',
                            'disk_total' => @disk_total_space('/') ? round(disk_total_space('/') / 1024 / 1024 / 1024, 2) . ' GB' : 'N/A',
                        ]
                    ],
                    'timestamp' => time()
                ];
                break;
                
            case 'health':
                $db = DatabaseConfig::getInstance();
                $dbStatus = $db->testConnection();
                $response = [
                    'success' => true,
                    'message' => 'Health check completed',
                    'data' => [
                        'healthy' => $dbStatus,
                        'checks' => [
                            'database' => $dbStatus ? 'OK' : 'FAIL',
                            'php' => 'OK',
                            'apache' => 'OK',
                            'disk_space' => @disk_free_space('/') ? 'OK' : 'WARNING'
                        ]
                    ],
                    'timestamp' => time()
                ];
                break;
                
            case 'config':
                $response = [
                    'success' => true,
                    'message' => 'Configuration retrieved successfully',
                    'data' => [
                        'php' => [
                            'version' => phpversion(),
                            'memory_limit' => ini_get('memory_limit'),
                            'max_execution_time' => ini_get('max_execution_time'),
                            'post_max_size' => ini_get('post_max_size'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'timezone' => date_default_timezone_get(),
                            'display_errors' => ini_get('display_errors'),
                            'error_reporting' => error_reporting(),
                        ],
                        'server' => [
                            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                            'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'Unknown',
                        ]
                    ],
                    'timestamp' => time()
                ];
                break;
                
            case 'db-test':
                $db = DatabaseConfig::getInstance();
                $connected = $db->testConnection();
                $response = [
                    'success' => true,
                    'message' => 'Database test completed',
                    'data' => [
                        'connected' => $connected,
                        'message' => $connected ? 'Successfully connected to MySQL database' : 'Failed to connect to MySQL database',
                        'host' => 'mysql-container',
                        'database' => 'mydb',
                        'port' => 3306
                    ],
                    'timestamp' => time()
                ];
                break;
                
            default:
                $response = [
                    'success' => false,
                    'error' => 'Endpoint not found',
                    'timestamp' => time()
                ];
                http_response_code(404);
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
            'timestamp' => time()
        ];
        http_response_code(500);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// If not an API request, show the HTML dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP System Configuration Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .api-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }

        .api-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .api-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .api-card .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 28px;
        }

        .api-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        .api-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .api-card .endpoint {
            display: inline-block;
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-family: 'Courier New', monospace;
            color: #667eea;
            font-weight: bold;
        }

        .status-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
            animation: pulse 2s infinite;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            position: relative;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .modal-header .endpoint-url {
            font-family: 'Courier New', monospace;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 32px;
            color: white;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .json-viewer {
            background: #1e1e1e;
            border-radius: 10px;
            padding: 20px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #d4d4d4;
            line-height: 1.6;
        }

        .json-key {
            color: #9cdcfe;
        }

        .json-string {
            color: #ce9178;
        }

        .json-number {
            color: #b5cea8;
        }

        .json-boolean {
            color: #569cd6;
        }

        .json-null {
            color: #569cd6;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 3px solid #667eea;
        }

        .info-item label {
            display: block;
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .info-item .value {
            font-size: 1.1em;
            color: #333;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .badge.success {
            background: #d4edda;
            color: #155724;
        }

        .badge.error {
            background: #f8d7da;
            color: #721c24;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .api-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                max-height: 90vh;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 PHP System Configuration</h1>
            <p>Monitor and manage your PHP, MySQL, and Apache configuration</p>
        </div>

        <div class="api-grid">
            <div class="api-card" onclick="openModal('status')">
                <div class="status-indicator"></div>
                <div class="icon">📊</div>
                <h3>System Status</h3>
                <p>Complete overview of server, PHP, database, and system resources</p>
                <span class="endpoint">?action=status</span>
            </div>

            <div class="api-card" onclick="openModal('health')">
                <div class="status-indicator"></div>
                <div class="icon">💚</div>
                <h3>Health Check</h3>
                <p>Quick health check for all system components and services</p>
                <span class="endpoint">?action=health</span>
            </div>

            <div class="api-card" onclick="openModal('config')">
                <div class="status-indicator"></div>
                <div class="icon">⚙️</div>
                <h3>Configuration</h3>
                <p>View PHP and Apache server configuration details</p>
                <span class="endpoint">?action=config</span>
            </div>

            <div class="api-card" onclick="openModal('db-test')">
                <div class="status-indicator"></div>
                <div class="icon">🗄️</div>
                <h3>Database Test</h3>
                <p>Test MySQL database connectivity and connection status</p>
                <span class="endpoint">?action=db-test</span>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Loading...</h2>
                <div class="endpoint-url" id="modal-endpoint"></div>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modal-body">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Loading data...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = window.location.origin + window.location.pathname;
        
        const endpoints = {
            status: {
                title: '📊 System Status',
                url: '?action=status',
                description: 'Complete system overview'
            },
            health: {
                title: '💚 Health Check',
                url: '?action=health',
                description: 'System health status'
            },
            config: {
                title: '⚙️ Configuration',
                url: '?action=config',
                description: 'PHP & Apache configuration'
            },
            'db-test': {
                title: '🗄️ Database Test',
                url: '?action=db-test',
                description: 'Database connectivity test'
            }
        };

        function openModal(endpoint) {
            const modal = document.getElementById('modal');
            const title = document.getElementById('modal-title');
            const endpointEl = document.getElementById('modal-endpoint');
            const body = document.getElementById('modal-body');
            
            const config = endpoints[endpoint];
            title.textContent = config.title;
            endpointEl.textContent = config.url;
            
            body.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading data...</p></div>';
            modal.classList.add('active');
            
            fetchEndpoint(endpoint);
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

        async function fetchEndpoint(endpoint) {
            const body = document.getElementById('modal-body');
            const config = endpoints[endpoint];
            
            try {
                const response = await fetch(API_BASE + config.url);
                const data = await response.json();
                
                body.innerHTML = renderData(data, endpoint);
            } catch (error) {
                body.innerHTML = `
                    <div class="info-section">
                        <h3>❌ Error</h3>
                        <div class="info-item">
                            <label>Message</label>
                            <div class="value" style="color: #dc3545;">${error.message}</div>
                        </div>
                    </div>
                `;
            }
        }

        function renderData(data, endpoint) {
            let html = '';
            
            // Success/Error Badge
            if (data.success !== undefined) {
                html += `<div style="margin-bottom: 20px;">
                    <span class="badge ${data.success ? 'success' : 'error'}">
                        ${data.success ? '✓ Success' : '✗ Error'}
                    </span>
                </div>`;
            }
            
            // Render based on endpoint
            switch(endpoint) {
                case 'status':
                    html += renderSystemStatus(data);
                    break;
                case 'health':
                    html += renderHealthCheck(data);
                    break;
                case 'config':
                    html += renderConfig(data);
                    break;
                case 'db-test':
                    html += renderDbTest(data);
                    break;
            }
            
            // Raw JSON
            html += `
                <div class="info-section">
                    <h3>📄 Raw JSON Response</h3>
                    <div class="json-viewer">${syntaxHighlight(JSON.stringify(data, null, 2))}</div>
                </div>
            `;
            
            return html;
        }

        function renderSystemStatus(data) {
            let html = '<div class="info-section"><h3>🖥️ Server Information</h3><div class="info-grid">';
            
            if (data.data && data.data.server) {
                const server = data.data.server;
                html += `
                    ${infoItem('Server Software', server.server_software)}
                    ${infoItem('Server Name', server.server_name)}
                    ${infoItem('Protocol', server.server_protocol)}
                `;
            }
            
            html += '</div></div>';
            
            if (data.data && data.data.php) {
                html += '<div class="info-section"><h3>🐘 PHP Configuration</h3><div class="info-grid">';
                const php = data.data.php;
                html += `
                    ${infoItem('PHP Version', php.version)}
                    ${infoItem('Memory Limit', php.memory_limit)}
                    ${infoItem('Max Execution Time', php.max_execution_time + 's')}
                    ${infoItem('Post Max Size', php.post_max_size)}
                `;
                html += '</div></div>';
            }
            
            if (data.data && data.data.database) {
                html += '<div class="info-section"><h3>🗄️ Database Status</h3><div class="info-grid">';
                const db = data.data.database;
                html += `
                    ${infoItem('Connection', db.connected ? '✓ Connected' : '✗ Disconnected')}
                    ${infoItem('Host', db.host)}
                    ${infoItem('Database', db.database)}
                `;
                html += '</div></div>';
            }
            
            return html;
        }

        function renderHealthCheck(data) {
            let html = '<div class="info-section"><h3>🏥 Health Checks</h3><div class="info-grid">';
            
            if (data.data && data.data.checks) {
                const checks = data.data.checks;
                for (const [key, value] of Object.entries(checks)) {
                    const status = value === 'OK' ? '✓' : '✗';
                    const color = value === 'OK' ? '#28a745' : '#dc3545';
                    html += infoItem(key.charAt(0).toUpperCase() + key.slice(1), 
                                   `<span style="color: ${color}">${status} ${value}</span>`);
                }
            }
            
            html += '</div></div>';
            return html;
        }

        function renderConfig(data) {
            let html = '';
            
            if (data.data && data.data.php) {
                html += '<div class="info-section"><h3>🐘 PHP Settings</h3><div class="info-grid">';
                const php = data.data.php;
                for (const [key, value] of Object.entries(php)) {
                    html += infoItem(key.replace(/_/g, ' ').toUpperCase(), value);
                }
                html += '</div></div>';
            }
            
            if (data.data && data.data.server) {
                html += '<div class="info-section"><h3>🌐 Server Settings</h3><div class="info-grid">';
                const server = data.data.server;
                for (const [key, value] of Object.entries(server)) {
                    html += infoItem(key.replace(/_/g, ' ').toUpperCase(), value);
                }
                html += '</div></div>';
            }
            
            return html;
        }

        function renderDbTest(data) {
            let html = '<div class="info-section"><h3>🗄️ Database Connection</h3><div class="info-grid">';
            
            if (data.data) {
                html += infoItem('Status', 
                               data.data.connected ? 
                               '<span style="color: #28a745">✓ Connected</span>' : 
                               '<span style="color: #dc3545">✗ Failed</span>');
                html += infoItem('Message', data.data.message);
            }
            
            html += '</div></div>';
            return html;
        }

        function infoItem(label, value) {
            return `
                <div class="info-item">
                    <label>${label}</label>
                    <div class="value">${value}</div>
                </div>
            `;
        }

        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                let cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>