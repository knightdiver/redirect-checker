<?php
// Version 2.2 - Improved CSV handling: remove protocol & domain from old URL
set_time_limit(300); // Set execution time to 5 minutes
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Disable buffering for real-time response
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', 'Off');
    ini_set('implicit_flush', 1);
    while (ob_get_level()) ob_end_flush();
    
    // Set headers for Server-Sent Events (SSE)
    header("Content-Type: text/event-stream");
    header("Cache-Control: no-cache");
    header("Connection: keep-alive");
    header("X-Accel-Buffering: no");
    
    $oldDomain = trim($_POST['old_domain'], '/');
    $protocol = $_POST['protocol'];
    $file = $_FILES['redirect_file']['tmp_name'];

    if (!$oldDomain || !file_exists($file) || !in_array($protocol, ['http', 'https'])) {
        echo "data: {\"error\": \"Invalid input. Please provide a domain, protocol, and a valid file.\"}\n\n";
        flush();
        exit;
    }

    $redirects = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total = count($lines);
    
    // Determine if the file is a CSV or an Nginx rewrite config
    if (pathinfo($_FILES['redirect_file']['name'], PATHINFO_EXTENSION) === 'csv') {
        // Handle CSV file
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if (count($data) >= 2) {
                    // Remove protocol & domain from old URL if present
                    $data[0] = preg_replace('/https?:\/\/[^\/]+/', '', trim($data[0]));
                    
                    // Ensure it starts with a leading slash
                    if (strpos($data[0], '/') !== 0) {
                        $data[0] = '/' . $data[0];
                    }
                    
                    $redirects[] = [
                        'line' => count($redirects) + 1,
                        'old_url' => "$protocol://$oldDomain" . $data[0],
                        'expected_redirect' => trim($data[1])
                    ];
                }
            }
            fclose($handle);
        }
    } else {
        // Assume it's an Nginx rewrite file
        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/rewrite \^(.*?)\$ (.*?) permanent;/', $line, $matches)) {
                $redirects[] = [
                    'line' => $lineNumber + 1,
                    'old_url' => "$protocol://$oldDomain" . $matches[1],
                    'expected_redirect' => $matches[2]
                ];
            }
        }
    }

