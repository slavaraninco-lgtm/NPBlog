<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class FormulasController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
    }

    /**
     * Render KaTeX formula via server-side Node.js runner
     */
    public static function renderKatex(string $tex, bool $displayMode = false, bool $throwOnError = false): array
    {
        $runner = dirname(__DIR__) . '/render_katex.cjs';
        if (!file_exists($runner)) {
            return [
                'success' => false,
                'error' => 'Runner render_katex.cjs not found',
                'html' => htmlspecialchars($tex, ENT_QUOTES, 'UTF-8')
            ];
        }

        $inputJson = json_encode([
            'tex' => $tex,
            'displayMode' => $displayMode,
            'throwOnError' => $throwOnError
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $cmd = 'node "' . $runner . '"';
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return [
                'success' => false,
                'error' => 'Node.js is not available on this server',
                'html' => htmlspecialchars($tex, ENT_QUOTES, 'UTF-8')
            ];
        }

        fwrite($pipes[0], $inputJson);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($proc);

        $result = json_decode($stdout ?: '[]', true);
        if (is_array($result) && !empty($result['success'])) {
            return [
                'success' => true,
                'html' => (string)($result['html'] ?? ''),
                'error' => null
            ];
        }

        return [
            'success' => false,
            'html' => htmlspecialchars($tex, ENT_QUOTES, 'UTF-8'),
            'error' => (string)($result['error'] ?? ($stderr ?: 'KaTeX render failed'))
        ];
    }

    /**
     * POST /api/v1/formulas/render
     * Рендеринг TeX формулы в готовый KaTeX HTML и разметку NPBlog
     */
    public function render(array $params, array $body): void
    {
        Auth::requireAuth();

        $tex = trim((string)($body['tex'] ?? $body['latex'] ?? $body['formula'] ?? ''));
        if ($tex === '') {
            Response::error('missing_formula', 'Параметр формулы (tex, latex или formula) обязателен', 422);
        }

        $display = strtolower(trim((string)($body['display'] ?? 'inline')));
        if (!in_array($display, ['inline', 'block'], true)) {
            $display = 'inline';
        }

        $size = strtolower(trim((string)($body['size'] ?? 'normal')));
        if (!in_array($size, ['normal', 'large', 'huge'], true)) {
            $size = 'normal';
        }

        $isBlock = ($display === 'block');
        $renderResult = self::renderKatex($tex, $isBlock, false);
        $escapedTex = htmlspecialchars($tex, ENT_QUOTES, 'UTF-8');
        $renderedKatex = $renderResult['html'];

        if ($isBlock) {
            $html = '<div class="npblog-formula-block" data-formula="' . $escapedTex . '" data-display="block" data-size="' . $size . '" contenteditable="false" title="Формула (Двойной клик для редактирования)">' . $renderedKatex . '</div>';
        } else {
            $html = '<span class="npblog-formula" data-formula="' . $escapedTex . '" data-display="inline" data-size="' . $size . '" contenteditable="false" title="Формула (Двойной клик для редактирования)">' . $renderedKatex . '</span>';
        }

        Response::json([
            'tex' => $tex,
            'display' => $display,
            'size' => $size,
            'html' => $html,
            'rendered_katex' => $renderedKatex,
            'is_valid' => $renderResult['success'],
            'error' => $renderResult['error']
        ], 200, 'Формула успешно отрендерена');
    }

    /**
     * POST /api/v1/formulas/validate
     * Проверка синтаксиса LaTeX формулы
     */
    public function validate(array $params, array $body): void
    {
        Auth::requireAuth();

        $tex = trim((string)($body['tex'] ?? $body['latex'] ?? $body['formula'] ?? ''));
        if ($tex === '') {
            Response::error('missing_formula', 'Параметр формулы (tex, latex или formula) обязателен', 422);
        }

        $renderResult = self::renderKatex($tex, false, true);

        if ($renderResult['success']) {
            Response::json([
                'valid' => true,
                'tex' => $tex
            ], 200, 'Синтаксис формулы валиден');
        } else {
            Response::json([
                'valid' => false,
                'tex' => $tex,
                'error' => $renderResult['error']
            ], 200, 'Синтаксис формулы содержит ошибки');
        }
    }

    /**
     * GET /api/v1/formulas/presets
     * Каталог предустановленных формул и математических шаблонов
     */
    public function presets(array $params, array $body): void
    {
        Auth::requireAuth();

        $presets = [
            'basic' => [
                'title' => 'Базовые конструкции',
                'items' => [
                    ['title' => 'Дробь', 'tex' => '\\frac{a}{b}', 'display' => 'inline', 'description' => 'Простая дробь'],
                    ['title' => 'Степень', 'tex' => 'x^{n}', 'display' => 'inline', 'description' => 'Верхний индекс / степень'],
                    ['title' => 'Нижний индекс', 'tex' => 'x_{i}', 'display' => 'inline', 'description' => 'Индекс элемента'],
                    ['title' => 'Квадратный корень', 'tex' => '\\sqrt{x}', 'display' => 'inline', 'description' => 'Корень из x'],
                    ['title' => 'Корень n-й степени', 'tex' => '\\sqrt[n]{x}', 'display' => 'inline', 'description' => 'Корень степени n'],
                    ['title' => 'Скобки переменного размера', 'tex' => '\\left( \\frac{a}{b} \\right)', 'display' => 'inline', 'description' => 'Адаптивные круглые скобки'],
                    ['title' => 'Квадратные скобки', 'tex' => '\\left[ x \\right]', 'display' => 'inline', 'description' => 'Адаптивные квадратные скобки'],
                    ['title' => 'Модуль (абсолютная величина)', 'tex' => '\\left| x \\right|', 'display' => 'inline', 'description' => 'Модуль выражения']
                ]
            ],
            'algebra' => [
                'title' => 'Алгебра',
                'items' => [
                    ['title' => 'Квадратное уравнение', 'tex' => 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}', 'display' => 'block', 'description' => 'Корни квадратного уравнения'],
                    ['title' => 'Разность квадратов', 'tex' => 'a^2 - b^2 = (a - b)(a + b)', 'display' => 'block', 'description' => 'Формула сокращенного умножения'],
                    ['title' => 'Квадрат суммы', 'tex' => '(a + b)^2 = a^2 + 2ab + b^2', 'display' => 'block', 'description' => 'Полный квадрат'],
                    ['title' => 'Бином Ньютона', 'tex' => '(a + b)^n = \\sum_{k=0}^{n} \\binom{n}{k} a^{n-k} b^k', 'display' => 'block', 'description' => 'Биномиальное разложение'],
                    ['title' => 'Логарифм по основанию', 'tex' => '\\log_{a}(b) = \\frac{\\ln b}{\\ln a}', 'display' => 'inline', 'description' => 'Переход к новому основанию']
                ]
            ],
            'calculus' => [
                'title' => 'Математический анализ',
                'items' => [
                    ['title' => 'Предел функции', 'tex' => '\\lim_{x \\to 0} \\frac{\\sin x}{x} = 1', 'display' => 'block', 'description' => 'Первый замечательный предел'],
                    ['title' => 'Второй замечательный предел', 'tex' => '\\lim_{n \\to \\infty} \\left( 1 + \\frac{1}{n} \\right)^n = e', 'display' => 'block', 'description' => 'Число Эйлера'],
                    ['title' => 'Определенный интеграл', 'tex' => '\\int_{a}^{b} f(x)\\,dx = F(b) - F(a)', 'display' => 'block', 'description' => 'Формула Ньютона-Лейбница'],
                    ['title' => 'Неопределенный интеграл', 'tex' => '\\int x^n\\,dx = \\frac{x^{n+1}}{n+1} + C', 'display' => 'block', 'description' => 'Интеграл степенной функции'],
                    ['title' => 'Определение производной', 'tex' => 'f\'(x) = \\lim_{\\Delta x \\to 0} \\frac{f(x + \\Delta x) - f(x)}{\\Delta x}', 'display' => 'block', 'description' => 'Производная функции'],
                    ['title' => 'Сумма ряда', 'tex' => '\\sum_{n=1}^{\\infty} \\frac{1}{n^2} = \\frac{\\pi^2}{6}', 'display' => 'block', 'description' => 'Базельская задача']
                ]
            ],
            'geometry_trig' => [
                'title' => 'Геометрия и тригонометрия',
                'items' => [
                    ['title' => 'Теорема Пифагора', 'tex' => 'a^2 + b^2 = c^2', 'display' => 'inline', 'description' => 'Соотношение сторон в прямоугольном треугольнике'],
                    ['title' => 'Основное тригонометрическое тождество', 'tex' => '\\sin^2 \\alpha + \\cos^2 \\alpha = 1', 'display' => 'inline', 'description' => 'Единичная окружность'],
                    ['title' => 'Формула Эйлера', 'tex' => 'e^{i\\pi} + 1 = 0', 'display' => 'block', 'description' => 'Тождество Эйлера'],
                    ['title' => 'Площадь круга', 'tex' => 'S = \\pi r^2', 'display' => 'inline', 'description' => 'Площадь через радиус'],
                    ['title' => 'Формула Герона', 'tex' => 'S = \\sqrt{p(p - a)(p - b)(p - c)}', 'display' => 'block', 'description' => 'Площадь треугольника по трем сторонам']
                ]
            ],
            'physics' => [
                'title' => 'Физика',
                'items' => [
                    ['title' => 'Эквивалентность массы и энергии', 'tex' => 'E = mc^2', 'display' => 'inline', 'description' => 'Формула Эйнштейна'],
                    ['title' => 'Второй закон Ньютона', 'tex' => '\\vec{F} = m \\vec{a}', 'display' => 'inline', 'description' => 'Закон динамики'],
                    ['title' => 'Закон всемирного тяготения', 'tex' => 'F = G \\frac{m_1 m_2}{r^2}', 'display' => 'block', 'description' => 'Гравитационное взаимодействие'],
                    ['title' => 'Закон Кулона', 'tex' => 'F = \\frac{1}{4\\pi\\varepsilon_0} \\frac{|q_1 q_2|}{r^2}', 'display' => 'block', 'description' => 'Электростатическое взаимодействие'],
                    ['title' => 'Уравнение Шрёдингера', 'tex' => 'i\\hbar \\frac{\\partial}{\\partial t}\\Psi = \\hat{H}\\Psi', 'display' => 'block', 'description' => 'Квантовое состояние']
                ]
            ],
            'matrices' => [
                'title' => 'Матрицы и векторы',
                'items' => [
                    ['title' => 'Матрица 2x2', 'tex' => '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}', 'display' => 'block', 'description' => 'Круглые скобки'],
                    ['title' => 'Определитель 2x2', 'tex' => '\\begin{vmatrix} a & b \\\\ c & d \\end{vmatrix} = ad - bc', 'display' => 'block', 'description' => 'Детерминант'],
                    ['title' => 'Квадратная матрица 3x3', 'tex' => '\\begin{bmatrix} a_{11} & a_{12} & a_{13} \\\\ a_{21} & a_{22} & a_{23} \\\\ a_{31} & a_{32} & a_{33} \\end{bmatrix}', 'display' => 'block', 'description' => 'Квадратные скобки']
                ]
            ]
        ];

        Response::json([
            'categories' => $presets
        ], 200, 'Каталог формул успешно получен');
    }

    /**
     * GET /api/v1/formulas/symbols
     * Каталог математических символов для клавиатур и панелей инструментов
     */
    public function symbols(array $params, array $body): void
    {
        Auth::requireAuth();

        $symbols = [
            'greek_lowercase' => [
                'title' => 'Греческий алфавит (строчные)',
                'items' => [
                    ['symbol' => 'α', 'tex' => '\\alpha', 'name' => 'alpha'],
                    ['symbol' => 'β', 'tex' => '\\beta', 'name' => 'beta'],
                    ['symbol' => 'γ', 'tex' => '\\gamma', 'name' => 'gamma'],
                    ['symbol' => 'δ', 'tex' => '\\delta', 'name' => 'delta'],
                    ['symbol' => 'ε', 'tex' => '\\varepsilon', 'name' => 'epsilon'],
                    ['symbol' => 'θ', 'tex' => '\\theta', 'name' => 'theta'],
                    ['symbol' => 'λ', 'tex' => '\\lambda', 'name' => 'lambda'],
                    ['symbol' => 'μ', 'tex' => '\\mu', 'name' => 'mu'],
                    ['symbol' => 'π', 'tex' => '\\pi', 'name' => 'pi'],
                    ['symbol' => 'ρ', 'tex' => '\\rho', 'name' => 'rho'],
                    ['symbol' => 'σ', 'tex' => '\\sigma', 'name' => 'sigma'],
                    ['symbol' => 'τ', 'tex' => '\\tau', 'name' => 'tau'],
                    ['symbol' => 'φ', 'tex' => '\\varphi', 'name' => 'phi'],
                    ['symbol' => 'ω', 'tex' => '\\omega', 'name' => 'omega']
                ]
            ],
            'greek_uppercase' => [
                'title' => 'Греческий алфавит (прописные)',
                'items' => [
                    ['symbol' => 'Γ', 'tex' => '\\Gamma', 'name' => 'Gamma'],
                    ['symbol' => 'Δ', 'tex' => '\\Delta', 'name' => 'Delta'],
                    ['symbol' => 'Θ', 'tex' => '\\Theta', 'name' => 'Theta'],
                    ['symbol' => 'Λ', 'tex' => '\\Lambda', 'name' => 'Lambda'],
                    ['symbol' => 'Π', 'tex' => '\\Pi', 'name' => 'Pi'],
                    ['symbol' => 'Σ', 'tex' => '\\Sigma', 'name' => 'Sigma'],
                    ['symbol' => 'Φ', 'tex' => '\\Phi', 'name' => 'Phi'],
                    ['symbol' => 'Ψ', 'tex' => '\\Psi', 'name' => 'Psi'],
                    ['symbol' => 'Ω', 'tex' => '\\Omega', 'name' => 'Omega']
                ]
            ],
            'operators' => [
                'title' => 'Операторы и знаки',
                'items' => [
                    ['symbol' => '±', 'tex' => '\\pm', 'name' => 'плюс-минус'],
                    ['symbol' => '∓', 'tex' => '\\mp', 'name' => 'минус-плюс'],
                    ['symbol' => '×', 'tex' => '\\times', 'name' => 'умножение (крестик)'],
                    ['symbol' => '·', 'tex' => '\\cdot', 'name' => 'умножение (точка)'],
                    ['symbol' => '÷', 'tex' => '\\div', 'name' => 'деление'],
                    ['symbol' => '∗', 'tex' => '\\ast', 'name' => 'звездочка'],
                    ['symbol' => '∘', 'tex' => '\\circ', 'name' => 'композиция']
                ]
            ],
            'relations' => [
                'title' => 'Отношения и сравнения',
                'items' => [
                    ['symbol' => '≤', 'tex' => '\\le', 'name' => 'меньше или равно'],
                    ['symbol' => '≥', 'tex' => '\\ge', 'name' => 'больше или равно'],
                    ['symbol' => '≠', 'tex' => '\\ne', 'name' => 'не равно'],
                    ['symbol' => '≈', 'tex' => '\\approx', 'name' => 'приблизительно'],
                    ['symbol' => '≡', 'tex' => '\\equiv', 'name' => 'тождественно равно'],
                    ['symbol' => '∝', 'tex' => '\\propto', 'name' => 'пропорционально'],
                    ['symbol' => '≪', 'tex' => '\\ll', 'name' => 'намного меньше'],
                    ['symbol' => '≫', 'tex' => '\\gg', 'name' => 'намного больше']
                ]
            ],
            'arrows' => [
                'title' => 'Стрелки',
                'items' => [
                    ['symbol' => '→', 'tex' => '\\to', 'name' => 'стрелка вправо / стремится'],
                    ['symbol' => '←', 'tex' => '\\leftarrow', 'name' => 'стрелка влево'],
                    ['symbol' => '↔', 'tex' => '\\leftrightarrow', 'name' => 'двусторонняя стрелка'],
                    ['symbol' => '⇒', 'tex' => '\\Rightarrow', 'name' => 'следовательно'],
                    ['symbol' => '⇐', 'tex' => '\\Leftarrow', 'name' => 'следует из'],
                    ['symbol' => '⇔', 'tex' => '\\iff', 'name' => 'тогда и только тогда']
                ]
            ],
            'calculus_and_sets' => [
                'title' => 'Матанализ и множества',
                'items' => [
                    ['symbol' => '∫', 'tex' => '\\int', 'name' => 'интеграл'],
                    ['symbol' => '∬', 'tex' => '\\iint', 'name' => 'двойной интеграл'],
                    ['symbol' => '∮', 'tex' => '\\oint', 'name' => 'контурный интеграл'],
                    ['symbol' => '∑', 'tex' => '\\sum', 'name' => 'знак суммы'],
                    ['symbol' => '∏', 'tex' => '\\prod', 'name' => 'произведение'],
                    ['symbol' => '∂', 'tex' => '\\partial', 'name' => 'частная производная'],
                    ['symbol' => '∇', 'tex' => '\\nabla', 'name' => 'набла / градиент'],
                    ['symbol' => '∞', 'tex' => '\\infty', 'name' => 'бесконечность'],
                    ['symbol' => '∈', 'tex' => '\\in', 'name' => 'принадлежит'],
                    ['symbol' => '∉', 'tex' => '\\notin', 'name' => 'не принадлежит'],
                    ['symbol' => '⊂', 'tex' => '\\subset', 'name' => 'подмножество'],
                    ['symbol' => '∪', 'tex' => '\\cup', 'name' => 'объединение'],
                    ['symbol' => '∩', 'tex' => '\\cap', 'name' => 'пересечение'],
                    ['symbol' => '∅', 'tex' => '\\emptyset', 'name' => 'пустое множество'],
                    ['symbol' => 'ℝ', 'tex' => '\\mathbb{R}', 'name' => 'вещественные числа'],
                    ['symbol' => 'ℕ', 'tex' => '\\mathbb{N}', 'name' => 'натуральные числа'],
                    ['symbol' => 'ℤ', 'tex' => '\\mathbb{Z}', 'name' => 'целые числа']
                ]
            ]
        ];

        Response::json([
            'symbols' => $symbols
        ], 200, 'Каталог математических символов успешно получен');
    }

    /**
     * POST /api/v1/formulas/convert-markdown
     * Автоматическая конвертация $...$ (инлайн) и $$...$$ (блок) в стандартную HTML-разметку формул NPBlog
     */
    public function convertMarkdown(array $params, array $body): void
    {
        Auth::requireAuth();

        $content = (string)($body['content'] ?? '');
        if ($content === '') {
            Response::json(['content' => '', 'count' => 0], 200);
            return;
        }

        // Предохраняем существующие формулы и pre/code блоки
        $sheltered = [];
        $content = preg_replace_callback('/(<pre[^>]*>[\s\S]*?<\/pre>|<code[^>]*>[\s\S]*?<\/code>|<(?:span|div)[^>]*\b(?:npblog-formula|npblog-formula-block)\b[^>]*>[\s\S]*?<\/(?:span|div)>)/i', function ($m) use (&$sheltered) {
            $idx = count($sheltered);
            $sheltered[] = $m[0];
            return "___SHELTER_PLACEHOLDER_{$idx}___";
        }, $content);

        $count = 0;

        // 1. Блочные формулы: $$...$$
        $content = preg_replace_callback('/\$\$([\s\S]+?)\$\$/', function ($m) use (&$count) {
            $tex = trim($m[1]);
            if ($tex === '') return $m[0];
            $renderResult = self::renderKatex($tex, true, false);
            $escapedTex = htmlspecialchars($tex, ENT_QUOTES, 'UTF-8');
            $html = '<div class="npblog-formula-block" data-formula="' . $escapedTex . '" data-display="block" data-size="normal" contenteditable="false" title="Формула (Двойной клик для редактирования)">' . $renderResult['html'] . '</div>';
            $count++;
            return "\n" . $html . "\n";
        }, $content);

        // 2. Инлайн формулы: $...$ (исключая экранированные \$ и пустые)
        $content = preg_replace_callback('/(?<!\\\\)\$([^\$\n]+?)(?<!\\\\)\$/', function ($m) use (&$count) {
            $tex = trim($m[1]);
            if ($tex === '') return $m[0];
            $renderResult = self::renderKatex($tex, false, false);
            $escapedTex = htmlspecialchars($tex, ENT_QUOTES, 'UTF-8');
            $html = '<span class="npblog-formula" data-formula="' . $escapedTex . '" data-display="inline" data-size="normal" contenteditable="false" title="Формула (Двойной клик для редактирования)">' . $renderResult['html'] . '</span>';
            $count++;
            return $html;
        }, $content);

        // Восстанавливаем укрытые блоки
        foreach ($sheltered as $idx => $orig) {
            $content = str_replace("___SHELTER_PLACEHOLDER_{$idx}___", $orig, $content);
        }

        Response::json([
            'content' => $content,
            'formulas_converted' => $count
        ], 200, "Преобразовано формул: $count");
    }
}
