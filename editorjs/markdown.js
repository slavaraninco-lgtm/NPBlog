// --- Markdown to HTML Dynamic Compiler ---
function parseMarkdownToHtml(md) {
    if (!md) return '';
    let html = md;
    html = html.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    // 1. Code blocks (```lang ... ```)
    const codeBlocks = [];
    html = html.replace(/```([\s\S]*?)```/g, (match, codeContent) => {
        const lines = codeContent.split('\n');
        let lang = '';
        if (lines[0] && !lines[0].includes(' ') && lines[0].trim().length > 0) {
            lang = lines[0].trim();
            lines.shift();
        }
        const code = lines.join('\n');
        const escapedCode = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const placeholder = `__CODEBLOCK_PLACEHOLDER_${codeBlocks.length}__`;
        codeBlocks.push(`<pre><code class="${lang ? 'language-' + lang : ''}">${escapedCode}</code></pre>`);
        return placeholder;
    });

    // 2. Inline code (`code`)
    const inlineCodes = [];
    html = html.replace(/`([^`\n]+)`/g, (match, code) => {
        const escapedCode = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const placeholder = `__INLINECODE_PLACEHOLDER_${inlineCodes.length}__`;
        inlineCodes.push(`<code>${escapedCode}</code>`);
        return placeholder;
    });

    // 3. Block elements (line by line)
    let lines = html.split('\n');
    let inList = false;
    let listType = ''; // 'ul' or 'ol'
    let listItems = [];
    let newLines = [];

    const flushList = () => {
        if (inList) {
            let listHtml = `<${listType}>` + listItems.map(item => `<li>${parseInlineMarkdown(item)}</li>`).join('') + `</${listType}>`;
            newLines.push(listHtml);
            inList = false;
            listItems = [];
        }
    };

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];

        // Headers
        let headerMatch = line.match(/^(#{1,6})\s+(.*)$/);
        if (headerMatch) {
            flushList();
            let level = headerMatch[1].length;
            let text = headerMatch[2];
            newLines.push(`<h${level}>${parseInlineMarkdown(text)}</h${level}>`);
            continue;
        }

        // Blockquote
        let bqMatch = line.match(/^>\s*(.*)$/);
        if (bqMatch) {
            flushList();
            let text = bqMatch[1];
            newLines.push(`<blockquote>${parseInlineMarkdown(text)}</blockquote>`);
            continue;
        }

        // Unordered List
        let ulMatch = line.match(/^[\*\-\+]\s+(.*)$/);
        if (ulMatch) {
            if (inList && listType !== 'ul') {
                flushList();
            }
            inList = true;
            listType = 'ul';
            listItems.push(ulMatch[1]);
            continue;
        }

        // Ordered List
        let olMatch = line.match(/^(\d+)\.\s+(.*)$/);
        if (olMatch) {
            if (inList && listType !== 'ol') {
                flushList();
            }
            inList = true;
            listType = 'ol';
            listItems.push(olMatch[2]);
            continue;
        }

        // Table
        if (line.trim().startsWith('|')) {
            flushList();
            let isTable = false;
            if (i + 1 < lines.length && lines[i + 1].trim().startsWith('|') && lines[i + 1].includes('-')) {
                isTable = true;
            }
            if (isTable) {
                let tableLines = [];
                while (i < lines.length && lines[i].trim().startsWith('|')) {
                    tableLines.push(lines[i]);
                    i++;
                }
                i--;

                let tableHtml = '<table>';
                let headers = tableLines[0].split('|').map(x => x.trim()).filter((x, idx, arr) => idx > 0 && idx < arr.length - 1);
                tableHtml += '<thead><tr>' + headers.map(h => `<th>${parseInlineMarkdown(h)}</th>`).join('') + '</tr></thead>';
                tableHtml += '<tbody>';
                for (let j = 2; j < tableLines.length; j++) {
                    let cells = tableLines[j].split('|').map(x => x.trim()).filter((x, idx, arr) => idx > 0 && idx < arr.length - 1);
                    tableHtml += '<tr>' + cells.map(c => `<td>${parseInlineMarkdown(c)}</td>`).join('') + '</tr>';
                }
                tableHtml += '</tbody></table>';
                newLines.push(tableHtml);
                continue;
            }
        }

        // Empty line
        if (line.trim() === '') {
            flushList();
            newLines.push('');
            continue;
        }

        if (inList) {
            listItems[listItems.length - 1] += '\n' + line;
        } else {
            newLines.push(parseInlineMarkdown(line));
        }
    }
    flushList();

    let finalHtml = '';
    let pContent = [];
    for (let line of newLines) {
        if (line.trim() === '') {
            if (pContent.length > 0) {
                finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
                pContent = [];
            }
        } else if (line.startsWith('<h') || line.startsWith('<pre') || line.startsWith('<blockquote') || line.startsWith('<ul') || line.startsWith('<ol') || line.startsWith('<table') || line.startsWith('<details')) {
            if (pContent.length > 0) {
                finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
                pContent = [];
            }
            finalHtml += line + '\n';
        } else {
            pContent.push(line);
        }
    }
    if (pContent.length > 0) {
        finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
    }

    finalHtml = finalHtml.replace(/__INLINECODE_PLACEHOLDER_(\d+)__/g, (match, idx) => {
        return inlineCodes[parseInt(idx)];
    });
    finalHtml = finalHtml.replace(/__CODEBLOCK_PLACEHOLDER_(\d+)__/g, (match, idx) => {
        return codeBlocks[parseInt(idx)];
    });

    return finalHtml;
}

function parseInlineMarkdown(text) {
    let html = text;
    html = html.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([\s\S]*?)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([\s\S]*?)\*/g, '<em>$1</em>');
    html = html.replace(/_([\s\S]*?)_/g, '<em>$1</em>');
    html = html.replace(/~~([\s\S]*?)~~/g, '<del>$1</del>');
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="blog-image">');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    return html;
}

window.parseMarkdownToHtml = parseMarkdownToHtml;

function convertHtmlToMarkdown(html) {
    if (!html) return '';
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return nodeToMarkdown(temp).trim();
}

function nodeToMarkdown(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent;
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return '';
    }

    const tagName = node.tagName.toUpperCase();
    let childrenMarkdown = '';

    // Process children
    for (let child of node.childNodes) {
        childrenMarkdown += nodeToMarkdown(child);
    }

    switch (tagName) {
        case 'DIV':
            if (node.classList.contains('spoiler-content')) {
                return childrenMarkdown;
            }
            return childrenMarkdown + '\n';
        case 'P':
            return childrenMarkdown.trim() ? '\n\n' + childrenMarkdown.trim() + '\n\n' : '';
        case 'BR':
            return '\n';
        case 'STRONG':
        case 'B':
            return childrenMarkdown.trim() ? `**${childrenMarkdown.trim()}**` : '';
        case 'EM':
        case 'I':
            return childrenMarkdown.trim() ? `*${childrenMarkdown.trim()}*` : '';
        case 'DEL':
        case 'S':
            return childrenMarkdown.trim() ? `~~${childrenMarkdown.trim()}~~` : '';
        case 'H1':
            return `\n# ${childrenMarkdown.trim()}\n`;
        case 'H2':
            return `\n## ${childrenMarkdown.trim()}\n`;
        case 'H3':
            return `\n### ${childrenMarkdown.trim()}\n`;
        case 'H4':
            return `\n#### ${childrenMarkdown.trim()}\n`;
        case 'H5':
            return `\n##### ${childrenMarkdown.trim()}\n`;
        case 'H6':
            return `\n###### ${childrenMarkdown.trim()}\n`;
        case 'BLOCKQUOTE':
            const lines = childrenMarkdown.trim().split('\n');
            return '\n' + lines.map(line => `> ${line}`).join('\n') + '\n';
        case 'UL':
            return '\n' + childrenMarkdown + '\n';
        case 'OL':
            let olMarkdown = '\n';
            let index = 1;
            for (let child of node.childNodes) {
                if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toUpperCase() === 'LI') {
                    olMarkdown += `${index}. ${nodeToMarkdown(child).trim()}\n`;
                    index++;
                } else {
                    olMarkdown += nodeToMarkdown(child);
                }
            }
            return olMarkdown + '\n';
        case 'LI':
            if (node.parentNode && node.parentNode.tagName.toUpperCase() === 'OL') {
                return childrenMarkdown;
            }
            return `* ${childrenMarkdown.trim()}\n`;
        case 'A':
            const href = node.getAttribute('href') || '';
            return `[${childrenMarkdown.trim()}](${href})`;
        case 'IMG':
            const src = node.getAttribute('src') || '';
            const alt = node.getAttribute('alt') || 'Изображение';
            return `![${alt}](${src})`;
        case 'PRE':
            const codeEl = node.querySelector('code');
            if (codeEl) {
                let lang = '';
                for (let cls of codeEl.classList) {
                    if (cls.startsWith('language-')) {
                        lang = cls.replace('language-', '');
                    }
                }
                return `\n\`\`\`${lang}\n${codeEl.textContent}\n\`\`\`\n`;
            }
            return `\n\`\`\`\n${node.textContent}\n\`\`\`\n`;
        case 'CODE':
            if (node.parentNode && node.parentNode.tagName.toUpperCase() === 'PRE') {
                return childrenMarkdown;
            }
            return `\`${node.textContent}\``;
        case 'TABLE':
            let tableMd = '\n';
            const rows = node.querySelectorAll('tr');
            if (rows.length > 0) {
                const firstRowCells = rows[0].querySelectorAll('th, td');
                const colCount = firstRowCells.length;
                tableMd += '| ' + Array.from(firstRowCells).map(cell => nodeToMarkdown(cell).trim()).join(' | ') + ' |\n';
                tableMd += '| ' + Array.from({ length: colCount }, () => '---').join(' | ') + ' |\n';
                for (let r = 1; r < rows.length; r++) {
                    const cells = rows[r].querySelectorAll('th, td');
                    tableMd += '| ' + Array.from(cells).map(cell => nodeToMarkdown(cell).trim()).join(' | ') + ' |\n';
                }
            }
            return tableMd + '\n';
        case 'DETAILS':
            const summaryEl = node.querySelector('summary');
            const summaryText = summaryEl ? summaryEl.textContent : 'Подробности';
            const contentEl = node.querySelector('.spoiler-content') || node.querySelector('div');
            const contentMd = contentEl ? nodeToMarkdown(contentEl) : '';
            return `\n<details class="spoiler-block"><summary class="spoiler-title">${summaryText}</summary><div class="spoiler-content">${parseMarkdownToHtml(contentMd)}</div></details>\n`;
        default:
            return childrenMarkdown;
    }
}

window.convertHtmlToMarkdown = convertHtmlToMarkdown;
window.getCurrentEditId = function () {
    return typeof currentEditId !== 'undefined' ? currentEditId : null;
};
