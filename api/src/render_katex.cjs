const fs = require('fs');
const path = require('path');

let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { input += chunk; });
process.stdin.on('end', () => {
    try {
        const data = JSON.parse(input || '{}');
        // Загрузка KaTeX строго из неизменяемого системного каталога assets/
        const katexPath = path.resolve(__dirname, '../../assets/katex/katex.js');
        if (!fs.existsSync(katexPath)) {
            throw new Error('KaTeX core library not found in assets/katex/katex.js');
        }

        const katex = require(katexPath);
        const tex = typeof data.tex === 'string' ? data.tex : '';
        const displayMode = Boolean(data.displayMode);
        const throwOnError = Boolean(data.throwOnError);

        const html = katex.renderToString(tex, {
            displayMode: displayMode,
            throwOnError: throwOnError
        });

        process.stdout.write(JSON.stringify({
            success: true,
            html: html
        }));
    } catch (err) {
        process.stdout.write(JSON.stringify({
            success: false,
            error: err && err.message ? err.message : String(err)
        }));
    }
});
