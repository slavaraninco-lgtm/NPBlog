<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновление blog.html</title>
    <style>
        
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --primary-color:rgb(255, 255, 255);
        }
        
        [data-theme="dark"] {
            --bg-color: #121212;
             --text-color: #f5f5f5;
             --primary-color:rgb(0, 0, 0);
             --primary-color2:rgb(255, 255, 255);
        }
        
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .file-inputs {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .file-input {
            flex: 1;
        }
        textarea {
            width: 100%;
            height: 300px;
            font-family: monospace;
            background-color: var(--bg-color);
            color: var(--text-color);
             transition: background-color 0.3s, color 0.3s;
        }
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }
    </style>
</head>
<body>
    <h1>Обновление blog.html</h1>
    
    <div class="container">
        <div class="file-inputs">
            <div class="file-input">
                <h3>Старый blog.html</h3>
                <input type="file" id="sourceFile" accept=".html,.htm">
            </div>
            <div class="file-input">
                <h3>Новый blog.html</h3>
                <input type="file" id="targetFile" accept=".html,.htm">
            </div>
        </div>
        
        <button id="processBtn">Перенести</button>
        
        <div>
            <h3>Результат:</h3>
            <textarea id="resultContent" readonly></textarea>
        </div>
        
        <div class="result">
            <h3>Скачать обновленный файл:  (modified_file.html переиминовать в blog.html)</h3>
            <button id="downloadBtn">Скачать обновленный файл файл</button>
        </div>
    </div>

    <script>
        document.getElementById('processBtn').addEventListener('click', processFiles);
document.getElementById('downloadBtn').addEventListener('click', downloadResult);

let processedContent = '';

async function processFiles() {
    const sourceFileInput = document.getElementById('sourceFile');
    const targetFileInput = document.getElementById('targetFile');
    const resultTextarea = document.getElementById('resultContent');
    
    if (!sourceFileInput.files[0] || !targetFileInput.files[0]) {
        alert('Пожалуйста, выберите оба файла');
        return;
    }
    
    try {
        const sourceContent = await readFile(sourceFileInput.files[0]);
        const targetContent = await readFile(targetFileInput.files[0]);
        
        const sourceMainContent = extractMainContent(sourceContent);
        
        if (sourceMainContent === null) {
            throw new Error('В исходном файле не найдены теги <main>');
        }
        
        processedContent = replaceMainContent(targetContent, sourceMainContent);
        
        resultTextarea.value = processedContent;
    } catch (error) {
        alert('Ошибка: ' + error.message);
        console.error(error);
    }
}

function readFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = (e) => reject(new Error('Ошибка чтения файла'));
        reader.readAsText(file);
    });
}

function extractMainContent(html) {
    const mainStart = html.indexOf('<main>');
    const mainEnd = html.indexOf('</main>');
    
    if (mainStart === -1 || mainEnd === -1 || mainEnd <= mainStart) {
        return null;
    }
    
    return html.substring(mainStart + '<main>'.length, mainEnd).trim();
}

function replaceMainContent(html, newContent) {
    const mainStart = html.indexOf('<main>');
    const mainEnd = html.indexOf('</main>');
    
    if (mainStart === -1 || mainEnd === -1 || mainEnd <= mainStart) {
        throw new Error('В целевом файле не найдены теги <main>');
    }
    
    return html.substring(0, mainStart + '<main>'.length) + 
           '\n' + newContent + '\n' + 
           html.substring(mainEnd);
}

function downloadResult() {
    if (!processedContent) {
        alert('Сначала обработайте файлы');
        return;
    }
    
    const blob = new Blob([processedContent], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'modified_file.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
    </script>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
const body = document.body;


const currentTheme = localStorage.getItem('theme');
if (currentTheme) {
    body.setAttribute('data-theme', currentTheme);
}

themeToggle.addEventListener('click', () => {
    if (body.getAttribute('data-theme') === 'dark') {
        body.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
    } else {
        body.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    }
});
    </script>
</body>
</html>