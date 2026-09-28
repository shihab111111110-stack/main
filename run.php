<?php
// cPanel-এর তথ্য অনুযায়ী কনফিগার করা হয়েছে
$cpanel_username = "axmirhostxy"; 
$app_folder      = "otp_bot"; 
$python_version  = "3.11"; 

// পাথ কনফিগারেশন
$python_path = "/home/{$cpanel_username}/virtualenv/{$app_folder}/{$python_version}/bin/python";
$script_path = "/home/{$cpanel_username}/{$app_folder}/main.py";
$log_path    = "/home/{$cpanel_username}/{$app_folder}/bot_output.log";

// সেম রুটে এরর লগ তৈরি করার ফাংশন
function log_controller_message($msg) {
    $log_file = __DIR__ . '/controller_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$status_class = '';

if ($action == 'start') {
    log_controller_message("Start Action Triggered.");
    $pid = shell_exec("pgrep -f '$script_path'");
    if (!empty($pid)) {
        $message = "বটটি ইতিমধ্যে সচল আছে। (PID: " . trim($pid) . ")";
        $status_class = 'warning';
        log_controller_message("Start skipped. Bot already running with PID: " . trim($pid));
    } else {
        log_controller_message("Attempting to start Python bot...");
        
        // সমাধান ১: HOME সেট করা হয়েছে এবং ইনপুট রিডাইরেকশন </dev/null ফিরিয়ে আনা হয়েছে
        $cmd = "export HOME=/home/{$cpanel_username} && cd /home/{$cpanel_username}/{$app_folder} && {$python_path} {$script_path} > {$log_path} 2>&1 </dev/null &";
        
        try {
            // সমাধান ২: লাইটস্পিড সার্ভারে পেজ হ্যাং হওয়া ঠেকাতে pclose(popen()) ব্যবহার করা হয়েছে
            pclose(popen($cmd, "r"));
            log_controller_message("Command executed: " . $cmd);
            sleep(3); // প্রসেসটি সচল হতে ৩ সেকেন্ড সময় দেওয়া হলো
            
            $new_pid = shell_exec("pgrep -f '$script_path'");
            if (!empty($new_pid)) {
                $message = "বটটি সফলভাবে চালু করা হয়েছে! (PID: " . trim($new_pid) . ")";
                $status_class = 'success';
                log_controller_message("Bot started successfully. Assigned PID: " . trim($new_pid));
            } else {
                $message = "বটটি চালু করা যায়নি। পাইথন কোডে কোনো ভুল থাকতে পারে।";
                $status_class = 'error';
                log_controller_message("ERROR: Command executed but no PID found after 3 seconds. Check bot_output.log");
            }
        } catch (\Throwable $e) {
            $message = "সিস্টেম ত্রুটি: " . $e->getMessage();
            $status_class = 'error';
            log_controller_message("EXCEPTION: " . $e->getMessage());
        }
    }
} elseif ($action == 'stop') {
    log_controller_message("Stop Action Triggered.");
    $pid = shell_exec("pgrep -f '$script_path'");
    if (!empty($pid)) {
        try {
            shell_exec("pkill -f '$script_path'");
            $message = "বটটি বন্ধ করা হয়েছে।";
            $status_class = 'error';
            log_controller_message("Bot killed successfully. Target script: " . $script_path);
        } catch (\Throwable $e) {
            log_controller_message("EXCEPTION on Stop: " . $e->getMessage());
        }
    } else {
        $message = "সচল কোনো বটের প্রসেস পাওয়া যায়নি।";
        $status_class = 'warning';
        log_controller_message("Stop ignored. No running process found.");
    }
} elseif ($action == 'status') {
    log_controller_message("Status Check Triggered.");
}

// বটের বর্তমান অবস্থা চেক করা
$current_pid = shell_exec("pgrep -f '$script_path'");
$is_running = !empty($current_pid);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot Controller</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: var(--card-bg);
            padding: 2.2rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            width: 90%;
            max-width: 420px;
            text-align: center;
            border: 1px solid #334155;
            position: relative;
        }
        h1 {
            font-size: 1.6rem;
            margin: 0 0 0.4rem 0;
            font-weight: 600;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 1.8rem;
        }
        .status-box {
            background-color: #0f172a;
            border-radius: 10px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-dot.active {
            background-color: var(--success);
            box-shadow: 0 0 10px var(--success);
            animation: pulse 1.6s infinite;
        }
        .status-dot.inactive {
            background-color: var(--danger);
            box-shadow: 0 0 10px var(--danger);
        }
        .status-text {
            font-weight: 600;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            line-height: 1.4;
        }
        .alert-success { background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #34d399; }
        .alert-error { background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #f87171; }
        .alert-warning { background-color: rgba(245, 158, 11, 0.15); border: 1px solid var(--warning); color: #fbbf24; }
        
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 1rem;
        }
        .btn {
            padding: 0.9rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-start { background-color: var(--success); }
        .btn-start:hover { background-color: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-stop { background-color: var(--danger); }
        .btn-stop:hover { background-color: #dc2626; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-status {
            grid-column: span 2;
            background-color: var(--primary);
        }
        .btn-status:hover { background-color: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); }
        
        .footer {
            margin-top: 1.8rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            border-top: 1px solid #334155;
            padding-top: 1rem;
        }

        /* লোডিং স্পিনার ডিজাইন */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .loading-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 15px;
            font-weight: 600;
            color: var(--text-main);
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <!-- লোডিং স্ক্রিন ওভারলে -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">প্রসেস করা হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন...</div>
    </div>

    <div class="container">
        <h1>Bot Controller</h1>
        <div class="subtitle">OTP Master Management Panel</div>
        
        <!-- বর্তমান স্ট্যাটাস কার্ড -->
        <div class="status-box">
            <span class="status-dot <?php echo $is_running ? 'active' : 'inactive'; ?>"></span>
            <span class="status-text" style="color: <?php echo $is_running ? 'var(--success)' : 'var(--danger)'; ?>;">
                <?php echo $is_running ? 'RUNNING' : 'STOPPED'; ?>
            </span>
        </div>

        <!-- অ্যাকশন মেসেজ উইন্ডো -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $status_class; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- কন্ট্রোল বাটন সমূহ -->
        <div class="btn-group">
            <a href="?action=start" class="btn btn-start">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                Start
            </a>
            <a href="?action=stop" class="btn btn-stop">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>
                Off
            </a>
            <a href="?action=status" class="btn btn-status">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                Refresh / Check Status
            </a>
        </div>

        <div class="footer">
            Python: <?php echo $python_version; ?> | Dir: <?php echo $app_folder; ?>
        </div>
    </div>

    <!-- ইন্টারঅ্যাক্টিভ জাভাস্ক্রিপ্ট লোডার স্ক্রিপ্ট -->
    <script>
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // লোডিং স্ক্রিন অন করা
                document.getElementById('loading-overlay').classList.add('show');
            });
        });
    </script>
</body>
</html>