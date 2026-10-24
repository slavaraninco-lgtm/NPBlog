<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно визуального конструктора формул
 * ==============================================================================
 * Обеспечивает полноценное интерактивное визуальное составление математических формул:
 * - Лента категорий структур: Дроби, Индексы, Радикалы, Интегралы, Крупные операторы,
 *   Скобки, Функции, Диакритика, Пределы, Символы, Матрицы, Греческий алфавит, Шаблоны.
 * - Интерактивное визуальное полотно (MathLive <math-field>) с заполнителями □,
 *   навигацией по Tab/стрелкам и вводом прямо с клавиатуры.
 * - Вспомогательная панель быстрых действий: Undo, Redo, Backspace, Очистить, Обернуть.
 * - Опциональный скрытый режим прямого редактирования LaTeX кода (для опытных пользователей).
 * - Настройка отображения (строчная внутри текста / отдельным блоком по центру, размер).
 * ==============================================================================
 */

$formulaCategories = [
    'fractions' => [
        'title' => 'Дроби',
        'icon' => '➗',
        'items' => [
            ['title' => 'Простая вертикальная дробь', 'preview' => '\frac{\Box}{\Box}', 'snippet' => '\frac{#@}{#?}'],
            ['title' => 'Малая строчная дробь', 'preview' => '\tfrac{\Box}{\Box}', 'snippet' => '\tfrac{#@}{#?}'],
            ['title' => 'Первая производная', 'preview' => '\frac{dy}{dx}', 'snippet' => '\frac{d#@}{d#?}'],
            ['title' => 'Вторая производная', 'preview' => '\frac{d^2 y}{dx^2}', 'snippet' => '\frac{d^2 #@}{d#?^2}'],
            ['title' => 'Частная производная', 'preview' => '\frac{\partial y}{\partial x}', 'snippet' => '\frac{\partial #@}{\partial #?}'],
            ['title' => 'Вторая частная производная', 'preview' => '\frac{\partial^2 y}{\partial x^2}', 'snippet' => '\frac{\partial^2 #@}{\partial #?^2}'],
            ['title' => 'Отношение приращений', 'preview' => '\frac{\Delta y}{\Delta x}', 'snippet' => '\frac{\Delta #@}{\Delta #?}'],
            ['title' => 'Косая дробь', 'preview' => '{a}/{b}', 'snippet' => '{#@}/{#?}'],
            ['title' => 'Биномиальный коэффициент', 'preview' => '\binom{n}{k}', 'snippet' => '\binom{#@}{#?}']
        ]
    ],
    'scripts' => [
        'title' => 'Индексы',
        'icon' => 'xⁿ',
        'items' => [
            ['title' => 'Верхний индекс (степень)', 'preview' => '\Box^{\Box}', 'snippet' => '{#@}^{#?}'],
            ['title' => 'Нижний индекс', 'preview' => '\Box_{\Box}', 'snippet' => '{#@}_{#?}'],
            ['title' => 'Верхний и нижний индекс', 'preview' => '\Box_{\Box}^{\Box}', 'snippet' => '{#@}_{#?}^{#?}'],
            ['title' => 'Левые верхний и нижний индексы', 'preview' => '{}_{\Box}^{\Box}\Box', 'snippet' => '{}_{#?}^{#?}{#@}'],
            ['title' => 'Экспонента со степенью', 'preview' => 'e^{\Box}', 'snippet' => 'e^{#@}'],
            ['title' => 'Двойной верхний индекс', 'preview' => 'x^{\Box^{\Box}}', 'snippet' => '{#@}^{{#?}^{#?}}'],
            ['title' => 'Возведение в квадрат', 'preview' => '\Box^2', 'snippet' => '{#@}^2'],
            ['title' => 'Возведение в куб', 'preview' => '\Box^3', 'snippet' => '{#@}^3']
        ]
    ],
    'radicals' => [
        'title' => 'Радикалы',
        'icon' => '√x',
        'items' => [
            ['title' => 'Квадратный корень', 'preview' => '\sqrt{\Box}', 'snippet' => '\sqrt{#@}'],
            ['title' => 'Корень n-й степени', 'preview' => '\sqrt[n]{\Box}', 'snippet' => '\sqrt[#?]{#@}'],
            ['title' => 'Кубический корень', 'preview' => '\sqrt[3]{\Box}', 'snippet' => '\sqrt[3]{#@}'],
            ['title' => 'Корень суммы квадратов', 'preview' => '\sqrt{a^2 + b^2}', 'snippet' => '\sqrt{{#@}^2 + {#?}^2}'],
            ['title' => 'Корень из дроби', 'preview' => '\sqrt{\frac{\Box}{\Box}}', 'snippet' => '\sqrt{\frac{#@}{#?}}']
        ]
    ],
    'integrals' => [
        'title' => 'Интегралы',
        'icon' => '∫',
        'items' => [
            ['title' => 'Неопределенный интеграл', 'preview' => '\int \Box \, d\Box', 'snippet' => '\int #@ \, d#?'],
            ['title' => 'Определенный интеграл', 'preview' => '\int_{\Box}^{\Box} \Box \, d\Box', 'snippet' => '\int_{#?}^{#?} #@ \, d#?'],
            ['title' => 'Интеграл от 0 до бесконечности', 'preview' => '\int_{0}^{\infty} \Box \, d\Box', 'snippet' => '\int_{0}^{\infty} #@ \, d#?'],
            ['title' => 'Интеграл от -∞ до +∞', 'preview' => '\int_{-\infty}^{\infty} \Box \, d\Box', 'snippet' => '\int_{-\infty}^{\infty} #@ \, d#?'],
            ['title' => 'Двойной интеграл', 'preview' => '\iint_{D} \Box \, dx \, dy', 'snippet' => '\iint_{#?} #@ \, d#?'],
            ['title' => 'Тройной интеграл', 'preview' => '\iiint_{V} \Box \, dV', 'snippet' => '\iiint_{#?} #@ \, d#?'],
            ['title' => 'Контурный интеграл', 'preview' => '\oint_{C} \Box \, dz', 'snippet' => '\oint_{#?} #@ \, d#?']
        ]
    ],
    'large_ops' => [
        'title' => 'Операторы',
        'icon' => '∑',
        'items' => [
            ['title' => 'Сумма с пределами', 'preview' => '\sum_{\Box}^{\Box} \Box', 'snippet' => '\sum_{#?}^{#?} #@'],
            ['title' => 'Бесконечная сумма', 'preview' => '\sum_{n=0}^{\infty} \Box', 'snippet' => '\sum_{n=0}^{\infty} #@'],
            ['title' => 'Сумма с боковым индексом', 'preview' => '\sum\nolimits_{i=1}^{n}', 'snippet' => '\sum\nolimits_{#?}^{#?} #@'],
            ['title' => 'Произведение с пределами', 'preview' => '\prod_{\Box}^{\Box} \Box', 'snippet' => '\prod_{#?}^{#?} #@'],
            ['title' => 'Бесконечное произведение', 'preview' => '\prod_{k=1}^{\infty} \Box', 'snippet' => '\prod_{k=1}^{\infty} #@'],
            ['title' => 'Сопроизведение', 'preview' => '\coprod_{\Box}^{\Box} \Box', 'snippet' => '\coprod_{#?}^{#?} #@'],
            ['title' => 'Объединение множеств', 'preview' => '\bigcup_{\Box}^{\Box} \Box', 'snippet' => '\bigcup_{#?}^{#?} #@'],
            ['title' => 'Пересечение множеств', 'preview' => '\bigcap_{\Box}^{\Box} \Box', 'snippet' => '\bigcap_{#?}^{#?} #@']
        ]
    ],
    'brackets' => [
        'title' => 'Скобки',
        'icon' => '(x)',
        'items' => [
            ['title' => 'Круглые скобки', 'preview' => '\left( \Box \right)', 'snippet' => '\left( #@ \right)'],
            ['title' => 'Квадратные скобки', 'preview' => '\left[ \Box \right]', 'snippet' => '\left[ #@ \right]'],
            ['title' => 'Фигурные скобки', 'preview' => '\left\{ \Box \right\}', 'snippet' => '\left\{ #@ \right\}'],
            ['title' => 'Модуль (одинарные полосы)', 'preview' => '\left| \Box \right|', 'snippet' => '\left| #@ \right|'],
            ['title' => 'Норма (двойные полосы)', 'preview' => '\left\| \Box \right\|', 'snippet' => '\left\| #@ \right\|'],
            ['title' => 'Угловые скобки', 'preview' => '\left\langle \Box \right\rangle', 'snippet' => '\left\langle #@ \right\rangle'],
            ['title' => 'Фигурная скобка сверху', 'preview' => '\overbrace{\Box}^{\Box}', 'snippet' => '\overbrace{#@}^{#?}'],
            ['title' => 'Фигурная скобка снизу', 'preview' => '\underbrace{\Box}_{\Box}', 'snippet' => '\underbrace{#@}_{#?}'],
            ['title' => 'Система из 2 уравнений', 'preview' => '\begin{cases} \Box \\ \Box \end{cases}', 'snippet' => '\begin{cases} #@ \\ #? \end{cases}'],
            ['title' => 'Система из 3 уравнений', 'preview' => '\begin{cases} \Box \\ \Box \\ \Box \end{cases}', 'snippet' => '\begin{cases} #@ \\ #? \\ #? \end{cases}'],
            ['title' => 'Кусочная функция', 'preview' => '\begin{cases} \Box, & \Box \\ \Box, & \Box \end{cases}', 'snippet' => '\begin{cases} #@, & #? \\ #?, & #? \end{cases}']
        ]
    ],
    'functions' => [
        'title' => 'Функции',
        'icon' => 'f(x)',
        'items' => [
            ['title' => 'Синус', 'preview' => '\sin(\Box)', 'snippet' => '\sin\left(#@\right)'],
            ['title' => 'Косинус', 'preview' => '\cos(\Box)', 'snippet' => '\cos\left(#@\right)'],
            ['title' => 'Тангенс', 'preview' => '\tan(\Box)', 'snippet' => '\tan\left(#@\right)'],
            ['title' => 'Котангенс', 'preview' => '\cot(\Box)', 'snippet' => '\cot\left(#@\right)'],
            ['title' => 'Арксинус', 'preview' => '\arcsin(\Box)', 'snippet' => '\arcsin\left(#@\right)'],
            ['title' => 'Арккосинус', 'preview' => '\arccos(\Box)', 'snippet' => '\arccos\left(#@\right)'],
            ['title' => 'Арктангенс', 'preview' => '\arctan(\Box)', 'snippet' => '\arctan\left(#@\right)'],
            ['title' => 'Натуральный логарифм', 'preview' => '\ln(\Box)', 'snippet' => '\ln\left(#@\right)'],
            ['title' => 'Десятичный логарифм', 'preview' => '\log(\Box)', 'snippet' => '\log\left(#@\right)'],
            ['title' => 'Логарифм по основанию a', 'preview' => '\log_{\Box}(\Box)', 'snippet' => '\log_{#?}\left(#@\right)'],
            ['title' => 'Экспонента', 'preview' => '\exp(\Box)', 'snippet' => '\exp\left(#@\right)'],
            ['title' => 'Минимум', 'preview' => '\min(\Box)', 'snippet' => '\min\left(#@\right)'],
            ['title' => 'Максимум', 'preview' => '\max(\Box)', 'snippet' => '\max\left(#@\right)']
        ]
    ],
    'accents' => [
        'title' => 'Диакритика',
        'icon' => 'ẋ',
        'items' => [
            ['title' => 'Вектор со стрелкой', 'preview' => '\vec{\Box}', 'snippet' => '\vec{#@}'],
            ['title' => 'Вектор над выражением', 'preview' => '\overrightarrow{\Box}', 'snippet' => '\overrightarrow{#@}'],
            ['title' => 'Штрих (производная)', 'preview' => 'f\'(\Box)', 'snippet' => '{#@}\''],
            ['title' => 'Два штриха', 'preview' => 'f\'\'(\Box)', 'snippet' => '{#@}\'\''],
            ['title' => 'Точка (производная по времени)', 'preview' => '\dot{\Box}', 'snippet' => '\dot{#@}'],
            ['title' => 'Две точки', 'preview' => '\ddot{\Box}', 'snippet' => '\ddot{#@}'],
            ['title' => 'Черта сверху', 'preview' => '\bar{\Box}', 'snippet' => '\bar{#@}'],
            ['title' => 'Длинная черта', 'preview' => '\overline{\Box}', 'snippet' => '\overline{#@}'],
            ['title' => 'Крышка', 'preview' => '\hat{\Box}', 'snippet' => '\hat{#@}'],
            ['title' => 'Тильда', 'preview' => '\tilde{\Box}', 'snippet' => '\tilde{#@}']
        ]
    ],
    'limits' => [
        'title' => 'Пределы',
        'icon' => 'lim',
        'items' => [
            ['title' => 'Предел x → 0', 'preview' => '\lim_{x \to 0} \Box', 'snippet' => '\lim_{x \to 0} #@'],
            ['title' => 'Предел x → ∞', 'preview' => '\lim_{x \to \infty} \Box', 'snippet' => '\lim_{x \to \infty} #@'],
            ['title' => 'Предел с произвольным стремлением', 'preview' => '\lim_{\Box \to \Box} \Box', 'snippet' => '\lim_{#? \to #?} #@'],
            ['title' => 'Односторонний предел справа', 'preview' => '\lim_{x \to 0^+} \Box', 'snippet' => '\lim_{x \to {#?}^+} #@'],
            ['title' => 'Односторонний предел слева', 'preview' => '\lim_{x \to 0^-} \Box', 'snippet' => '\lim_{x \to {#?}^-} #@'],
            ['title' => 'Минимум по множеству', 'preview' => '\min_{\Box} \Box', 'snippet' => '\min_{#?} #@'],
            ['title' => 'Максимум по множеству', 'preview' => '\max_{\Box} \Box', 'snippet' => '\max_{#?} #@'],
            ['title' => 'Супремум', 'preview' => '\sup_{\Box} \Box', 'snippet' => '\sup_{#?} #@'],
            ['title' => 'Инфимум', 'preview' => '\inf_{\Box} \Box', 'snippet' => '\inf_{#?} #@']
        ]
    ],
    'operators' => [
        'title' => 'Символы',
        'icon' => '±≠',
        'items' => [
            ['title' => 'Плюс-минус', 'preview' => '\pm', 'snippet' => '\pm '],
            ['title' => 'Минус-плюс', 'preview' => '\mp', 'snippet' => '\mp '],
            ['title' => 'Умножение (крестик)', 'preview' => '\times', 'snippet' => '\times '],
            ['title' => 'Умножение (точка)', 'preview' => '\cdot', 'snippet' => '\cdot '],
            ['title' => 'Деление', 'preview' => '\div', 'snippet' => '\div '],
            ['title' => 'Не равно', 'preview' => '\neq', 'snippet' => '\neq '],
            ['title' => 'Приблизительно равно', 'preview' => '\approx', 'snippet' => '\approx '],
            ['title' => 'Тождественно равно', 'preview' => '\equiv', 'snippet' => '\equiv '],
            ['title' => 'Меньше либо равно', 'preview' => '\le', 'snippet' => '\le '],
            ['title' => 'Больше либо равно', 'preview' => '\ge', 'snippet' => '\ge '],
            ['title' => 'Много меньше', 'preview' => '\ll', 'snippet' => '\ll '],
            ['title' => 'Много больше', 'preview' => '\gg', 'snippet' => '\gg '],
            ['title' => 'Пропорционально', 'preview' => '\propto', 'snippet' => '\propto '],
            ['title' => 'Бесконечность', 'preview' => '\infty', 'snippet' => '\infty '],
            ['title' => 'Частная производная', 'preview' => '\partial', 'snippet' => '\partial '],
            ['title' => 'Набла (градиент)', 'preview' => '\nabla', 'snippet' => '\nabla '],
            ['title' => 'Принадлежит множеству', 'preview' => '\in', 'snippet' => '\in '],
            ['title' => 'Не принадлежит множеству', 'preview' => '\notin', 'snippet' => '\notin '],
            ['title' => 'Подмножество', 'preview' => '\subset', 'snippet' => '\subset '],
            ['title' => 'Объединение', 'preview' => '\cup', 'snippet' => '\cup '],
            ['title' => 'Пересечение', 'preview' => '\cap', 'snippet' => '\cap '],
            ['title' => 'Пустое множество', 'preview' => '\emptyset', 'snippet' => '\emptyset '],
            ['title' => 'Для любого (квантор всеобщности)', 'preview' => '\forall', 'snippet' => '\forall '],
            ['title' => 'Существует (квантор существования)', 'preview' => '\exists', 'snippet' => '\exists '],
            ['title' => 'Стрелка вправо', 'preview' => '\to', 'snippet' => '\to '],
            ['title' => 'Следование', 'preview' => '\implies', 'snippet' => '\implies '],
            ['title' => 'Эквивалентность', 'preview' => '\iff', 'snippet' => '\iff '],
            ['title' => 'Градус', 'preview' => '^\circ', 'snippet' => '^{\circ}'],
            ['title' => 'Угол', 'preview' => '\angle', 'snippet' => '\angle '],
            ['title' => 'Перпендикуляр', 'preview' => '\perp', 'snippet' => '\perp '],
            ['title' => 'Параллельность', 'preview' => '\parallel', 'snippet' => '\parallel ']
        ]
    ],
    'matrices' => [
        'title' => 'Матрицы',
        'icon' => '[…]',
        'items' => [
            ['title' => 'Матрица 2x2 (круглые скобки)', 'preview' => '\begin{pmatrix} \Box & \Box \\ \Box & \Box \end{pmatrix}', 'snippet' => '\begin{pmatrix} #@ & #? \\ #? & #? \end{pmatrix}'],
            ['title' => 'Матрица 2x2 (квадратные скобки)', 'preview' => '\begin{bmatrix} \Box & \Box \\ \Box & \Box \end{bmatrix}', 'snippet' => '\begin{bmatrix} #@ & #? \\ #? & #? \end{bmatrix}'],
            ['title' => 'Определитель 2x2', 'preview' => '\begin{vmatrix} \Box & \Box \\ \Box & \Box \end{vmatrix}', 'snippet' => '\begin{vmatrix} #@ & #? \\ #? & #? \end{vmatrix}'],
            ['title' => 'Матрица 3x3 (круглые скобки)', 'preview' => '\begin{pmatrix} \Box & \Box & \Box \\ \Box & \Box & \Box \\ \Box & \Box & \Box \end{pmatrix}', 'snippet' => '\begin{pmatrix} #@ & #? & #? \\ #? & #? & #? \\ #? & #? & #? \end{pmatrix}'],
            ['title' => 'Матрица 3x3 (квадратные скобки)', 'preview' => '\begin{bmatrix} \Box & \Box & \Box \\ \Box & \Box & \Box \\ \Box & \Box & \Box \end{bmatrix}', 'snippet' => '\begin{bmatrix} #@ & #? & #? \\ #? & #? & #? \\ #? & #? & #? \end{bmatrix}'],
            ['title' => 'Определитель 3x3', 'preview' => '\begin{vmatrix} \Box & \Box & \Box \\ \Box & \Box & \Box \\ \Box & \Box & \Box \end{vmatrix}', 'snippet' => '\begin{vmatrix} #@ & #? & #? \\ #? & #? & #? \\ #? & #? & #? \end{vmatrix}'],
            ['title' => 'Вектор-столбец 2x1', 'preview' => '\begin{pmatrix} \Box \\ \Box \end{pmatrix}', 'snippet' => '\begin{pmatrix} #@ \\ #? \end{pmatrix}'],
            ['title' => 'Вектор-столбец 3x1', 'preview' => '\begin{pmatrix} \Box \\ \Box \\ \Box \end{pmatrix}', 'snippet' => '\begin{pmatrix} #@ \\ #? \\ #? \end{pmatrix}'],
            ['title' => 'Вектор-строка 1x3', 'preview' => '\begin{pmatrix} \Box & \Box & \Box \end{pmatrix}', 'snippet' => '\begin{pmatrix} #@ & #? & #? \end{pmatrix}']
        ]
    ],
    'greek' => [
        'title' => 'Греческие',
        'icon' => 'αβ',
        'items' => [
            ['title' => 'Альфа (α)', 'preview' => '\alpha', 'snippet' => '\alpha '],
            ['title' => 'Бета (β)', 'preview' => '\beta', 'snippet' => '\beta '],
            ['title' => 'Гамма (γ)', 'preview' => '\gamma', 'snippet' => '\gamma '],
            ['title' => 'Дельта (δ)', 'preview' => '\delta', 'snippet' => '\delta '],
            ['title' => 'Эпсилон (ε)', 'preview' => '\epsilon', 'snippet' => '\epsilon '],
            ['title' => 'Дзета (ζ)', 'preview' => '\zeta', 'snippet' => '\zeta '],
            ['title' => 'Эта (η)', 'preview' => '\eta', 'snippet' => '\eta '],
            ['title' => 'Тета (θ)', 'preview' => '\theta', 'snippet' => '\theta '],
            ['title' => 'Лямбда (λ)', 'preview' => '\lambda', 'snippet' => '\lambda '],
            ['title' => 'Мю (μ)', 'preview' => '\mu', 'snippet' => '\mu '],
            ['title' => 'Пи (π)', 'preview' => '\pi', 'snippet' => '\pi '],
            ['title' => 'Ро (ρ)', 'preview' => '\rho', 'snippet' => '\rho '],
            ['title' => 'Сигма (σ)', 'preview' => '\sigma', 'snippet' => '\sigma '],
            ['title' => 'Тау (τ)', 'preview' => '\tau', 'snippet' => '\tau '],
            ['title' => 'Фи (φ)', 'preview' => '\phi', 'snippet' => '\phi '],
            ['title' => 'Пси (ψ)', 'preview' => '\psi', 'snippet' => '\psi '],
            ['title' => 'Омега (ω)', 'preview' => '\omega', 'snippet' => '\omega '],
            ['title' => 'Гамма прописная (Γ)', 'preview' => '\Gamma', 'snippet' => '\Gamma '],
            ['title' => 'Дельта прописная (Δ)', 'preview' => '\Delta', 'snippet' => '\Delta '],
            ['title' => 'Тета прописная (Θ)', 'preview' => '\Theta', 'snippet' => '\Theta '],
            ['title' => 'Лямбда прописная (Λ)', 'preview' => '\Lambda', 'snippet' => '\Lambda '],
            ['title' => 'Пи прописная (Π)', 'preview' => '\Pi', 'snippet' => '\Pi '],
            ['title' => 'Сигма прописная (Σ)', 'preview' => '\Sigma', 'snippet' => '\Sigma '],
            ['title' => 'Фи прописная (Φ)', 'preview' => '\Phi', 'snippet' => '\Phi '],
            ['title' => 'Пси прописная (Ψ)', 'preview' => '\Psi', 'snippet' => '\Psi '],
            ['title' => 'Омега прописная (Ω)', 'preview' => '\Omega', 'snippet' => '\Omega ']
        ]
    ],
    'templates' => [
        'title' => 'Шаблоны',
        'icon' => '⭐',
        'items' => [
            ['title' => 'Квадратное уравнение', 'preview' => 'x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}', 'snippet' => 'x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}'],
            ['title' => 'Теорема Пифагора', 'preview' => 'a^2 + b^2 = c^2', 'snippet' => 'a^2 + b^2 = c^2'],
            ['title' => 'Формула Эйлера', 'preview' => 'e^{i\pi} + 1 = 0', 'snippet' => 'e^{i\pi} + 1 = 0'],
            ['title' => 'Бином Ньютона', 'preview' => '(x+a)^n = \sum_{k=0}^n \binom{n}{k} x^k a^{n-k}', 'snippet' => '(x + a)^n = \sum_{k=0}^{n} \binom{n}{k} x^k a^{n-k}'],
            ['title' => 'Площадь круга', 'preview' => 'A = \pi r^2', 'snippet' => 'A = \pi r^2'],
            ['title' => 'Ряд Тейлора', 'preview' => 'f(x) = \sum_{n=0}^{\infty} \frac{f^{(n)}(a)}{n!} (x-a)^n', 'snippet' => 'f(x) = \sum_{n=0}^{\infty} \frac{f^{(n)}(a)}{n!} (x - a)^n'],
            ['title' => 'Второй закон Ньютона', 'preview' => '\vec{F} = m\vec{a}', 'snippet' => '\vec{F} = m \vec{a} = \frac{d\vec{p}}{dt}'],
            ['title' => 'Эквивалентность массы и энергии', 'preview' => 'E = mc^2', 'snippet' => 'E = m c^2'],
            ['title' => 'Нормальное распределение Гаусса', 'preview' => 'f(x) = \frac{1}{\sigma \sqrt{2\pi}} e^{-\frac{(x-\mu)^2}{2\sigma^2}}', 'snippet' => 'f(x) = \frac{1}{\sigma \sqrt{2\pi}} e^{-\frac{(x - \mu)^2}{2\sigma^2}}'],
            ['title' => 'Первый замечательный предел', 'preview' => '\lim_{x \to 0} \frac{\sin x}{x} = 1', 'snippet' => '\lim_{x \to 0} \frac{\sin x}{x} = 1'],
            ['title' => 'Интеграл Гаусса', 'preview' => '\int_{-\infty}^{\infty} e^{-x^2} \, dx = \sqrt{\pi}', 'snippet' => '\int_{-\infty}^{\infty} e^{-x^2} \, dx = \sqrt{\pi}']
        ]
    ]
];

