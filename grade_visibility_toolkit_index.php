<?php
/**
 * Grade Visibility Fix - Complete Toolkit Index
 * Navigate all diagnostic and fix tools from one central location
 */
define('SYSTEM_ACCESS', true);
require_once 'config/session.php';

startSecureSession();

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'admin'])) {
    header('Location: login.php');
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Visibility Fix - Complete Toolkit</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        header { 
            text-align: center; 
            color: white; 
            margin-bottom: 50px;
        }
        header h1 { font-size: 2.5em; margin-bottom: 10px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        header p { font-size: 1.1em; opacity: 0.9; }

        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
            gap: 25px;
            margin-bottom: 50px;
        }

        .card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .card-header {
            padding: 25px;
            color: white;
            font-weight: bold;
            font-size: 1.3em;
        }

        .card.primary .card-header { background: linear-gradient(135deg, #003082 0%, #005eb8 100%); }
        .card.secondary .card-header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .card.info .card-header { background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); }
        .card.warning .card-header { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }

        .card-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .card-description {
            color: #666;
            margin-bottom: 15px;
            flex: 1;
            line-height: 1.6;
        }

        .card-features {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .card-features li {
            margin-left: 20px;
            margin-bottom: 5px;
            color: #555;
        }

        .card-features li:last-child { margin-bottom: 0; }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 1em;
        }

        .btn-primary { background: #003082; color: white; }
        .btn-primary:hover { background: #002050; transform: scale(1.05); }

        .btn-secondary { background: #28a745; color: white; }
        .btn-secondary:hover { background: #218838; transform: scale(1.05); }

        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; transform: scale(1.05); }

        .btn-warning { background: #ff9800; color: white; }
        .btn-warning:hover { background: #e68900; transform: scale(1.05); }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            margin-right: 8px;
            margin-bottom: 10px;
        }

        .badge-new { background: #28a745; color: white; }
        .badge-recommended { background: #ffc107; color: #333; }
        .badge-quick { background: #17a2b8; color: white; }

        .info-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .info-section h2 {
            color: #003082;
            margin-bottom: 20px;
            border-bottom: 3px solid #003082;
            padding-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #003082;
            border-radius: 4px;
        }

        .info-item strong { color: #003082; }
        .info-item p { margin-top: 8px; color: #666; font-size: 0.95em; }

        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        footer {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 768px) {
            header h1 { font-size: 1.8em; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🔐 Grade Visibility Fix Toolkit</h1>
        <p>Complete suite of diagnostic and repair tools</p>
    </header>

    <!-- Main Tools -->
    <div class="grid">
        <!-- MAIN FIX TOOL -->
        <div class="card primary">
            <div class="card-header">⭐ Grade Visibility Debug (MAIN TOOL)</div>
            <div class="card-content">
                <span class="badge badge-recommended">RECOMMENDED</span>
                <span class="badge badge-new">NEW</span>
                
                <div class="card-description">
                    The primary tool for checking and fixing grade visibility issues. Select your class and click a button to decrypt/encrypt all grades at once.
                </div>

                <div class="card-features">
                    <strong>Features:</strong>
                    <ul>
                        <li>✓ Check current encryption status</li>
                        <li>✓ One-click decrypt/encrypt</li>
                        <li>✓ Progress indicators</li>
                        <li>✓ Automatic refresh</li>
                    </ul>
                </div>

                <a href="grade_visibility_debug.php" class="btn btn-primary">Open Debug Tool →</a>
            </div>
        </div>

        <!-- HEALTH CHECK -->
        <div class="card secondary">
            <div class="card-header">🏥 Encryption Health Check</div>
            <div class="card-content">
                <span class="badge badge-new">NEW</span>
                
                <div class="card-description">
                    Verify the encryption system is working correctly. Run this first if grades won't decrypt or you see encryption errors.
                </div>

                <div class="card-features">
                    <strong>Tests:</strong>
                    <ul>
                        <li>✓ Encryption class exists</li>
                        <li>✓ Keys configured</li>
                        <li>✓ Encrypt/decrypt works</li>
                        <li>✓ Database tables OK</li>
                    </ul>
                </div>

                <a href="encryption_health_check.php" class="btn btn-secondary">Run Health Check →</a>
            </div>
        </div>

        <!-- DETAILED DIAGNOSIS -->
        <div class="card info">
            <div class="card-header">🔍 Detailed Diagnosis</div>
            <div class="card-content">
                <span class="badge badge-new">NEW</span>
                
                <div class="card-description">
                    Analyze a specific student's grade status. Shows exactly why a student sees locked or visible grades.
                </div>

                <div class="card-features">
                    <strong>Shows:</strong>
                    <ul>
                        <li>✓ Database state</li>
                        <li>✓ Encryption status</li>
                        <li>✓ Visibility records</li>
                        <li>✓ Root cause analysis</li>
                    </ul>
                </div>

                <a href="grade_visibility_diagnostic.php" class="btn btn-info">Open Diagnostic →</a>
            </div>
        </div>

        <!-- MANUAL FIX -->
        <div class="card warning">
            <div class="card-header">🔧 Manual Grade Fix</div>
            <div class="card-content">
                <span class="badge badge-new">NEW</span>
                
                <div class="card-description">
                    Manual override tool. Bypass the faculty UI and directly decrypt/encrypt grades for any class.
                </div>

                <div class="card-features">
                    <strong>Features:</strong>
                    <ul>
                        <li>✓ List all classes</li>
                        <li>✓ Show status per class</li>
                        <li>✓ Direct decryption</li>
                        <li>✓ No faculty UI needed</li>
                    </ul>
                </div>

                <a href="manual_decrypt_grades.php" class="btn btn-warning">Open Manual Fix →</a>
            </div>
        </div>
    </div>

    <!-- Quick Start Section -->
    <div class="info-section">
        <h2>⚡ Quick Start (5 Minutes)</h2>
        <ol style="margin-left: 30px; line-height: 1.8; color: #333;">
            <li><strong>Open:</strong> <code>grade_visibility_debug.php</code></li>
            <li><strong>Select:</strong> Your class (e.g., CCPRGG1L)</li>
            <li><strong>Check:</strong> Current Encryption Status section</li>
            <li><strong>If HIDDEN:</strong> Click "🔓 SHOW GRADES" button</li>
            <li><strong>Wait:</strong> For operation to complete</li>
            <li><strong>Verify:</strong> Have student hard refresh (Ctrl+Shift+R)</li>
        </ol>
    </div>

    <!-- Tools Reference -->
    <div class="info-section">
        <h2>📚 Tools Reference</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>grade_visibility_debug.php</strong>
                <p>Check and fix grades. Best for: Quick fixes and status checks.</p>
            </div>
            <div class="info-item">
                <strong>encryption_health_check.php</strong>
                <p>System verification. Best for: Diagnosing encryption issues.</p>
            </div>
            <div class="info-item">
                <strong>grade_visibility_diagnostic.php</strong>
                <p>Detailed analysis. Best for: Debugging specific students.</p>
            </div>
            <div class="info-item">
                <strong>manual_decrypt_grades.php</strong>
                <p>Manual override. Best for: Bypassing UI issues.</p>
            </div>
            <div class="info-item">
                <strong>test_hide_grades.php</strong>
                <p>Basic testing. Best for: General testing and verification.</p>
            </div>
            <div class="info-item">
                <strong>verify_hide_grades.php</strong>
                <p>System status. Best for: Overall system health check.</p>
            </div>
        </div>
    </div>

    <!-- Documentation -->
    <div class="info-section">
        <h2>📖 Documentation</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong><a href="QUICK_START_GRADE_FIX.md" style="color: #003082; text-decoration: none;">QUICK_START_GRADE_FIX.md</a></strong>
                <p>Fast reference guide with 5-minute fix instructions.</p>
            </div>
            <div class="info-item">
                <strong><a href="GRADE_VISIBILITY_FIX_README.md" style="color: #003082; text-decoration: none;">GRADE_VISIBILITY_FIX_README.md</a></strong>
                <p>Complete documentation with troubleshooting guide.</p>
            </div>
            <div class="info-item">
                <strong><a href="GRADE_VISIBILITY_DEBUG_TOOLS.md" style="color: #003082; text-decoration: none;">GRADE_VISIBILITY_DEBUG_TOOLS.md</a></strong>
                <p>Detailed tool documentation and usage guide.</p>
            </div>
        </div>
    </div>

    <!-- Problem Solving Tree -->
    <div class="info-section">
        <h2>🌳 Problem Solving Guide</h2>
        
        <p style="margin-bottom: 20px; color: #333;">Follow this tree to find your solution:</p>

        <div style="margin-left: 20px;">
            <p><strong>1️⃣ Does faculty see "VISIBLE TO STUDENTS" status?</strong></p>
            <div style="margin-left: 20px; color: #666;">
                <p><strong>YES →</strong> But student sees locked grades?</p>
                <div style="margin-left: 20px;">
                    <p>Use: <code>grade_visibility_diagnostic.php</code> for specific student</p>
                </div>
                <p style="margin-top: 10px;"><strong>NO →</strong> Status shows "HIDDEN FROM STUDENTS"?</p>
                <div style="margin-left: 20px;">
                    <p>Use: <code>grade_visibility_debug.php</code> → Click "SHOW GRADES"</p>
                </div>
            </div>

            <p style="margin-top: 20px;"><strong>2️⃣ Did you use a debug tool and still see issues?</strong></p>
            <div style="margin-left: 20px; color: #666;">
                <p>Use: <code>encryption_health_check.php</code> to verify system</p>
            </div>

            <p style="margin-top: 20px;"><strong>3️⃣ Are there red failures in the health check?</strong></p>
            <div style="margin-left: 20px; color: #666;">
                <p>System config issue - Check .env file has APP_ENCRYPTION_KEY set</p>
            </div>
        </div>
    </div>

    <!-- Support -->
    <footer>
        <p><strong>Need Help?</strong></p>
        <p>1. Start with: <code>grade_visibility_debug.php</code></p>
        <p>2. Read: <code>QUICK_START_GRADE_FIX.md</code></p>
        <p>3. Check: <code>encryption_health_check.php</code></p>
        <p style="margin-top: 15px; opacity: 0.8;">Created: November 25, 2024 | Status: ✅ Complete</p>
    </footer>

</div>

</body>
</html>