function checkRedirect($oldUrl, $expectedUrl) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $headers = @get_headers($oldUrl, 1, $context);
    
    if (!$headers || !isset($headers['Location'])) {
        return "❌ Failed (No Redirect)";
    }

    $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];

    $expectedHost = parse_url($expectedUrl, PHP_URL_HOST) ?: '[No Domain]';
    $actualHost = parse_url($location, PHP_URL_HOST) ?: '[No Domain]';
    $expectedPath = parse_url($expectedUrl, PHP_URL_PATH) ?: '/';
    $actualPath = parse_url($location, PHP_URL_PATH) ?: '/';

    // ✅ Exact match
    if ($location === $expectedUrl) {
        return "✅ Success ($location)";
    }

    // ❓ Domain Mismatch: Expected has no domain, but actual does
    if ($expectedHost === '[No Domain]' && $actualHost !== '[No Domain]' && $expectedPath === $actualPath) {
        return "❓ Domain Mismatch (Expected: <mark>$expectedPath</mark> → Got: <mark>$actualHost$actualPath</mark>)";
    }

    // ❓ Domain Mismatch: Expected and actual have different domains, but path is the same
    if ($expectedPath === $actualPath && strtolower($expectedHost) !== strtolower($actualHost)) {
        return "❓ Domain Mismatch (Expected: <mark>$expectedHost</mark>$expectedPath → Got: <mark>$actualHost</mark>$actualPath)";
    }

    // ⚠️ Path Mismatch: Domains match, but paths differ
    if (strtolower($expectedHost) === strtolower($actualHost) && $expectedPath !== $actualPath) {
        $highlightedPath = str_replace($expectedPath, "<mark>$expectedPath</mark>", $actualPath);
        return "⚠️ Path Mismatch (Expected: $expectedUrl → Got: $location, Closest Match: $highlightedPath)";
    }

    // ⚠️ Complete Mismatch: Both domain and path are different
    return "⚠️ Complete Mismatch (Expected: <mark>$expectedUrl</mark> → Got: <mark>$location</mark>)";
}

    echo "data: {\"status\": \"Processing started...\", \"progress\": 0, \"clear\": true}\n\n";
    flush();

    foreach ($redirects as $index => $redirect) {
        $status = checkRedirect($redirect['old_url'], $redirect['expected_redirect']);
        $progress = round((($index + 1) / $total) * 100, 2);
        echo "data: {\"line\": {$redirect['line']}, \"old_url\": \"{$redirect['old_url']}\", \"expected_redirect\": \"{$redirect['expected_redirect']}\", \"status\": \"$status\", \"progress\": $progress}\n\n";
        flush();
    }

    echo "data: {\"status\": \"Processing complete.\", \"progress\": 100}\n\n";
    flush();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirect Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
        }
        .help-link {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 16px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .help-link:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            display: block;
            margin: 10px 0 5px;
            text-align: left;
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: 0.3s;
            margin-top: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .progress-container {
            width: 100%;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-top: 15px;
            overflow: hidden;
            height: 20px;
        }
        .progress-bar {
            width: 0%;
            height: 100%;
            background-color: #28a745;
            border-radius: 5px;
            transition: width 0.5s;
        }
        .table-container {
            width: 100%;
            max-width: 90%;
            margin: 20px auto 0;
            background: #fff;
            border-radius: 5px;
            overflow: hidden;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #343a40;
            color: white;
        }
        .radio-group {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById("redirectForm").addEventListener("submit", async function (event) {
                event.preventDefault();
                const resultsTable = document.getElementById("resultsTable");
                resultsTable.innerHTML = "";
                document.getElementById("status").innerText = "Processing...";
                document.querySelector(".progress-bar").style.width = "0%";

                const formData = new FormData(this);
                try {
                    const response = await fetch("index.php", { method: "POST", body: formData });
                    if (!response.body) {
                        document.getElementById("status").innerText = "Error: No response body.";
                        return;
                    }
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let receivedText = "";

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        receivedText += decoder.decode(value, { stream: true });
                        const newLines = receivedText.split("\n");

                        newLines.forEach(line => {
                            if (line.startsWith("data: ")) {
                                try {
                                    let jsonData = JSON.parse(line.replace("data: ", "").trim());
                                    if (jsonData.progress) {
                                        document.querySelector(".progress-bar").style.width = jsonData.progress + "%";
                                    }
                                    if (jsonData.status) {
                                        document.getElementById("status").innerText = jsonData.status;
                                    }
                                    if (jsonData.old_url) {
                                        let row = document.createElement("tr");
                                        row.innerHTML = `
                                            <td>${jsonData.line}</td>
                                            <td>${jsonData.old_url}</td>
                                            <td>${jsonData.expected_redirect}</td>
                                            <td>${jsonData.status}</td>
                                        `;
                                        resultsTable.appendChild(row);
                                    }
                                } catch (e) {
                                    console.error("JSON Parse Error:", e, line);
                                }
                            }
                        });
                        receivedText = "";
                    }
                } catch (error) {
                    console.error("Fetch error:", error);
                    document.getElementById("status").innerText = "An error occurred.";
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="help.html" class="help-link">Help & Instructions</a>
        <h2>FM Dev's Redirect Checker</h2>
        <form id="redirectForm" method="POST" enctype="multipart/form-data">
            <label for="old_domain">Old Domain:</label>
            <input type="text" id="old_domain" name="old_domain" required placeholder="www.example.com">
            
            <label>Protocol:</label>
            <div class="radio-group">
                <input type="radio" id="http" name="protocol" value="http" checked>
                <label for="http">HTTP</label>
                <input type="radio" id="https" name="protocol" value="https">
                <label for="https">HTTPS</label>
            </div>
            
            <label for="redirect_file">Upload Redirect File (.conf, .txt, .csv):</label>
            <input type="file" id="redirect_file" name="redirect_file" accept=".conf,.txt,.csv" required>
            
            <button type="submit">Test Redirects</button>
        </form>
        <p id="status">Waiting to start...</p>
        <div class="progress-container"><div class="progress-bar"></div></div>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Line #</th>
                    <th>Old URL</th>
                    <th>Expected Redirect</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="resultsTable"></tbody>
        </table>
    </div>
</body>
</html>