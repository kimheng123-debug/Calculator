<?php
// Handle AJAX request to save conversion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Get the POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    $file = 'conversions.txt';
    $timestampFile = 'last_cleanup.txt';

    // Check if cleanup is needed (every 24 hours)
    if (file_exists($timestampFile)) {
        $lastCleanup = file_get_contents($timestampFile);
        $timeSinceLastCleanup = time() - intval($lastCleanup);

        // If more than 24 hours have passed, clear the file
        if ($timeSinceLastCleanup > 86400) { // 86400 seconds = 24 hours
            file_put_contents($file, ''); // Clear the file
            file_put_contents($timestampFile, time()); // Update last cleanup time
        }
    } else {
        // If timestamp file doesn't exist, create it
        file_put_contents($timestampFile, time());
    }

    // Create the conversion text
    $conversionText = sprintf(
        "Conversion Record:\n" .
            "Riel: %s\n" .
            "English: %s\n" .
            "Khmer: %s\n" .
            "USD: %s\n" .
            "Timestamp: %s\n" .
            "----------------------------------------\n",
        $data['riel'],
        $data['english'],
        $data['khmer'],
        $data['usd'],
        $data['timestamp']
    );

    // Append to the conversions.txt file
    if (file_put_contents($file, $conversionText, FILE_APPEND)) {
        echo json_encode(['success' => true, 'message' => 'Conversion saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save conversion']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Riel to Words (Khmer & English) + USD Conversion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            max-width: 800px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .card-header {
            background-color: darkblue;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #4a90e2;
            transition: all 0.3s ease;
        }

        .result:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background-color: #4a90e2;
            border-color: #4a90e2;
        }

        .btn-danger {
            background-color: #e74c3c;
            border-color: #e74c3c;
        }

        .btn-info {
            background-color: #2ecc71;
            border-color: #2ecc71;
            color: white;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        .history-item {
            padding: 10px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #2ecc71;
        }

        .history-header {
            background-color: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Convert Riel to Words (Khmer & English) + USD</h2>
            </div>
            <div class="card-body">
                <form id="rielForm">
                    <div class="mb-4">
                        <label class="form-label">Enter Amount in Riel (KHR)</label>
                        <input type="number" min="0" step="0.01" class="form-control" name="riel" id="riel" placeholder="Enter amount in Riel" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="convertBtn">Convert</button>
                        <button type="button" class="btn btn-danger" id="clearInputBtn">Clear Input</button>
                        <button type="button" class="btn btn-warning" id="clearHistoryBtn">Clear History</button>
                        <button type="button" class="btn btn-info" id="viewHistoryBtn">View History</button>
                    </div>
                </form>

                <div id="resultArea"></div>
            </div>
        </div>

        <script>
            // Load history from localStorage when page loads
            let conversionHistory = JSON.parse(localStorage.getItem('conversionHistory')) || [];

            // Form submission handler
            document.getElementById('rielForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const rielInput = document.getElementById('riel').value;

                // Convert to positive number if negative
                const number = Math.abs(parseFloat(rielInput));
                if (!rielInput || isNaN(number)) {
                    showResult("<div class='result text-danger'>Error: Please enter a valid number.</div>");
                    return;
                }

                convertRiel(number.toString());
            });

            // Convert Riel to words and USD
            function convertRiel(rielInput) {
                // Format the amount with 2 decimal places
                const riel = parseFloat(rielInput).toFixed(2);

                // Convert Riel to USD (using standard exchange rate)
                const usd = (parseFloat(riel) / 4000).toFixed(2);

                // Convert Riel to Words
                const rielWordsEN = convertNumberToWordsEN(riel) + " Riels";
                const rielWordsKH = convertNumberToWordsKH(riel) + " រៀល";

                // Format the result
                const resultHTML = `
                    <div class='result'><strong>Amount in Riel:</strong> ៛${new Intl.NumberFormat().format(riel)}</div>
                    <div class='result'><strong>In English:</strong> ${rielWordsEN}</div>
                    <div class='result'><strong>In Khmer:</strong> ${rielWordsKH}</div>
                    <div class='result'><strong>Exchange to USD:</strong> $${new Intl.NumberFormat().format(usd)}</div>
                `;

                // Save to in-memory history and localStorage
                const historyEntry = {
                    riel: `៛${new Intl.NumberFormat().format(riel)}`,
                    english: rielWordsEN,
                    khmer: rielWordsKH,
                    usd: `${new Intl.NumberFormat().format(usd)}`,
                    timestamp: new Date().toLocaleString()
                };

                conversionHistory.unshift(historyEntry);
                localStorage.setItem('conversionHistory', JSON.stringify(conversionHistory));

                // Save to text file using AJAX
                fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(historyEntry)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to save to text file:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving to text file:', error);
                    });

                // Display the result
                showResult(resultHTML);
            }

            // Show result in the result area
            function showResult(html) {
                document.getElementById('resultArea').innerHTML = html;
            }

            // Clear Input Button functionality
            document.getElementById('clearInputBtn').addEventListener('click', function() {
                document.getElementById('riel').value = '';
                document.getElementById('resultArea').innerHTML = '';
                showResult('<div class="result text-success">Input cleared successfully!</div>');
            });

            // Clear History Button functionality
            document.getElementById('clearHistoryBtn').addEventListener('click', function() {
                conversionHistory = [];
                localStorage.removeItem('conversionHistory');
                showResult('<div class="result text-success">History cleared successfully!</div>');
            });

            // View History Button functionality
            document.getElementById('viewHistoryBtn').addEventListener('click', function() {
                if (conversionHistory.length === 0) {
                    showResult('<div class="result">No conversion history found.</div>');
                    return;
                }

                let historyHtml = '<div class="history-header mt-4"><h4 class="mb-0">Conversion History</h4></div>';

                conversionHistory.forEach(entry => {
                    historyHtml += `
                        <div class="history-item">
                            <div><strong>Riel:</strong> ${entry.riel}</div>
                            <div><strong>English:</strong> ${entry.english}</div>
                            <div><strong>Khmer:</strong> ${entry.khmer}</div>
                            <div><strong>USD:</strong> ${entry.usd}</div>
                            <div class="text-muted small">${entry.timestamp}</div>
                        </div>`;
                });

                showResult(historyHtml);
            });

            // Format input as currency
            document.getElementById('riel').addEventListener('input', function(e) {
                let value = e.target.value;

                // Remove any non-numeric characters except decimal point
                value = value.replace(/[^\d.-]/g, '');

                // Ensure only one decimal point
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }

                // Limit to 2 decimal places
                if (parts[1] && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }

                // Convert to positive number if negative
                if (value.startsWith('-')) {
                    value = value.substring(1);
                }

                // Update the input value
                e.target.value = value;
            });

            // Prevent negative numbers on keydown
            document.getElementById('riel').addEventListener('keydown', function(e) {
                if (e.key === '-') {
                    e.preventDefault();
                }
            });

            // Function to convert number to English words with decimal support
            function convertNumberToWordsEN(num) {
                const ones = ["", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine"];
                const teens = ["", "eleven", "twelve", "thirteen", "fourteen", "fifteen", "sixteen", "seventeen", "eighteen", "nineteen"];
                const tens = ["", "ten", "twenty", "thirty", "forty", "fifty", "sixty", "seventy", "eighty", "ninety"];
                const thousands = ["", "thousand", "million", "billion", "trillion"];

                // Split number into integer and decimal parts
                const parts = num.toString().split('.');
                const integerPart = parts[0];
                const decimalPart = parts[1] || '00';

                // Convert integer part
                let result = '';
                if (integerPart == 0) {
                    result = "zero";
                } else {
                    let numStr = integerPart.toString();
                    // Pad to multiple of 3 for processing
                    const originalLength = numStr.length;
                    numStr = numStr.padStart(Math.ceil(numStr.length / 3) * 3, "0");
                    const chunks = [];

                    for (let i = 0; i < numStr.length; i += 3) {
                        chunks.push(numStr.substring(i, i + 3));
                    }

                    const convertedGroups = chunks.map((chunk, i) => {
                        const hundred = chunk[0] !== "0" ? ones[chunk[0]] + " hundred" : "";
                        let ten = "";
                        let one = "";

                        if (chunk[1] === "1" && chunk[2] !== "0") {
                            ten = teens[chunk[2]];
                        } else {
                            ten = chunk[1] !== "0" ? tens[chunk[1]] : "";
                            one = chunk[2] !== "0" && chunk[1] !== "1" ? ones[chunk[2]] : "";
                        }

                        const group = [hundred, ten, one].filter(Boolean).join(" ");
                        const suffix = thousands[chunks.length - i - 1];

                        if (group === "" && chunk === '000' && chunks.length - i - 1 > 0) {
                            return ""; // Skip empty chunks in the middle unless it's the last group
                        } else {
                            return group + (suffix ? " " + suffix : "");
                        }
                    }).filter(Boolean);

                    // Join the converted groups with '& '
                    result = convertedGroups.join("& ");
                }

                // Add decimal part if exists
                if (decimalPart !== '00') {
                    result += " point";
                    for (let i = 0; i < decimalPart.length; i++) {
                        result += " " + ones[decimalPart[i]];
                    }
                }

                return result.trim();
            }

            // Function to convert number to Khmer words with decimal support
            function convertNumberToWordsKH(num) {
                const khmerNumbers = {
                    0: "សូន្យ",
                    1: "មួយ",
                    2: "ពីរ",
                    3: "បី",
                    4: "បួន",
                    5: "ប្រាំ",
                    6: "ប្រាំមួយ",
                    7: "ប្រាំពីរ",
                    8: "ប្រាំបី",
                    9: "ប្រាំបួន",
                    10: "ដប់",
                    20: "ម្ភៃ",
                    30: "សាមសិប",
                    40: "សែសិប",
                    50: "ហាសិប",
                    60: "ហុកសិប",
                    70: "ចិតសិប",
                    80: "ប៉ែតសិប",
                    90: "កៅសិប"
                };
                const levels = ["", "ពាន់", "លាន", "ពាន់លាន", "ត្រីលាន"];

                // Split number into integer and decimal parts
                const parts = num.toString().split('.');
                const integerPart = parts[0];
                const decimalPart = parts[1] || '00';

                // Convert integer part
                let result = '';
                if (integerPart == 0) {
                    result = "សូន្យ";
                } else {
                    let numStr = integerPart.toString();
                    // Pad to multiple of 3 for processing
                    numStr = numStr.padStart(Math.ceil(numStr.length / 3) * 3, "0");
                    const chunks = [];

                    for (let i = 0; i < numStr.length; i += 3) {
                        chunks.push(numStr.substring(i, i + 3));
                    }

                    const output = [];

                    chunks.forEach((chunk, i) => {
                        const hundred = chunk[0] !== "0" ? khmerNumbers[chunk[0]] + "រយ" : "";
                        let ten = "";
                        let one = "";

                        if (chunk[1] === "1" && chunk[2] !== "0") {
                            ten = "ដប់" + khmerNumbers[chunk[2]];
                        } else {
                            ten = chunk[1] !== "0" ? khmerNumbers[chunk[1] + "0"] : "";
                            one = chunk[2] !== "0" && chunk[1] !== "1" ? khmerNumbers[chunk[2]] : "";
                        }

                        const group = hundred + ten + one;
                        if (group) {
                            const suffix = levels[chunks.length - i - 1];
                            output.push(group + suffix);
                        }
                    });

                    result = output.join("").trim();
                }

                // Add decimal part if exists
                if (decimalPart !== '00') {
                    result += "ក្បៀស";
                    for (let i = 0; i < decimalPart.length; i++) {
                        result += khmerNumbers[decimalPart[i]];
                    }
                }

                return result.trim();
            }
        </script>
    </div>
</body>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>

</html>