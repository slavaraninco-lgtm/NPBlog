const fs = require('fs');
const path = require('path');

let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { input += chunk; });
process.stdin.on('end', () => {
    try {
        const data = JSON.parse(input || '{}');
        const primaryKatexPath = path.resolve(__dirname, '../../data/blog/assets/katex/katex.js');
        const fallbackKatexPath = path.resolve(__dirname, '../../assets/katex/katex.js');
        
        let katexPath = primaryKatexPath;
        if (!fs.existsSync(katexPath) && fs.existsSync(fallbackKatexPath)) {
            katexPath = fallbackKatexPath;
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