$quickSymbols = [
    '+', '-', '\pm', '\mp', '\times', '\div', '\cdot', '=', '\neq', '\approx', '\equiv',
    '\le', '\ge', '\ll', '\gg', '\infty', '\pi', 'e', 'i', '\partial', '\nabla',
    '\alpha', '\beta', '\gamma', '\delta', '\theta', '\lambda', '\mu', '\sigma', '\omega', '\Delta', '\Omega',
    '\in', '\notin', '\subset', '\cup', '\cap', '\to', '\implies', '\iff', '\forall', '\exists', '^{\circ}'
];
?>
<div id="formulaDialog" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl formula-dialog-window">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <div class="formula-header-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h2.5l3.5 9 4-17H21"/>
                        <path d="M15 11l4 5"/>
                        <path d="M19 11l-4 5"/>
                    </svg>
                </div>
                <div class="modal-titles">
                    <h3 class="modal-title" id="formulaDialogTitle" data-i18n="modals.formula_title">Визуальный конструктор формул</h3>
                    <p class="modal-subtitle" data-i18n="modals.formula_subtitle">Интерактивное визуальное составление уравнений и формул</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeFormulaDialog()" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body formula-modal-body">
            
            <!-- Панель быстрых символов -->
            <div class="formula-quick-symbols-wrap">
                <div class="formula-section-label" data-i18n="modals.formula_quick_symbols">Быстрые символы:</div>
                <div class="formula-quick-symbols-bar" id="formulaQuickSymbolsBar">
                    <?php foreach ($quickSymbols as $sym): ?>
                        <button type="button" class="formula-quick-sym-btn" data-latex="<?= htmlspecialchars($sym) ?>" onclick="insertQuickSymbol(this.getAttribute('data-latex'))" title="<?= htmlspecialchars($sym) ?>">
                            <span class="katex-raw-render" data-expr="<?= htmlspecialchars($sym) ?>"><?= htmlspecialchars($sym) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Лента категорий структур (Ribbon Tabs) -->
            <div class="formula-ribbon-container">
                <div class="formula-ribbon-tabs" id="formulaRibbonTabs" role="tablist">
                    <?php $first = true; foreach ($formulaCategories as $catKey => $cat): ?>
                        <button type="button" class="formula-ribbon-tab-btn <?= $first ? 'active' : '' ?>" data-tab="<?= $catKey ?>" onclick="switchFormulaTab('<?= $catKey ?>')">
                            <span class="formula-tab-icon"><?= $cat['icon'] ?></span>
                            <span class="formula-tab-title" data-i18n="modals.formula_tab_<?= $catKey ?>"><?= htmlspecialchars($cat['title']) ?></span>
                        </button>
                    <?php $first = false; endforeach; ?>
                </div>

                <!-- Палитры структур для каждой вкладки -->
                <div class="formula-palettes-wrap">
                    <?php $first = true; foreach ($formulaCategories as $catKey => $cat): ?>
                        <div class="formula-palette-panel <?= $first ? 'active' : '' ?>" id="palette-<?= $catKey ?>" data-category="<?= $catKey ?>">
                            <div class="formula-structures-grid <?= $catKey === 'templates' ? 'templates-grid' : '' ?>">
                                <?php foreach ($cat['items'] as $item): ?>
                                    <button type="button" class="formula-structure-btn" 
                                            data-snippet="<?= htmlspecialchars($item['snippet']) ?>"
                                            title="<?= htmlspecialchars($item['title']) ?>"
                                            onclick="insertVisualSnippet(this.getAttribute('data-snippet'))">
                                        <div class="formula-structure-preview katex-raw-render" data-expr="<?= htmlspecialchars($item['preview']) ?>">
                                            <code><?= htmlspecialchars($item['preview']) ?></code>
                                        </div>
                                        <span class="formula-structure-title"><?= htmlspecialchars($item['title']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>

            <!-- ГЛАВНАЯ ВИЗУАЛЬНАЯ ОБЛАСТЬ (WYSIWYG CANVAS) -->
            <div class="formula-visual-section">
                <div class="formula-section-header">
                    <div class="formula-visual-title-wrap">
                        <span class="formula-section-label" data-i18n="modals.formula_visual_label">Полотно формулы (нажимайте и печатайте):</span>
                        <span class="formula-badge-wysiwyg">WYSIWYG</span>
                    </div>

                    <!-- Панель быстрых визуальных действий -->
                    <div class="formula-visual-actions-bar">
                        <div class="formula-btn-group">
                            <button type="button" class="formula-action-btn" onclick="formulaActionUndo()" data-i18n-title="modals.formula_undo_title" title="Отменить последнее действие (Ctrl+Z)">
                                <span data-i18n="modals.formula_undo">↶ Отменить</span>
                            </button>
                            <button type="button" class="formula-action-btn" onclick="formulaActionRedo()" data-i18n-title="modals.formula_redo_title" title="Повторить действие (Ctrl+Y)">
                                <span data-i18n="modals.formula_redo">↷ Повторить</span>
                            </button>
                            <button type="button" class="formula-action-btn" onclick="formulaActionDelete()" data-i18n-title="modals.formula_erase_title" title="Стереть символ слева (Backspace)">
                                <span data-i18n="modals.formula_erase">⌫ Стереть</span>
                            </button>
                            <button type="button" class="formula-action-btn formula-action-btn-danger" onclick="formulaActionClear()" data-i18n-title="modals.formula_clear_title" title="Очистить поле">
                                <span data-i18n="modals.formula_clear">🗑️ Очистить</span>
                            </button>
                        </div>
                        <div class="formula-btn-divider"></div>
                        <div class="formula-btn-group">
                            <button type="button" class="formula-action-btn formula-wrap-btn" onclick="insertVisualSnippet('\\left( #@ \\right)')" data-i18n-title="modals.formula_wrap_brackets" title="Взять в круглые скобки ( )">( ... )</button>
                            <button type="button" class="formula-action-btn formula-wrap-btn" onclick="insertVisualSnippet('\\frac{#@}{#?}')" data-i18n-title="modals.formula_make_fraction" title="Превратить в дробь">a/b</button>
                            <button type="button" class="formula-action-btn formula-wrap-btn" onclick="insertVisualSnippet('\\sqrt{#@}')" data-i18n-title="modals.formula_put_sqrt" title="Поместить под корень">√x</button>
                            <button type="button" class="formula-action-btn formula-wrap-btn" onclick="insertVisualSnippet('{#@}^2')" data-i18n-title="modals.formula_square" title="Возвести в квадрат">x²</button>
                            <button type="button" class="formula-action-btn formula-wrap-btn" onclick="insertVisualSnippet('{#@}_{#?}')" data-i18n-title="modals.formula_add_subscript" title="Добавить нижний индекс">x_i</button>
                        </div>
                        <div class="formula-btn-divider"></div>
                        <div class="formula-btn-group">
                            <button type="button" id="formulaVirtualKeyboardBtn" class="formula-action-btn" onclick="formulaActionToggleKeyboard()" data-i18n-title="modals.formula_keyboard_title" title="Экранная клавиатура">
                                <span data-i18n="modals.formula_keyboard">⌨ Клавиатура</span>
                            </button>
                            <button type="button" id="formulaToggleCodeBtn" class="formula-action-btn" onclick="toggleFormulaCodeSection()" data-i18n-title="modals.formula_latex_toggle_title" title="Показать или скрыть код LaTeX">
                                <span data-i18n="modals.formula_latex_code">{ } Код LaTeX</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Интерактивное визуальное поле MathLive -->
                <div class="formula-visual-field-container">
                    <math-field id="visualMathField" class="formula-visual-field"></math-field>
                </div>

                <div class="formula-status-bar">
                    <div id="formulaSyntaxStatus" class="formula-syntax-badge is-valid" data-i18n="modals.formula_status_ready">Готово к редактированию ✓</div>
                    <div class="formula-quick-tip" data-i18n="modals.formula_quick_tip">💡 Кликайте по квадратикам □ или используйте Tab и стрелки для перемещения. Печатайте знаки с клавиатуры: / для дроби, ^ для степени, _ для индекса.</div>
                </div>
            </div>

            <!-- Сворачиваемый блок исходного кода LaTeX (для опытных пользователей) -->
            <div id="formulaCodeCollapsible" class="formula-code-section" style="display: none;">
                <div class="formula-section-header">
                    <label class="modal-label" for="formulaInput" style="margin: 0;" data-i18n="modals.formula_code_label">Код формулы (LaTeX):</label>
                    <div class="formula-code-helpers">
                        <button type="button" class="formula-helper-btn" onclick="insertVisualSnippet('\\, ')" data-i18n-title="modals.formula_small_space_title" title="Небольшой пробел" data-i18n="modals.formula_small_space">\, пробел</button>
                        <button type="button" class="formula-helper-btn" onclick="insertVisualSnippet('\\quad ')" data-i18n-title="modals.formula_wide_space_title" title="Широкий пробел">\quad</button>
                        <button type="button" class="formula-helper-btn" onclick="insertVisualSnippet('\\text{#@}')" data-i18n-title="modals.formula_text_title" title="Обычный текст">\text{...}</button>
                    </div>
                </div>
                <textarea id="formulaInput" class="modal-textarea formula-code-textarea" rows="2" placeholder="x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}" spellcheck="false" oninput="syncCodeToVisual()"></textarea>
            </div>

            <!-- Настройки отображения формулы -->
            <div class="formula-options-grid">
                <div class="formula-option-group">
                    <label class="modal-label" data-i18n="modals.formula_type_label">Тип формулы:</label>
                    <div class="formula-radio-toggle">
                        <label class="formula-radio-label">
                            <input type="radio" name="formulaDisplayType" value="inline" id="formulaTypeInline" checked>
                            <span class="formula-radio-custom"></span>
                            <span data-i18n="modals.formula_type_inline">Внутри строки (строчная)</span>
                        </label>
                        <label class="formula-radio-label">
                            <input type="radio" name="formulaDisplayType" value="block" id="formulaTypeBlock">
                            <span class="formula-radio-custom"></span>
                            <span data-i18n="modals.formula_type_block">Отдельным блоком по центру</span>
                        </label>
                    </div>
                </div>

                <div class="formula-option-group">
                    <label class="modal-label" for="formulaSizeSelect" data-i18n="modals.formula_size_label">Размер:</label>
                    <select id="formulaSizeSelect" class="modal-select">
                        <option value="normal" data-i18n="modals.formula_size_normal" selected>Обычный (100%)</option>
                        <option value="large" data-i18n="modals.formula_size_large">Крупный (125%)</option>
                        <option value="huge" data-i18n="modals.formula_size_huge">Очень крупный (150%)</option>
                    </select>
                </div>
            </div>

        </div>

        <!-- Подвал окна -->
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeFormulaDialog()" data-i18n="common.cancel">Отмена</button>
            <button type="button" id="formulaSubmitBtn" class="modal-btn modal-btn-primary" onclick="insertFormulaToEditor()">
                <span>📐</span>
                <span data-i18n="modals.formula_insert_btn">Вставить формулу</span>
            </button>
        </div>
    </div>
</div>
