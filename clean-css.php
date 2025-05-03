<?php
session_start();

// Clean uploads folder on initial load
if (empty($_SESSION['initial_load'])) {
    function cleanUploadsFolder() {
        $uploadDir = __DIR__ . '/uploads/';
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    cleanUploadsFolder();
    $_SESSION['initial_load'] = true;
}

function removeDuplicateCSSRules($cssContent) {
    $pattern = '/([^{]+)\{([^}]*)\}/s';
    preg_match_all($pattern, $cssContent, $matches, PREG_SET_ORDER);

    $seen = [];
    $uniqueRules = [];
    $hasDuplicates = false;
    $processingSteps = [];

    foreach ($matches as $index => $match) {
        $selector = trim($match[1]);
        $properties = trim(preg_replace('/\s+/', ' ', $match[2]));
        $key = $selector . '|' . $properties;

        if (!isset($seen[$key])) {
            $seen[$key] = $index;
            $uniqueRules[$index] = [$selector, $properties];
            $processingSteps[] = [
                'selector' => $selector,
                'properties' => $properties,
                'action' => 'keep',
                'reason' => 'First occurrence'
            ];
        } else {
            $processingSteps[] = [
                'selector' => $selector,
                'properties' => $properties,
                'action' => 'remove',
                'reason' => 'Duplicate rule'
            ];
            unset($uniqueRules[$seen[$key]]);
            $seen[$key] = $index;
            $uniqueRules[$index] = [$selector, $properties];
            $hasDuplicates = true;
        }
    }

    $cleaned = '';
    foreach ($uniqueRules as [$selector, $properties]) {
        $cleaned .= "$selector {\n  $properties\n}\n";
    }

    return [
        'cleaned_css' => trim($cleaned),
        'has_duplicates' => $hasDuplicates,
        'processing_steps' => $processingSteps,
        'original_css' => $cssContent
    ];
}

// Main processing
$success = false;
$error = null;
$downloadLink = null;
$processingData = null;
$processingComplete = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['css_file']) && $_FILES['css_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $tmpFile = $_FILES['css_file']['tmp_name'];
        $originalName = basename($_FILES['css_file']['name']);
        $outputName = 'cleaned_' . $originalName;
        $inputPath = $uploadDir . $originalName;
        $outputPath = $uploadDir . $outputName;

        if (move_uploaded_file($tmpFile, $inputPath)) {
            $originalContent = file_get_contents($inputPath);
            $processingData = removeDuplicateCSSRules($originalContent);
            file_put_contents($outputPath, $processingData['cleaned_css']);
            $downloadLink = "uploads/" . $outputName;
            $success = true;
            $processingComplete = true;
        } else {
            $error = "Gagal mengunggah file.";
        }
    } elseif (isset($_POST['css_code'])) {
        $originalContent = $_POST['css_code'];
        $processingData = removeDuplicateCSSRules($originalContent);
        $success = true;
        $processingComplete = true;
    } elseif (isset($_POST['css_url'])) {
        $url = $_POST['css_url'];
        $originalContent = @file_get_contents($url);
        if ($originalContent === FALSE) {
            $error = "Gagal mengambil CSS dari URL.";
        } else {
            $processingData = removeDuplicateCSSRules($originalContent);
            $success = true;
            $processingComplete = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSS Cleaner Pro</title>
  <link rel="apple-touch-icon" sizes="57x57" href="//assets.sangia.org/static/favicon/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="//assets.sangia.org/static/favicon/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="//assets.sangia.org/static/favicon/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="//assets.sangia.org/static/favicon/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="//assets.sangia.org/static/favicon/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="//assets.sangia.org/static/favicon/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="//assets.sangia.org/static/favicon/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="//assets.sangia.org/static/favicon/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="//assets.sangia.org/static/favicon/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192"  href="//assets.sangia.org/static/favicon/android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="//assets.sangia.org/static/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="//assets.sangia.org/static/favicon/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="//assets.sangia.org/static/favicon/favicon-16x16.png">
  <link rel="manifest" href="//assets.sangia.org/static/favicon/manifest.json">  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/codemirror.min.css">
  <style>
    :root {
      --primary: #4CAF50;
      --primary-hover: #45a049;
      --secondary: #2196F3;
      --secondary-hover: #0b7dda;
      --danger: #f44336;
      --dark: #333;
      --light: #f5f5f5;
      --header-height: 60px;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--light);
      height: 100vh;
      overflow: hidden;
    }
    
    .container {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      height: 100vh;
      display: flex;
      flex-direction: column;
    }
    
    h1 {
      text-align: center;
      color: var(--dark);
      padding: 15px 0;
      background-color: white;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .upload-section {
      background-color: white;
      padding: 20px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      position: sticky;
      top: var(--header-height);
      z-index: 90;
      transition: transform 0.3s ease;
    }
    
    .upload-section.hidden {
      transform: translateY(-100%);
    }
    
    .upload-options {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .upload-options input[type="file"],
    .upload-options input[type="text"] {
      flex: 1;
      min-width: 200px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    
    textarea {
      width: 100%;
      margin-top: 10px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      min-height: 100px;
    }
    
    .btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .btn:hover {
      background: var(--primary-hover);
    }
    
    .btn-secondary {
      background: var(--secondary);
    }
    
    .btn-secondary:hover {
      background: var(--secondary-hover);
    }
    
    .results-section {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      position: relative;
    }
    
    .editor-container {
      display: flex;
      gap: 20px;
      flex: 1;
      min-height: 0;
      padding: 10px 0;
    }
    
    .code-editor {
      flex: 1;
      display: flex;
      flex-direction: column;
      border: 1px solid #ddd;
      border-radius: 5px;
      overflow: hidden;
      min-height: 0;
      background: white;
    }
    
    .editor-header {
      background: var(--dark);
      color: white;
      padding: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .editor-title {
      font-weight: bold;
    }
    
    .editor-actions {
      display: flex;
      gap: 5px;
    }
    
    .CodeMirror {
      height: 100%;
      flex: 1;
    }
    
    .success {
      color: var(--primary);
      font-weight: bold;
      margin-bottom: 10px;
      padding: 0 10px;
    }
    
    .error {
      color: var(--danger);
      font-weight: bold;
      margin-bottom: 10px;
      padding: 0 10px;
    }
    
    .notification {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: var(--primary);
      color: white;
      padding: 10px 20px;
      border-radius: 4px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s ease;
      z-index: 1000;
    }
    
    .notification.show {
      transform: translateY(0);
      opacity: 1;
    }
    
    @media (max-width: 768px) {
      .editor-container {
        flex-direction: column;
      }
      
      .code-editor {
        height: 50%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>CSS Cleaner Pro</h1>
    
    <div class="upload-section" id="uploadSection">
      <form method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="upload-options">
          <input type="file" name="css_file" accept=".css">
          <button type="submit" class="btn">Unggah & Periksa</button>
        </div>
        <textarea name="css_code" placeholder="Tempelkan kode CSS di sini..." rows="5"></textarea>
        <input type="text" name="css_url" placeholder="Masukkan URL CSS...">
      </form>
    </div>

    <?php if ($processingComplete): ?>
      <div class="results-section" id="resultsSection">
        <?php if ($success): ?>
          <p class="success">✅ File berhasil dibersihkan.</p>
          <div class="editor-container">
            <div class="code-editor">
              <div class="editor-header">
                <span class="editor-title">Original CSS</span>
                <div class="editor-actions">
                  <button class="btn-secondary paste-btn" data-target="original-css">Tempel</button>
                </div>
              </div>
              <textarea id="original-css"><?= htmlspecialchars($processingData['original_css']) ?></textarea>
            </div>
            <div class="code-editor">
              <div class="editor-header">
                <span class="editor-title">Cleaned CSS</span>
                <div class="editor-actions">
                  <button class="btn copy-btn" data-target="cleaned-css">Salin</button>
                </div>
              </div>
              <textarea id="cleaned-css"><?= htmlspecialchars($processingData['cleaned_css']) ?></textarea>
            </div>
          </div>
        <?php else: ?>
          <p class="error">❌ <?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="notification" id="notification">Teks berhasil disalin!</div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/mode/css/css.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Initialize CodeMirror editors
      const originalEditor = CodeMirror.fromTextArea(document.getElementById('original-css'), {
        lineNumbers: true,
        mode: 'css',
        readOnly: true,
        viewportMargin: Infinity
      });

      const cleanedEditor = CodeMirror.fromTextArea(document.getElementById('cleaned-css'), {
        lineNumbers: true,
        mode: 'css',
        readOnly: true,
        viewportMargin: Infinity
      });

      // Scroll behavior for upload section
      const uploadSection = document.getElementById('uploadSection');
      const resultsSection = document.getElementById('resultsSection');
      let lastScrollPosition = 0;

      if (resultsSection) {
        resultsSection.addEventListener('scroll', (e) => {
          const currentScrollPosition = resultsSection.scrollTop;
          
          if (currentScrollPosition > lastScrollPosition && currentScrollPosition > 50) {
            // Scrolling down
            uploadSection.classList.add('hidden');
          } else {
            // Scrolling up
            uploadSection.classList.remove('hidden');
          }
          
          lastScrollPosition = currentScrollPosition;
        });
      }

      // Copy button functionality
      document.querySelectorAll('.copy-btn').forEach(button => {
        button.addEventListener('click', async () => {
          const targetId = button.getAttribute('data-target');
          const editor = targetId === 'original-css' ? originalEditor : cleanedEditor;
          const text = editor.getValue();
          
          try {
            await navigator.clipboard.writeText(text);
            showNotification('CSS berhasil disalin!');
          } catch (err) {
            showNotification('Gagal menyalin CSS');
            console.error('Failed to copy text: ', err);
          }
        });
      });

      // Paste button functionality
      document.querySelectorAll('.paste-btn').forEach(button => {
        button.addEventListener('click', async () => {
          try {
            const text = await navigator.clipboard.readText();
            originalEditor.setValue(text);
            showNotification('CSS berhasil ditempel!');
          } catch (err) {
            showNotification('Gagal menempel CSS');
            console.error('Failed to paste text: ', err);
          }
        });
      });

      // Notification function
      function showNotification(message) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.classList.add('show');
        
        setTimeout(() => {
          notification.classList.remove('show');
        }, 3000);
      }

      // Adjust editor heights
      function adjustEditorHeights() {
        const containerHeight = document.querySelector('.container').offsetHeight;
        const headerHeight = document.querySelector('h1').offsetHeight;
        const uploadSectionHeight = document.querySelector('.upload-section').offsetHeight;
        const availableHeight = containerHeight - headerHeight - 20; // 20px padding
        
        document.querySelectorAll('.CodeMirror').forEach(editor => {
          editor.getWrapperElement().style.height = `${availableHeight}px`;
          editor.refresh();
        });
      }

      // Initial adjustment and on window resize
      adjustEditorHeights();
      window.addEventListener('resize', adjustEditorHeights);
    });
  </script>
</body>
</html>
