<!DOCTYPE html>
<html>
<head>
    <title>Редактор</title>
    <meta charset="utf-8">
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

        /* Анимации редактора */
        @keyframes dialogOverlayIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes dialogContentIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.94); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        @keyframes popoverIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes contextMenuIn {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes dialogScaleIn {
            from { opacity: 0; transform: scale(0.94); }
            to { opacity: 1; transform: scale(1); }
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: var(--bg-color);
             color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center; /* центрируем блоки редактора */
        }
        textarea {
            min-height: 200px;
            padding: 10px;
            line-height: 1.6;
            font-size: 14px;
            font-family: Arial, sans-serif;
            white-space: pre-wrap;
            resize: vertical;
        }
        /* Ширина полей под размер контента блога */
        #title,
        #submitButton,
        #content,
        #contentVisual,
        .editor-toolbar-wrap {
            max-width: 920px;
            width: 100%;
            box-sizing: border-box;
        }

        .manage-posts {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 340px;
            max-width: 95vw;
            background-color: var(--bg-color);
            color: var(--text-color);
            border-left: 1px solid #ffffff;
            padding: 0;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -8px 0 32px rgba(0,0,0,0.12);
            z-index: 500;
        }
        [data-theme="dark"] .manage-posts {
            box-shadow: -8px 0 32px rgba(0,0,0,0.35);
        }
        .manage-posts.active {
            transform: translateX(0);
        }
        .manage-posts-header {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 20px 20px 16px;
            background: var(--bg-color);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .manage-posts-header {
            border-bottom-color: rgba(255,255,255,0.08);
        }
        .manage-posts h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        .manage-posts .close-manage {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            color: var(--text-color);
            opacity: 0.7;
            transition: opacity 0.2s ease, background 0.2s ease;
        }
        .manage-posts .close-manage:hover {
            opacity: 1;
            background: rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .manage-posts .close-manage:hover {
            background: rgba(255,255,255,0.08);
        }
        .post-list {
            list-style: none;
            padding: 16px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .post-item {
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #ffffff;
            background: var(--bg-color);
            transition: border-color 0.2s ease, box-shadow 0.25s ease, transform 0.2s ease;
        }
        [data-theme="dark"] .post-item {
            background: rgba(255,255,255,0.03);
        }
        .post-item:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .post-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .post-item-title {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.35;
            margin: 0 0 6px 0;
            color: var(--text-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .post-item-date {
            display: block;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.65;
            margin-bottom: 12px;
        }
        .post-item-actions {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
        }
        .post-item .edit-btn,
        .post-item .additional-btn,
        .post-item .delete-btn {
            position: static;
            transform: none;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid #ffffff;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .post-item .edit-btn {
            background: var(--text-color);
            color: var(--bg-color);
        }
        .post-item .edit-btn:hover {
            opacity: 0.9;
        }
        .post-item .additional-btn {
            background: transparent;
            color: var(--text-color);
            border-color: var(--text-color);
        }
        .post-item .additional-btn:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        .post-item .delete-btn {
            background: transparent;
            color: var(--text-color);
        }
        .post-item .delete-btn:hover {
            background: #b71c1c;
            color: #fff;
            border-color: #b71c1c;
        }
        .manage-posts-empty {
            padding: 24px 16px;
            text-align: center;
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.7;
        }

        /* Улучшенная система управления изображениями в редакторе */
        .blog-image-align-wrap {
            display: block;
            text-align: left;
            margin: 14px 0;
            width: 100%;
            clear: both;
            position: relative;
        }
        .blog-image-wrap {
            position: relative;
            display: inline-block;
            max-width: 100%;
            vertical-align: top;
            transition: box-shadow 0.2s ease;
        }
        .blog-image-wrap:hover {
            box-shadow: 0 0 0 2px var(--primary-color, #4CAF50);
        }
        .blog-image-wrap.selected {
            box-shadow: 0 0 0 3px var(--primary-color, #4CAF50);
        }
        .blog-image-wrap img {
            display: block;
            max-width: 100%;
            height: auto;
            vertical-align: middle;
            border-radius: 8px;
        }
        
        /* Плавающая панель инструментов для изображения */
        .image-toolbar {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 4px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            padding: 6px;
            border-radius: 10px;
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 10;
            pointer-events: none;
        }
        .blog-image-wrap:hover .image-toolbar,
        .blog-image-wrap.selected .image-toolbar {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .image-toolbar-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0;
        }
        .image-toolbar-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }
        .image-toolbar-btn.active {
            background: var(--primary-color, #4CAF50);
            border-color: var(--primary-color, #4CAF50);
        }
        
        /* Панель выравнивания (выпадающая) */
        .image-align-dropdown {
            position: absolute;
            top: 48px;
            right: 8px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 10px;
            padding: 6px;
            display: none;
            flex-direction: column;
            gap: 4px;
            min-width: 180px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            z-index: 20;
        }
        .image-align-dropdown.show {
            display: flex;
        }
        .image-align-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: left;
        }
        .image-align-option:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .image-align-option.active {
            background: var(--primary-color, #4CAF50);
            border-color: var(--primary-color, #4CAF50);
        }
        
        /* Индикатор размера изображения */
        .image-size-indicator {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-family: monospace;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
        }
        .blog-image-wrap:hover .image-size-indicator {
            opacity: 1;
        }
        
        /* Ручки изменения размера */
        .image-resize-handle {
            position: absolute;
            width: 12px;
            height: 12px;
            background: var(--primary-color, #4CAF50);
            border: 2px solid #fff;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s ease;
            cursor: nwse-resize;
            z-index: 11;
        }
        .blog-image-wrap:hover .image-resize-handle,
        .blog-image-wrap.selected .image-resize-handle {
            opacity: 1;
        }
        .image-resize-handle.bottom-right {
            bottom: -6px;
            right: -6px;
        }
        .image-resize-handle.bottom-left {
            bottom: -6px;
            left: -6px;
            cursor: nesw-resize;
        }

        /* Кастомное ПКМ-меню редактора */
        .editor-context-menu {
            display: none;
            position: fixed;
            z-index: 2000;
            min-width: 200px;
            padding: 6px 0;
            background: var(--bg-color);
            border: 1px solid #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
            transform-origin: top left;
        }
        [data-theme="dark"] .editor-context-menu {
            box-shadow: 0 12px 32px rgba(0,0,0,0.4);
        }
        .editor-context-menu.is-open {
            display: block;
            animation: contextMenuIn 0.18s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
        }
        .editor-context-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            border: none;
            background: none;
            color: var(--text-color);
            font-size: 14px;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s ease;
            box-sizing: border-box;
        }
        .editor-context-item:hover {
            background: rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .editor-context-item:hover {
            background: rgba(255,255,255,0.08);
        }
        .editor-context-sep {
            display: block;
            height: 1px;
            margin: 6px 0;
            background: rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .editor-context-sep {
            background: rgba(255,255,255,0.08);
        }
        
        .manage-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            border-radius: 5px;
            border-style: groove;
        }

        .theme-btn {
            position: fixed;
            top: 61px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            border-radius: 5px;
            border-style: groove;
        }
        
        .upd-btn{
            position: fixed;
            top: 102px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            border-radius: 5px;
            border-style: groove
        }

        .ftp-btn{
            position: fixed;
            top: 143px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            border-radius: 5px;
            border-style: groove
        }

        .manage-btn:hover {
            background: #45a049;
        }
        
        .close-manage {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--primary-color2);
        }

        .dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(6px);
            z-index: 1000;
        }
        .dialog[style*="display: block"] {
            animation: dialogOverlayIn 0.22s ease-out forwards;
        }
        .dialog[style*="display: block"] .dialog-content {
            animation: dialogContentIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
        }

        .dialog-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            padding: 28px 28px 24px;
            border-radius: 16px;
            min-width: 320px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #ffffff;
            box-shadow: 0 24px 48px rgba(0,0,0,0.12), 0 8px 24px rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .dialog-content {
            border-color: #ffffff;
            box-shadow: 0 24px 48px rgba(0,0,0,0.4);
        }
        .dialog-content h3 {
            margin: 0 0 20px 0;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .image-size-controls {
            margin: 18px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px 20px;
            align-items: flex-start;
        }
        .image-size-controls label {
            font-size: 14px;
            color: var(--text-color);
        }
        .image-size-controls select {
            margin-left: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ffffff;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 14px;
        }

        .dialog-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .dialog-buttons {
            border-top-color: rgba(255,255,255,0.08);
        }
        .dialog-buttons button {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #ffffff;
            background: var(--bg-color);
            color: var(--text-color);
        }
        .dialog-buttons button:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        .dialog-buttons button:first-child {
            background: var(--text-color);
            color: var(--bg-color);
        }
        .dialog-buttons button:first-child:hover {
            opacity: 0.9;
        }

        .dialog .form-group {
            margin-bottom: 18px;
        }
        .dialog .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-color);
        }
        .dialog .form-control {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-color);
            color: var(--text-color);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .dialog .form-control:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }
        .dialog input[type="number"],
        .dialog input[type="text"]:not(.image-url-input):not(.media-input):not(.code-input) {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-color);
            color: var(--text-color);
            margin-bottom: 4px;
        }
        .dialog input[type="number"]:focus,
        .dialog input[type="text"]:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }

        #customSizeInputs {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        #customSizeInputs input {
            width: 100px;
        }

        .edit-btn {
            /* стили для кнопки Edit заданы в .post-item .edit-btn */
        }

        .code-block {
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin: 15px 0;
        position: relative;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 14px;
        line-height: 1.4;
        max-height: 400px;
        overflow-x: auto;
        white-space: pre;
        color: #000;
    }
    
    [data-theme="dark"] .code-block {
        background: #1e1e1e;
        border-color: #444;
        color: #d4d4d4;
    }

    .code-block::before {
        content: attr(data-language);
        position: absolute;
        top: -12px;
        right: 10px;
        background: #fff;
        padding: 0 5px;
        font-size: 12px;
        color: #666;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    [data-theme="dark"] .code-block::before {
        background: #000;
        color: #ccc;
        border-color: #444;
    }

    /* Стили для сворачиваемого блока (spoiler) */
    .spoiler-block {
        background: var(--bg-color);
        border: 2px solid var(--border-color, #000);
        border-radius: 8px;
        margin: 15px 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .spoiler-title {
        display: block;
        padding: 12px 16px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        user-select: none;
        background: rgba(0, 0, 0, 0.03);
        transition: background 0.2s ease;
        list-style: none;
    }

    .spoiler-title::-webkit-details-marker {
        display: none;
    }

    .spoiler-title::before {
        content: "\25B6";
        display: inline-block;
        margin-right: 8px;
        transition: transform 0.3s ease;
        font-size: 12px;
    }

    .spoiler-block[open] .spoiler-title::before {
        transform: rotate(90deg);
    }

    .spoiler-title:hover {
        background: rgba(0, 0, 0, 0.06);
    }

    [data-theme="dark"] .spoiler-title {
        background: rgba(255, 255, 255, 0.03);
    }

    [data-theme="dark"] .spoiler-title:hover {
        background: rgba(255, 255, 255, 0.06);
    }

    .spoiler-content {
        padding: 16px;
        line-height: 1.6;
        animation: spoilerFadeIn 0.3s ease;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    @keyframes spoilerFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Стили для spoiler в визуальном редакторе */
    #contentVisual .spoiler-block {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }

    /* Стили для маркера (выделение текста) */
    mark {
        padding: 2px 6px;
        background: var(--marker-color, rgba(255, 235, 59, 0.5));
        color: inherit;
        font-weight: inherit;
        position: relative;
        display: inline;
        border-radius: 255px 15px 225px 15px/15px 225px 15px 255px;
    }

    /* Ровное выделение */
    mark[data-marker-style="straight"] {
        border-radius: 3px;
        padding: 3px 6px;
    }

    /* Кривое выделение (рука дрожала) */
    mark[data-marker-style="rough"] {
        border-radius: 255px 15px 225px 15px/15px 225px 15px 255px;
        padding: 3px 8px;
        transform: rotate(-0.5deg);
    }

    /* Зигзагом */
    mark[data-marker-style="zigzag"] {
        border-radius: 15px 255px 15px 225px/225px 15px 255px 15px;
        padding: 3px 7px;
        transform: rotate(0.3deg);
        background: linear-gradient(to right, 
            var(--marker-color) 0%, 
            transparent 0%) 0 0,
            linear-gradient(to right, 
            var(--marker-color) 0%, 
            transparent 0%) 0 100%;
        background-size: 8px 3px;
        background-repeat: repeat-x;
        background-color: var(--marker-color);
    }

    /* Волнистое */
    mark[data-marker-style="wavy"] {
        border-radius: 225px 255px 15px 225px/255px 15px 225px 15px;
        padding: 3px 7px;
        transform: rotate(-0.3deg);
        position: relative;
    }

    mark[data-marker-style="wavy"]::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: inherit;
        border-radius: inherit;
        transform: scaleY(0.95) translateY(1px);
        z-index: -1;
    }

    mark[data-marker-color="yellow"] {
        --marker-color: rgba(255, 235, 59, 0.5);
    }

    mark[data-marker-color="green"] {
        --marker-color: rgba(76, 175, 80, 0.45);
    }

    mark[data-marker-color="blue"] {
        --marker-color: rgba(33, 150, 243, 0.45);
    }

    mark[data-marker-color="orange"] {
        --marker-color: rgba(255, 152, 0, 0.5);
    }

    mark[data-marker-color="pink"] {
        --marker-color: rgba(233, 30, 99, 0.45);
    }

    mark[data-marker-color="purple"] {
        --marker-color: rgba(156, 39, 176, 0.45);
    }

    /* Диалог выбора стиля и цвета маркера */
    .marker-styles {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin: 16px 0;
    }

    .marker-style-btn {
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        background: var(--bg-color);
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .marker-style-btn:hover {
        border-color: #999;
        transform: scale(1.05);
    }

    .marker-style-btn.active {
        border-color: #4caf50;
        background: rgba(76, 175, 80, 0.1);
    }

    .marker-style-preview {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-color);
    }

    .marker-preview-straight {
        background: rgba(255, 235, 59, 0.5);
        padding: 3px 8px;
        border-radius: 3px;
    }

    .marker-preview-rough {
        background: rgba(255, 235, 59, 0.5);
        padding: 3px 10px;
        border-radius: 255px 15px 225px 15px/15px 225px 15px 255px;
        transform: rotate(-0.5deg);
    }

    .marker-preview-zigzag {
        background: rgba(255, 235, 59, 0.5);
        padding: 3px 9px;
        border-radius: 15px 255px 15px 225px/225px 15px 255px 15px;
        transform: rotate(0.3deg);
    }

    .marker-preview-wavy {
        background: rgba(255, 235, 59, 0.5);
        padding: 3px 9px;
        border-radius: 225px 255px 15px 225px/255px 15px 225px 15px;
        transform: rotate(-0.3deg);
    }

    /* Диалог выбора цвета маркера */
    .marker-colors {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin: 20px 0;
    }

    .marker-color-btn {
        width: 48px;
        height: 48px;
        border: 3px solid transparent;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .marker-color-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    .marker-color-btn:active {
        transform: scale(0.95);
    }

    .dialog.code-dialog .dialog-content {
        width: 560px;
        max-width: 90vw;
    }

    .code-input {
        width: 100%;
        box-sizing: border-box;
        min-height: 200px;
        font-family: 'Consolas', 'Monaco', monospace;
        margin: 12px 0;
        padding: 14px;
        border: 1px solid #ffffff;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.5;
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s, border-color 0.2s;
    }
    .code-input:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }

    .language-select {
        width: 100%;
        max-width: 200px;
        padding: 10px 14px;
        margin-bottom: 12px;
        border: 1px solid #ffffff;
        border-radius: 10px;
        font-size: 14px;
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s;
    }
    .font-size-select {
        width: auto;
        padding: 0 5px;
        font-size: 14px;
    }

    .font-size-select:hover {
        background-color: #666;
    }

    .font-family-select {
    width: auto;
    padding: 0 5px;
    font-size: 14px;
    background-color: var(--primary-color);
    color: var(--primary-color2);
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.font-family-select:focus, .font-family-select:hover {
    box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(102, 175, 233, 0.6);
    background-color: #666;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}


#fontFamilyDialog {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(6px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
#fontFamilyDialog[style*="display: block"] {
    display: flex !important;
    animation: dialogOverlayIn 0.22s ease-out forwards;
}
#fontFamilyDialog[style*="display: block"] .dialog-content {
    animation: dialogScaleIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
}
#fontFamilyDialog .dialog-content {
    position: relative;
    top: auto;
    left: auto;
    transform: none;
    margin: auto;
    padding: 28px 28px 24px;
    border-radius: 16px;
    min-width: 320px;
    border: 1px solid #ffffff;
    box-shadow: 0 24px 48px rgba(0,0,0,0.12), 0 8px 24px rgba(0,0,0,0.08);
    background: var(--bg-color);
    color: var(--text-color);
}
[data-theme="dark"] #fontFamilyDialog .dialog-content {
    box-shadow: 0 24px 48px rgba(0,0,0,0.4);
}
#fontFamilyDialog label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--text-color);
}
#fontFamilyDialog input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    font-size: 14px;
    border: 1px solid #ffffff;
    border-radius: 10px;
    outline: none;
    background: var(--bg-color);
    color: var(--text-color);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
#fontFamilyDialog input[type="text"]:focus {
    box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
}
#fontFamilyDialog button {
    margin-top: 14px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid #ffffff;
    border-radius: 10px;
    background: var(--text-color);
    color: var(--bg-color);
    cursor: pointer;
    transition: all 0.2s ease;
}
#fontFamilyDialog button:hover {
    opacity: 0.9;
}

    .font-size-span {
        display: inline-block;
        padding: 2px 5px;
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 3px;
        margin: 2px;
        font-size: inherit;
    }
    .size-input-group {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 5px;
    }

    .size-input-group input {
        width: 100px;
        padding: 8px 10px;
        border: 1px solid #ffffff;
        border-radius: 8px;
        background: var(--bg-color);
        color: var(--text-color);
        font-size: 14px;
    }

    .size-input-group select {
        width: 64px;
        padding: 8px 10px;
        border: 1px solid #ffffff;
        border-radius: 8px;
        background: var(--bg-color);
        color: var(--text-color);
    }

    #customSizeInputs {
        margin-top: 10px;
        display: none;
    }
    .media-select {
        width: 100%;
        padding: 5px;
        margin-bottom: 10px;
    }

    .media-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        margin-bottom: 16px;
        border: 1px solid #ffffff;
        border-radius: 10px;
        font-size: 14px;
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s, border-color 0.2s;
    }
    .media-input:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }

    .media-container {
        position: relative;
        margin: 15px 0;
        width: 100%;
    }

    .media-container iframe {
        border: none;
        max-width: 100%;
    }

    .size-input-group label {
        display: inline-block;
        width: 70px;
    }
    .image-source-toggle {
        margin-bottom: 18px;
        display: flex;
        gap: 24px;
    }

    .image-source-toggle label {
        cursor: pointer;
        font-size: 14px;
        color: var(--text-color);
    }

    .image-url-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        margin-bottom: 16px;
        border: 1px solid #ffffff;
        border-radius: 10px;
        font-size: 14px;
        background: var(--bg-color);
        color: var(--text-color);
        transition: border-color 0.2s ease;
    }
    .image-url-input:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }

    #fileUploadContainer,
    #urlContainer {
        margin-bottom: 16px;
    }
    .color-picker {
        width: 30px;
        height: 30px;
        padding: 0;
        border: 1px solid #ccc;
        cursor: pointer;
    }

    .color-picker::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .color-picker::-webkit-color-swatch {
        border: none;
        border-radius: 3px;
    }
    .format-btn sup, .format-btn sub {
        font-size: 0.7em;
        line-height: 1;
    }

    .format-btn sup {
        vertical-align: super;
    }

    .format-btn sub {
        vertical-align: sub;
    }

    iframe {
        border: 0;
    }

    .content228 {
        background-color: var(--primary-color);
        color: var(--primary-color2);
        border-radius: 5px;
    }

    input {
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s;
    }

    button {
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s;
        border: none;
        border-radius: 5px;
        border-style: groove;
        border-width: 1px;
    }

    button:hover {
        background-color: #666;
    }

    .dropbtn {
  position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
}

.dropdown {
  position: relative;
  display: inline-block;
}

.dropdown-content {
  display: none;
  position: absolute;
  background-color: #f1f1f1;
  min-width: 160px;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  z-index: 1;
}

.dropdown-content a {
  color: black;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
}
.dropdown-content a:hover {background-color: #ddd;}

.dropdown:hover .dropdown-content {display: block;}

.dropdown:hover .dropbtn {background-color: #3e8e41;}

        /* ——— Красивое меню редактора ——— */
        .editor-menu-wrap {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 100;
        }
        .editor-menu-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid #ffffff;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        [data-theme="dark"] .editor-menu-btn {
            border-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .editor-menu-btn:hover {
            background: var(--text-color);
            color: var(--bg-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .editor-menu-btn::after {
            content: '';
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid currentColor;
            margin-left: 2px;
            opacity: 0.8;
            transition: transform 0.2s ease;
        }
        .editor-menu-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 260px;
            background: var(--bg-color);
            border: 1px solid #ffffff;
            border-radius: 12px;
            padding: 8px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08), 0 2px 10px rgba(0,0,0,0.04);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-12px) scale(0.95);
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        visibility 0.3s;
        }
        [data-theme="dark"] .editor-menu-dropdown {
            border-color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        .editor-menu-wrap.is-open .editor-menu-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .editor-menu-wrap.is-closing .editor-menu-dropdown {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-12px) scale(0.95);
        }
        .editor-menu-wrap.is-open .editor-menu-btn::after {
            transform: rotate(180deg);
        }
        .editor-menu-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            text-align: left;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 14px;
            cursor: pointer;
            transition: background 0.15s ease;
            text-decoration: none;
            box-sizing: border-box;
        }
        .editor-menu-item:hover {
            background: rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .editor-menu-item:hover {
            background: rgba(255,255,255,0.08);
        }
        .editor-menu-item a {
            color: inherit;
            text-decoration: none;
            display: block;
        }
        .editor-menu-version {
            padding: 10px 16px;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.6;
            border-top: 1px solid #ffffff;
            margin-top: 4px;
        }
        [data-theme="dark"] .editor-menu-version {
            border-top-color: #ffffff;
        }
        
        /* Глобальные параметры */
        .global-nav-btn:hover {
            background: rgba(0,0,0,0.08) !important;
        }
        .global-nav-btn.active {
            background: rgba(0,0,0,0.1) !important;
            font-weight: 600;
        }
        [data-theme="dark"] .global-nav-btn:hover {
            background: rgba(255,255,255,0.08) !important;
        }
        [data-theme="dark"] .global-nav-btn.active {
            background: rgba(255,255,255,0.1) !important;
        }
        
        /* Анимации модальных окон */
        .modal-overlay {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.show {
            opacity: 1;
        }
        .modal-content {
            transform: scale(0.9) translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .modal-overlay.show .modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        }

        /* ——— Панель форматирования (только строка кнопок прилипает при прокрутке) ——— */
        .editor-toolbar-wrap {
            max-width: 920px;
            width: 100%;
            box-sizing: border-box;
        }
        .formatting-buttons-sticky-wrap {
            position: relative;
            width: 100%;
        }
        .formatting-buttons-sentinel {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 1px;
            pointer-events: none;
            visibility: hidden;
        }
        .formatting-buttons-placeholder {
            display: none;
            width: 100%;
        }
        .formatting-buttons.is-floating {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1001;
            width: 100%;
            max-width: 920px;
            padding: 12px 20px;
            margin: 0;
            box-sizing: border-box;
            background: var(--bg-color);
            border-bottom: 1px solid #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            column-gap: 12px;
            row-gap: 8px;
        }
        [data-theme="dark"] .formatting-buttons.is-floating {
            border-bottom-color: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        }
        .mode-toggle {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
        }
        .mode-toggle .format-btn {
            width: auto;
            min-width: 110px;
            height: 36px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #ffffff;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .mode-toggle .format-btn {
            border-color: #ffffff;
        }
        .mode-toggle .format-btn:hover {
            background: rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .mode-toggle .format-btn:hover {
            background: rgba(255,255,255,0.08);
        }
        .mode-toggle .format-btn.active {
            background: var(--text-color);
            color: var(--bg-color);
            border-color: var(--text-color);
        }
        .formatting-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            column-gap: 12px;
            row-gap: 8px;
            padding: 12px 16px;
            background: var(--bg-color);
            border: 1px solid #ffffff;
            border-radius: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .formatting-buttons {
            border-color: #ffffff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .formatting-buttons .toolbar-group {
            display: flex;
            gap: 2px;
            align-items: center;
        }
        .formatting-buttons .toolbar-divider {
            width: 1px;
            height: 20px;
            min-height: 20px;
            align-self: center;
            flex-shrink: 0;
            background: rgba(0,0,0,0.15);
            border-radius: 1px;
        }
        [data-theme="dark"] .formatting-buttons .toolbar-divider {
            background: rgba(255,255,255,0.2);
        }
        .format-btn {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-color);
            transition: background 0.15s ease, border-color 0.15s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px;
        }
        .format-btn:hover {
            background: rgba(0,0,0,0.06);
            border-color: rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .format-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.1);
        }
        .format-btn.active {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.12);
        }
        [data-theme="dark"] .format-btn.active {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.18);
        }
        .format-btn sup, .format-btn sub { font-size: 0.7em; line-height: 1; }
        .format-btn sup { vertical-align: super; }
        .format-btn sub { vertical-align: sub; }
        #btn-spoiler {
            font-size: 16px;
            line-height: 1;
        }
        .spoiler-icon {
            display: inline-block;
            transform: translateY(-2px);
        }
        #btn-marker {
            line-height: 1;
            padding-bottom: 2px;
        }
        .format-btn.font-size-select,
        .format-btn.font-family-select {
            width: auto;
            min-width: 90px;
            padding: 0 8px;
            font-size: 13px;
            font-weight: normal;
        }
        .color-picker {
            width: 32px;
            height: 32px;
            padding: 2px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            background: transparent;
        }
        .color-picker::-webkit-color-swatch-wrapper { padding: 2px; }
        .color-picker::-webkit-color-swatch { border-radius: 4px; border: 1px solid rgba(0,0,0,0.2); }

        /* Кастомная палитра цвета текста */
        .color-picker-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .color-picker-btn {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            background: var(--bg-color);
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .color-picker-btn:hover {
            background: rgba(0,0,0,0.06);
            border-color: rgba(0,0,0,0.18);
        }
        [data-theme="dark"] .color-picker-btn {
            border-color: rgba(255,255,255,0.2);
        }
        [data-theme="dark"] .color-picker-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.3);
        }
        .color-picker-btn .color-preview {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.25);
        }
        [data-theme="dark"] .color-picker-btn .color-preview {
            border-color: rgba(255,255,255,0.35);
        }
        .color-palette-popover {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 1100;
            background: var(--bg-color);
            border: 1px solid #ffffff;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
            min-width: 200px;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34, 1.2, 0.64, 1);
            overflow: hidden;
        }
        [data-theme="dark"] .color-palette-popover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.4);
        }
        .color-picker-wrap.is-open .color-palette-popover {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .color-palette-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 6px;
            margin-bottom: 10px;
        }
        .color-swatch {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.15);
            cursor: pointer;
            transition: transform 0.1s ease;
        }
        .color-swatch:hover {
            transform: scale(1.1);
        }
        .color-palette-custom {
            border-top: 1px solid rgba(0,0,0,0.08);
            padding-top: 10px;
            margin-top: 4px;
        }
        [data-theme="dark"] .color-palette-custom {
            border-top-color: rgba(255,255,255,0.08);
        }
        .color-palette-custom label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-color);
            cursor: pointer;
            margin: 0;
        }
        .color-palette-custom input[type="color"] {
            width: 28px;
            height: 28px;
            padding: 2px;
            border: 1px solid #ffffff;
            border-radius: 6px;
            cursor: pointer;
            background: transparent;
        }
        .color-palette-custom input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        .color-palette-custom input[type="color"]::-webkit-color-swatch { border-radius: 4px; border: none; }

        /* Кастомные окошки: размер и шрифт */
        .font-size-picker-wrap,
        .font-family-picker-wrap {
            position: relative;
            display: inline-block;
        }
        .font-size-picker-btn,
        .font-family-picker-btn {
            min-width: 88px;
            height: 32px;
            padding: 0 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-color);
            background: transparent;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .font-size-picker-btn:hover,
        .font-family-picker-btn:hover {
            background: rgba(0,0,0,0.06);
            border-color: rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .font-size-picker-btn:hover,
        [data-theme="dark"] .font-family-picker-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.1);
        }
        .font-size-popover,
        .font-family-popover {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 1100;
            background: var(--bg-color);
            border: 1px solid #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
            min-width: 160px;
            max-height: 280px;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34, 1.2, 0.64, 1);
            overflow: hidden;
            padding: 0;
        }
        .font-size-popover-inner,
        .font-family-popover-inner {
            max-height: 280px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 12px;
        }
        .font-size-popover-inner::-webkit-scrollbar,
        .font-family-popover-inner::-webkit-scrollbar {
            width: 6px;
        }
        .font-size-popover-inner::-webkit-scrollbar-track,
        .font-family-popover-inner::-webkit-scrollbar-track {
            background: transparent;
        }
        .font-size-popover-inner::-webkit-scrollbar-thumb,
        .font-family-popover-inner::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.2);
            border-radius: 3px;
        }
        [data-theme="dark"] .font-size-popover-inner::-webkit-scrollbar-thumb,
        [data-theme="dark"] .font-family-popover-inner::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
        }
        .font-size-popover-inner::-webkit-scrollbar-thumb:hover,
        .font-family-popover-inner::-webkit-scrollbar-thumb:hover {
            background: rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .font-size-popover-inner::-webkit-scrollbar-thumb:hover,
        [data-theme="dark"] .font-family-popover-inner::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }
        [data-theme="dark"] .font-size-popover,
        [data-theme="dark"] .font-family-popover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.4);
        }
        .font-size-picker-wrap.is-open .font-size-popover,
        .font-family-picker-wrap.is-open .font-family-popover {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .font-size-item,
        .font-family-item {
            display: block;
            width: 100%;
            padding: 8px 12px;
            text-align: left;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: none;
            color: var(--text-color);
            cursor: pointer;
            transition: background 0.15s ease;
            box-sizing: border-box;
        }
        .font-size-item:hover,
        .font-family-item:hover {
            background: rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .font-size-item:hover,
        [data-theme="dark"] .font-family-item:hover {
            background: rgba(255,255,255,0.08);
        }
        .font-family-item {
            font-family: inherit;
        }
        .font-size-custom,
        .font-family-custom {
            border-top: 1px solid rgba(0,0,0,0.08);
            padding-top: 10px;
            margin-top: 4px;
        }
        [data-theme="dark"] .font-size-custom,
        [data-theme="dark"] .font-family-custom {
            border-top-color: rgba(255,255,255,0.08);
        }
        .font-size-custom input,
        .font-family-custom input {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 10px;
            margin-top: 6px;
            border: 1px solid #ffffff;
            border-radius: 8px;
            font-size: 13px;
            background: var(--bg-color);
            color: var(--text-color);
        }
        .font-size-custom label,
        .font-family-custom label {
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.9;
        }
        .font-size-custom button,
        .font-family-custom button {
            margin-top: 8px;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
            border: 1px solid #ffffff;
            background: var(--text-color);
            color: var(--bg-color);
            cursor: pointer;
        }

        /* ——— Общий вид редактора (в одном стиле с панелью) ——— */
        .editor-page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
        }
        .editor-field {
            width: 100%;
            max-width: 920px;
            box-sizing: border-box;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.5;
            font-family: inherit;
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid #ffffff;
            border-radius: 10px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        [data-theme="dark"] .editor-field {
            border-color: #ffffff;
        }
        .editor-field:hover {
            border-color: #ffffff;
        }
        [data-theme="dark"] .editor-field:hover {
            border-color: #ffffff;
        }
        .editor-field:focus {
            outline: none;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }
        [data-theme="dark"] .editor-field:focus {
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.2);
        }
        .editor-field::placeholder {
            color: var(--text-color);
            opacity: 0.5;
        }
        #title.editor-field {
            font-size: 1.05rem;
            font-weight: 500;
            margin-bottom: 0;
        }
        #content.editor-field {
            min-height: 200px;
            resize: vertical;
            white-space: pre-wrap;
        }
        #contentVisual.editor-field {
            min-height: 200px;
            line-height: 1.6;
        }
        
        /* Блоки кода внутри визуального редактора */
        #contentVisual .code-block {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        
        #submitButton {
            width: 100%;
            max-width: 920px;
            box-sizing: border-box;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid #ffffff;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        [data-theme="dark"] #submitButton {
            border-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        #submitButton:hover {
            background: var(--text-color);
            color: var(--bg-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        #submitButton.editing {
            background: #e65100;
            color: #fff;
            border-color: #e65100;
        }
        #submitButton.editing:hover {
            background: #bf360c;
            color: #fff;
        }
        #blogForm {
            gap: 12px;
        }
        /* Меньший отступ между блоком с заголовком и полем контента */
        #content.editor-field,
        #contentVisual.editor-field {
            margin-top: -4px;
        }

        /* ——— Система уведомлений ——— */
        .notification-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
            pointer-events: none;
        }
        
        .notification {
            background: var(--bg-color);
            color: var(--text-color);
            border: 2px solid var(--border-color, #000);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            opacity: 0;
            transform: translateX(-100%);
            transition: all 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            pointer-events: auto;
            min-width: 300px;
        }
        
        [data-theme="dark"] .notification {
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .notification.hide {
            opacity: 0;
            transform: translateX(-100%);
        }
        
        .notification-icon {
            font-size: 20px;
            line-height: 1;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            color: var(--text-color);
        }
        
        .notification-message {
            font-size: 13px;
            line-height: 1.4;
            color: var(--text-color);
            opacity: 0.85;
            word-wrap: break-word;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.5;
            transition: opacity 0.2s ease;
            flex-shrink: 0;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
        .notification.success {
            border-color: var(--text-color);
        }
        
        .notification.error {
            border-color: #dc3545;
        }
        
        .notification.warning {
            border-color: #ffc107;
        }
        
        .notification.info {
            border-color: var(--text-color);
        }
        
        /* ——— Диалог ошибки целостности ——— */
        .integrity-error-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        }
        
        .integrity-error-overlay.show {
            display: flex;
        }
        
        .integrity-error-dialog {
            background: var(--bg-color);
            border: 3px solid #dc3545;
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .integrity-error-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .integrity-error-icon {
            width: 48px;
            height: 48px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .integrity-error-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }
        
        .integrity-error-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .integrity-error-message {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            opacity: 0.9;
        }
        
        .integrity-error-button {
            width: 100%;
            padding: 14px 24px;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .integrity-error-button:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        
        .integrity-error-button:active {
            transform: translateY(0);
        }
        
        /* ——— Диалог подтверждения удаления ——— */
        .delete-confirm-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            z-index: 10002;
            align-items: center;
            justify-content: center;
        }
        
        .delete-confirm-overlay.show {
            display: flex;
        }
        
        .delete-confirm-dialog {
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.3);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        [data-theme="dark"] .delete-confirm-dialog {
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
        }
        
        .delete-confirm-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .delete-confirm-icon {
            width: 48px;
            height: 48px;
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .delete-confirm-icon svg {
            width: 24px;
            height: 24px;
            fill: var(--text-color);
        }
        
        .delete-confirm-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .delete-confirm-message {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            opacity: 0.85;
        }
        
        .delete-confirm-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .delete-confirm-btn {
            padding: 12px 24px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .delete-confirm-btn.cancel {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .delete-confirm-btn.cancel:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .delete-confirm-btn.delete {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .delete-confirm-btn.delete:hover {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        /* ——— Меню "Прочее" ——— */
        .more-menu-wrap {
            position: relative;
            display: inline-block;
        }
        
        .more-menu-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 200px;
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 12px;
            padding: 8px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        [data-theme="dark"] .more-menu-dropdown {
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        
        .more-menu-wrap.is-open .more-menu-dropdown {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .more-menu-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            text-align: left;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 14px;
            cursor: pointer;
            transition: background 0.15s ease;
            box-sizing: border-box;
        }
        
        .more-menu-item:hover {
            background: rgba(0,0,0,0.06);
        }
        
        [data-theme="dark"] .more-menu-item:hover {
            background: rgba(255,255,255,0.08);
        }
        
        .more-menu-item.has-submenu {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .more-menu-item.has-submenu::after {
            content: '→';
            font-size: 16px;
            opacity: 0.6;
        }
        
        /* Подменю для вставки includes */
        .more-submenu {
            display: none;
            position: absolute;
            left: calc(100% + 4px);
            top: 0;
            min-width: 220px;
            max-height: 400px;
            overflow-y: auto;
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 12px;
            padding: 8px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            opacity: 0;
            transform: translateX(-8px);
            transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        [data-theme="dark"] .more-submenu {
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        
        .more-menu-item.has-submenu.submenu-open .more-submenu {
            display: block;
            opacity: 1;
            transform: translateX(0);
        }
        
        .more-submenu-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            text-align: left;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s ease;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .more-submenu-item:hover {
            background: rgba(0,0,0,0.06);
        }
        
        [data-theme="dark"] .more-submenu-item:hover {
            background: rgba(255,255,255,0.08);
        }
        
        .more-submenu-empty {
            padding: 16px;
            text-align: center;
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.6;
        }
        
        /* Диалог сохранения в includes */
        .save-include-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            z-index: 10003;
            align-items: center;
            justify-content: center;
        }
        
        .save-include-overlay.show {
            display: flex;
        }
        
        .save-include-dialog {
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.3);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        [data-theme="dark"] .save-include-dialog {
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
        }
        
        .save-include-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0 0 20px 0;
        }
        
        .save-include-label {
            display: block;
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        
        .save-include-input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-color);
            color: var(--text-color);
            margin-bottom: 24px;
        }
        
        .save-include-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }
        
        .save-include-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .save-include-btn {
            padding: 12px 24px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .save-include-btn.cancel {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .save-include-btn.cancel:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .save-include-btn.save {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .save-include-btn.save:hover {
            opacity: 0.85;
        }
        
        /* ——— Менеджер бэкапов ——— */
        .backup-manager-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10004;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .backup-manager-overlay.show {
            display: flex;
        }
        
        .backup-manager-dialog {
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .backup-manager-header {
            padding: 24px 28px;
            border-bottom: 2px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .backup-manager-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .backup-manager-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-color);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .backup-manager-close:hover {
            opacity: 1;
        }
        
        .backup-manager-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
        }
        
        .backup-post-group {
            margin-bottom: 24px;
            border: 2px solid #ffffff;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .backup-post-group.deleted-post {
            border-color: rgba(220, 53, 69, 0.5);
            opacity: 0.8;
        }
        
        .backup-post-group.deleted-post .backup-post-header {
            background: rgba(220, 53, 69, 0.05);
        }
        
        .backup-post-header {
            padding: 16px 20px;
            background: var(--bg-color);
            border-bottom: 2px solid #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.2s;
        }
        
        .backup-post-header:hover {
            background: rgba(0,0,0,0.03);
        }
        
        [data-theme="dark"] .backup-post-header:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .backup-post-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }
        
        .backup-post-toggle {
            font-size: 18px;
            color: var(--text-color);
            opacity: 0.6;
            transition: transform 0.3s;
        }
        
        .backup-post-group.expanded .backup-post-toggle {
            transform: rotate(180deg);
        }
        
        .backup-list {
            display: none;
            padding: 16px 20px;
            background: var(--bg-color);
        }
        
        .backup-post-group.expanded .backup-list {
            display: block;
        }
        
        .backup-item {
            padding: 12px 16px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.2s;
        }
        
        .backup-item:last-child {
            margin-bottom: 0;
        }
        
        .backup-item:hover {
            background: rgba(0,0,0,0.03);
        }
        
        [data-theme="dark"] .backup-item:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-number {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 4px;
        }
        
        .backup-date {
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.7;
        }
        
        .backup-actions {
            display: flex;
            gap: 8px;
        }
        
        .backup-btn {
            padding: 8px 16px;
            border: 2px solid #ffffff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .backup-btn:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .backup-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-color);
            opacity: 0.6;
        }
        
        /* Диалог подтверждения удаления бэкапа */
        .delete-backup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 10005;
            align-items: center;
            justify-content: center;
        }
        
        .delete-backup-overlay.show {
            display: flex;
        }
        
        .delete-backup-dialog {
            background: var(--bg-color);
            border: 3px solid #dc3545;
            border-radius: 16px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .delete-backup-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .delete-backup-icon {
            width: 48px;
            height: 48px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .delete-backup-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }
        
        .delete-backup-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .delete-backup-message {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .delete-backup-warning {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid rgba(220, 53, 69, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-color);
            font-weight: 600;
        }
        
        .delete-backup-confirm-text {
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .delete-backup-input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 2px solid #dc3545;
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-color);
            color: var(--text-color);
            margin-bottom: 24px;
        }
        
        .delete-backup-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
        }
        
        .delete-backup-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .delete-backup-btn {
            padding: 12px 24px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .delete-backup-btn.cancel {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .delete-backup-btn.cancel:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .delete-backup-btn.delete {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }
        
        .delete-backup-btn.delete:hover {
            background: #c82333;
        }
        
        .delete-backup-btn.delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Финальное подтверждение удаления бэкапа */
        .final-delete-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 10006;
            align-items: center;
            justify-content: center;
        }
        
        .final-delete-overlay.show {
            display: flex;
        }
        
        .final-delete-dialog {
            background: var(--bg-color);
            border: 3px solid #dc3545;
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.6);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .final-delete-title {
            font-size: 22px;
            font-weight: 700;
            color: #dc3545;
            margin: 0 0 20px 0;
            text-align: center;
        }
        
        .final-delete-message {
            color: var(--text-color);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .final-delete-checkbox-wrap {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid rgba(220, 53, 69, 0.4);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .final-delete-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }
        
        .final-delete-checkbox {
            width: 24px;
            height: 24px;
            min-width: 24px;
            cursor: pointer;
            margin-top: 2px;
        }
        
        .final-delete-checkbox-text {
            font-size: 14px;
            line-height: 1.5;
            color: var(--text-color);
            font-weight: 600;
        }
        
        .final-delete-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .final-delete-btn {
            padding: 14px 28px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 140px;
        }
        
        .final-delete-btn.cancel {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .final-delete-btn.cancel:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .final-delete-btn.confirm {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }
        
        .final-delete-btn.confirm:hover:not(:disabled) {
            background: #c82333;
            transform: scale(1.02);
        }
        
        .final-delete-btn.confirm:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Диалог подтверждения восстановления бэкапа */
        .restore-backup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10005;
            align-items: center;
            justify-content: center;
        }
        
        .restore-backup-overlay.show {
            display: flex;
        }
        
        .restore-backup-dialog {
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .restore-backup-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .restore-backup-icon {
            width: 48px;
            height: 48px;
            background: var(--bg-color);
            border: 2px solid var(--text-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .restore-backup-icon svg {
            width: 28px;
            height: 28px;
            fill: var(--text-color);
        }
        
        .restore-backup-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .restore-backup-message {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .restore-backup-info {
            background: rgba(0, 0, 0, 0.05);
            border: 2px solid #ffffff;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        [data-theme="dark"] .restore-backup-info {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .restore-backup-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .restore-backup-info-item:last-child {
            margin-bottom: 0;
        }
        
        .restore-backup-info-label {
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 600;
        }
        
        .restore-backup-info-value {
            color: var(--text-color);
            font-weight: 600;
        }
        
        .restore-backup-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--text-color);
            line-height: 1.5;
        }
        
        .restore-backup-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .restore-backup-btn {
            padding: 12px 24px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .restore-backup-btn.cancel {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .restore-backup-btn.cancel:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .restore-backup-btn.restore {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .restore-backup-btn.restore:hover {
            opacity: 0.85;
        }
        
        @media (max-width: 768px) {
            .notification-container {
                left: 10px;
                right: 10px;
                max-width: calc(100% - 20px);
            }
            
            .notification {
                min-width: 0;
                width: 100%;
            }
        }

        /* ——— Проверка нумерации ——— */
        .numbering-check-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10005;
            align-items: center;
            justify-content: center;
        }
        
        .numbering-check-overlay.show {
            display: flex;
        }
        
        .numbering-check-dialog {
            background: var(--bg-color);
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 28px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            animation: dialogScaleIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
        }
        
        .numbering-check-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .numbering-check-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .numbering-check-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-color);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .numbering-check-close:hover {
            opacity: 1;
        }
        
        .numbering-check-content {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .numbering-status {
            padding: 16px 20px;
            border: 2px solid #ffffff;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-color);
        }
        
        .numbering-status.success {
            background: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
        }
        
        .numbering-status.warning {
            background: rgba(255, 193, 7, 0.1);
            border-color: rgba(255, 193, 7, 0.3);
        }
        
        .numbering-issues-list {
            margin-top: 16px;
        }
        
        .numbering-issue-item {
            padding: 12px 16px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            margin-bottom: 12px;
            background: var(--bg-color);
        }
        
        .numbering-issue-item:last-child {
            margin-bottom: 0;
        }
        
        .numbering-issue-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 6px;
        }
        
        .numbering-issue-detail {
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.7;
        }
        
        .numbering-check-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .numbering-check-btn {
            padding: 12px 24px;
            border: 2px solid #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .numbering-check-btn.close {
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .numbering-check-btn.close:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .numbering-check-btn.fix {
            background: var(--text-color);
            color: var(--bg-color);
        }
        
        .numbering-check-btn.fix:hover {
            opacity: 0.85;
        }
        
        .numbering-check-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ——— Гайд для первого запуска ——— */
        .tutorial-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 10010;
            align-items: center;
            justify-content: center;
        }

        .tutorial-overlay.show {
            display: flex;
        }

        .tutorial-spotlight {
            position: fixed;
            border: 3px solid #4CAF50;
            border-radius: 8px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.4);
            pointer-events: none;
            z-index: 10011;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tutorial-tooltip {
            position: fixed;
            background: var(--bg-color);
            border: 2px solid #4CAF50;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.6);
            z-index: 10012;
            animation: tutorialFadeIn 0.4s ease;
            pointer-events: auto;
        }

        @keyframes tutorialFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .tutorial-tooltip h3 {
            margin: 0 0 12px 0;
            font-size: 1.3em;
            color: var(--text-color);
        }

        .tutorial-tooltip p {
            margin: 0 0 20px 0;
            line-height: 1.6;
            color: var(--text-color);
            opacity: 0.9;
        }

        .tutorial-progress {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
        }

        .tutorial-progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }

        .tutorial-progress-dot.active {
            background: #fff;
            width: 24px;
            border-radius: 4px;
        }

        .tutorial-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .tutorial-btn {
            padding: 10px 20px;
            border: 2px solid var(--text-color);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tutorial-btn.skip {
            background: var(--bg-color);
            color: var(--text-color);
        }

        .tutorial-btn.skip:hover {
            opacity: 0.7;
        }

        .tutorial-btn.next {
            background: var(--text-color);
            color: var(--bg-color);
        }

        .tutorial-btn.next:hover {
            opacity: 0.85;
        }

        .tutorial-complete-dialog {
            background: var(--bg-color);
            border: 2px solid #28a745;
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.6);
            animation: tutorialFadeIn 0.4s ease;
        }

        .tutorial-complete-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .tutorial-complete-dialog h2 {
            margin: 0 0 16px 0;
            font-size: 1.8em;
            color: var(--text-color);
        }

        .tutorial-complete-dialog p {
            margin: 0 0 24px 0;
            line-height: 1.6;
            color: var(--text-color);
            opacity: 0.9;
        }

        .tutorial-complete-btn {
            padding: 14px 32px;
            background: #28a745;
            color: #fff;
            border: 2px solid #28a745;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tutorial-complete-btn:hover {
            background: #218838;
            border-color: #218838;
        }

    </style>
</head>
<body>
    <!-- Контейнер для уведомлений -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- Диалог подтверждения удаления -->
    <div class="delete-confirm-overlay" id="deleteConfirmOverlay">
        <div class="delete-confirm-dialog">
            <div class="delete-confirm-header">
                <div class="delete-confirm-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                </div>
                <h2 class="delete-confirm-title">Удалить статью?</h2>
            </div>
            <div class="delete-confirm-message">
                Вы уверены, что хотите удалить эту статью? Это действие нельзя отменить.
            </div>
            <div class="delete-confirm-buttons">
                <button class="delete-confirm-btn cancel" onclick="closeDeleteConfirm()">Отмена</button>
                <button class="delete-confirm-btn delete" onclick="confirmDelete()">Удалить</button>
            </div>
        </div>
    </div>

    <!-- Диалог сохранения в includes -->
    <div class="save-include-overlay" id="saveIncludeOverlay">
        <div class="save-include-dialog">
            <h2 class="save-include-title">Сохранить в includes</h2>
            <label class="save-include-label">Название файла:</label>
            <input type="text" class="save-include-input" id="includeNameInput" placeholder="Например: контакты">
            <div class="save-include-buttons">
                <button class="save-include-btn cancel" onclick="closeSaveInclude()">Отмена</button>
                <button class="save-include-btn save" onclick="confirmSaveInclude()">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Менеджер бэкапов -->
    <div class="backup-manager-overlay" id="backupManagerOverlay">
        <div class="backup-manager-dialog">
            <div class="backup-manager-header">
                <h2 class="backup-manager-title">Менеджер бэкапов</h2>
                <button class="backup-manager-close" onclick="closeBackupManager()">×</button>
            </div>
            <div class="backup-manager-content" id="backupManagerContent">
                <div class="backup-empty">Загрузка...</div>
            </div>
        </div>
    </div>

    <!-- Диалог подтверждения удаления бэкапа -->
    <div class="delete-backup-overlay" id="deleteBackupOverlay">
        <div class="delete-backup-dialog">
            <div class="delete-backup-header">
                <div class="delete-backup-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 5v2h2v-2h-2z"/>
                    </svg>
                </div>
                <h2 class="delete-backup-title">Удалить бэкап?</h2>
            </div>
            <div class="delete-backup-message">
                Вы собираетесь удалить бэкап. Это действие необратимо.
            </div>
            <div class="delete-backup-warning">
                ⚠ Внимание! После удаления восстановить бэкап будет невозможно.
            </div>
            <div class="delete-backup-confirm-text">
                Для подтверждения введите слово <strong>УДАЛИТЬ</strong>:
            </div>
            <input type="text" class="delete-backup-input" id="deleteBackupConfirmInput" placeholder="УДАЛИТЬ">
            <div class="delete-backup-buttons">
                <button class="delete-backup-btn cancel" onclick="closeDeleteBackup()">Отмена</button>
                <button class="delete-backup-btn delete" id="confirmDeleteBackupBtn" disabled onclick="openFinalDeleteConfirm()">Удалить бэкап</button>
            </div>
        </div>
    </div>

    <!-- Финальное подтверждение удаления -->
    <div class="final-delete-overlay" id="finalDeleteOverlay">
        <div class="final-delete-dialog">
            <h2 class="final-delete-title">⚠ ПОСЛЕДНЕЕ ПРЕДУПРЕЖДЕНИЕ ⚠</h2>
            <div class="final-delete-message">
                Вы действительно хотите безвозвратно удалить этот бэкап?
            </div>
            <div class="final-delete-checkbox-wrap">
                <label class="final-delete-checkbox-label">
                    <input type="checkbox" class="final-delete-checkbox" id="finalDeleteCheckbox">
                    <span class="final-delete-checkbox-text">
                        Я осознаю все риски и согласен с безвозвратным удалением этого бэкапа. Я понимаю, что восстановить его будет невозможно.
                    </span>
                </label>
            </div>
            <div class="final-delete-buttons">
                <button class="final-delete-btn cancel" onclick="closeFinalDelete()">Отмена</button>
                <button class="final-delete-btn confirm" id="finalDeleteBtn" disabled onclick="executeFinalDelete()">УДАЛИТЬ НАВСЕГДА</button>
            </div>
        </div>
    </div>

    <!-- Диалог подтверждения восстановления -->
    <div class="restore-backup-overlay" id="restoreBackupOverlay">
        <div class="restore-backup-dialog">
            <div class="restore-backup-header">
                <div class="restore-backup-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                    </svg>
                </div>
                <h2 class="restore-backup-title">Восстановить бэкап?</h2>
            </div>
            <div class="restore-backup-message">
                Вы собираетесь восстановить статью из бэкапа. Текущая версия статьи будет заменена.
            </div>
            <div class="restore-backup-info" id="restoreBackupInfo">
                <!-- Информация о бэкапе будет добавлена через JS -->
            </div>
            <div class="restore-backup-warning">
                ⚠ Внимание! Текущая версия статьи будет перезаписана содержимым из бэкапа.
            </div>
            <div class="restore-backup-buttons">
                <button class="restore-backup-btn cancel" onclick="closeRestoreBackup()">Отмена</button>
                <button class="restore-backup-btn restore" onclick="confirmRestoreBackup()">Восстановить</button>
            </div>
        </div>
    </div>

    <!-- Диалог проверки нумерации -->
    <div class="numbering-check-overlay" id="numberingCheckOverlay">
        <div class="numbering-check-dialog">
            <div class="numbering-check-header">
                <h2 class="numbering-check-title">Проверка нумерации статей</h2>
                <button class="numbering-check-close" onclick="closeNumberingCheck()">×</button>
            </div>
            <div class="numbering-check-content" id="numberingCheckContent">
                <div class="numbering-status">Проверка...</div>
            </div>
            <div class="numbering-check-buttons">
                <button class="numbering-check-btn close" onclick="closeNumberingCheck()">Закрыть</button>
                <button class="numbering-check-btn fix" id="fixNumberingBtn" style="display:none;" onclick="fixNumbering()">Исправить</button>
            </div>
        </div>
    </div>

    <!-- Гайд для первого запуска -->
    <div class="tutorial-overlay" id="tutorialOverlay">
        <div class="tutorial-spotlight" id="tutorialSpotlight"></div>
        <div class="tutorial-tooltip" id="tutorialTooltip">
            <div class="tutorial-progress" id="tutorialProgress"></div>
            <h3 id="tutorialTitle"></h3>
            <p id="tutorialText"></p>
            <div class="tutorial-buttons">
                <button class="tutorial-btn skip" onclick="skipTutorial()">Пропустить</button>
                <button class="tutorial-btn next" onclick="nextTutorialStep()">Далее</button>
            </div>
        </div>
        <div class="tutorial-complete-dialog" id="tutorialComplete" style="display:none;">
            <div class="tutorial-complete-icon">🎉</div>
            <h2>Обучение завершено!</h2>
            <p>Теперь вы знаете основы работы с редактором NPBlog. Приятного использования!</p>
            <button class="tutorial-complete-btn" onclick="completeTutorial()">OK</button>
        </div>
    </div>

    <h1 class="editor-page-title">NPBlog</h1>
    <form id="blogForm">
        <div class="editor-toolbar-wrap">
        <div class="mode-toggle">
            <button type="button" id="modeVisualBtn" class="format-btn" title="Визуальный режим">Визуально</button>
            <button type="button" id="modeCodeBtn" class="format-btn" title="Режим кода">Код</button>
        </div>
        <div class="formatting-buttons-sticky-wrap">
            <div class="formatting-buttons-sentinel" id="formatBarSentinel" aria-hidden="true"></div>
            <div class="formatting-buttons-placeholder" id="formatBarPlaceholder"></div>
            <div class="formatting-buttons" id="formatBarRow">
            <span class="toolbar-group">
                <button type="button" id="btn-bold" class="format-btn" onclick="formatText('b')" title="Жирный">B</button>
                <button type="button" id="btn-italic" class="format-btn" onclick="formatText('i')" title="Курсив"><i>I</i></button>
                <button type="button" id="btn-underline" class="format-btn" onclick="formatText('u')" title="Подчеркнутый">U</button>
                <button type="button" id="btn-strike" class="format-btn" onclick="formatText('s')" title="Зачеркнутый"><s>S</s></button>
                <button type="button" id="btn-sup" class="format-btn" onclick="formatText('sup')" title="Верхний индекс">X<sup>2</sup></button>
                <button type="button" id="btn-sub" class="format-btn" onclick="formatText('sub')" title="Нижний индекс">X<sub>2</sub></button>
                <button type="button" id="btn-h2" class="format-btn" onclick="formatText('h2')" title="Подзаголовок">H</button>
                <button type="button" id="btn-spoiler" class="format-btn" onclick="openSpoilerDialog()" title="Сворачиваемый блок"><span class="spoiler-icon">▼</span></button>
                <button type="button" id="btn-marker" class="format-btn" onclick="openMarkerDialog()" title="Маркер">🖍</button>
            </span>
            <span class="toolbar-divider"></span>
            <span class="toolbar-group">
                <button type="button" class="format-btn" onclick="alignText('left')" title="По левому краю">◄</button>
                <button type="button" class="format-btn" onclick="alignText('center')" title="По центру">≡</button>
                <button type="button" class="format-btn" onclick="alignText('right')" title="По правому краю">►</button>
                <button type="button" class="format-btn" onclick="insertList()" title="Список">•</button>
            </span>
            <span class="toolbar-divider"></span>
            <span class="toolbar-group">
                <button type="button" class="format-btn" onclick="addLink()" title="Ссылка">🔗</button>
                <button type="button" class="format-btn" onclick="showImageUpload()" title="Добавить изображение">📷</button>
                <button type="button" class="format-btn" onclick="showMediaDialog()" title="Добавить медиа">🎬</button>
                <!-- <button type="button" class="format-btn" onclick="insertCode()" title="Вставить код">{ }</button> Временно не работает   -->
            </span>
            <span class="toolbar-divider"></span>
            <span class="toolbar-group">
                <div class="font-size-picker-wrap" id="fontSizeWrapMain">
                    <button type="button" class="format-btn font-size-picker-btn" title="Размер шрифта">Размер</button>
                    <div class="font-size-popover">
                        <div class="font-size-popover-inner">
                        <button type="button" class="font-size-item" data-size="12">12px</button>
                        <button type="button" class="font-size-item" data-size="14">14px</button>
                        <button type="button" class="font-size-item" data-size="16">16px</button>
                        <button type="button" class="font-size-item" data-size="18">18px</button>
                        <button type="button" class="font-size-item" data-size="20">20px</button>
                        <button type="button" class="font-size-item" data-size="24">24px</button>
                        <button type="button" class="font-size-item" data-size="28">28px</button>
                        <button type="button" class="font-size-item" data-size="32">32px</button>
                        <div class="font-size-custom">
                            <label>Свой размер (8–72)</label>
                            <input type="number" id="fontSizeCustomMain" min="8" max="72" placeholder="px">
                            <button type="button" onclick="applyCustomFontSize('fontSizeWrapMain')">Применить</button>
                        </div>
                        </div>
                    </div>
                </div>
                <div class="font-family-picker-wrap" id="fontFamilyWrapMain">
                    <button type="button" class="format-btn font-family-picker-btn" title="Шрифт">Шрифт</button>
                    <div class="font-family-popover">
                        <div class="font-family-popover-inner">
                        <button type="button" class="font-family-item" data-font="Arial" style="font-family:Arial">Arial</button>
                        <button type="button" class="font-family-item" data-font="Times New Roman" style="font-family:'Times New Roman'">Times New Roman</button>
                        <button type="button" class="font-family-item" data-font="Open Sans" style="font-family:'Open Sans'">Open Sans</button>
                        <button type="button" class="font-family-item" data-font="Verdana" style="font-family:Verdana">Verdana</button>
                        <button type="button" class="font-family-item" data-font="Helvetica" style="font-family:Helvetica">Helvetica</button>
                        <button type="button" class="font-family-item" data-font="Georgia" style="font-family:Georgia">Georgia</button>
                        <button type="button" class="font-family-item" data-font="PT Sans" style="font-family:'PT Sans'">PT Sans</button>
                        <button type="button" class="font-family-item" data-font="Comic Sans MS" style="font-family:'Comic Sans MS'">Comic Sans MS</button>
                        <div class="font-family-custom">
                            <label>Свой шрифт</label>
                            <input type="text" id="fontFamilyCustomMain" placeholder="Название шрифта">
                            <button type="button" onclick="applyCustomFontFamily('fontFamilyWrapMain')">Применить</button>
                        </div>
                        </div>
                    </div>
                </div>
                <div class="color-picker-wrap" id="colorPickerWrapMain">
                    <button type="button" class="color-picker-btn" title="Цвет текста" aria-label="Цвет текста"><span class="color-preview" style="background:#333;"></span></button>
                    <div class="color-palette-popover">
                        <div class="color-palette-grid" id="colorPaletteGridMain"></div>
                        <div class="color-palette-custom">
                            <label>Свой цвет <input type="color" id="textColorCustomMain" value="#333333"></label>
                        </div>
                    </div>
                </div>
            </span>
            <span class="toolbar-group">
                <div class="more-menu-wrap" id="moreMenuWrap">
                    <button type="button" class="format-btn" title="Прочее" onclick="toggleMoreMenu()">⋯</button>
                    <div class="more-menu-dropdown" id="moreMenuDropdown">
                        <button type="button" class="more-menu-item" onclick="openSaveInclude()">Сохранить в includes</button>
                        <button type="button" class="more-menu-item has-submenu" onclick="toggleIncludesSubmenu(event)">
                            Вставить
                            <div class="more-submenu" id="includesSubmenu">
                                <div class="more-submenu-empty">Загрузка...</div>
                            </div>
                        </button>
                        <button type="button" class="more-menu-item has-submenu" onclick="toggleArticlesSubmenu(event)">
                            Вставить ссылку на статью
                            <div class="more-submenu" id="articlesSubmenu">
                                <div class="more-submenu-empty">Загрузка...</div>
                            </div>
                        </button>
                    </div>
                </div>
            </span>
            </div>
        </div>
        <input class="content228 editor-field" type="text" id="title" placeholder="Заголовок статьи" required>
        </div>
        <textarea class="content228 editor-field" id="content" placeholder="Содержание статьи" style="display:none;"></textarea>
        <div id="contentVisual" class="content228 editor-field" contenteditable="true"></div>
        <button type="submit" id="submitButton">Опубликовать</button>
    </form>

    <div id="editorContextMenu" class="editor-context-menu" role="menu">
        <button type="button" class="editor-context-item" data-cmd="paste" role="menuitem">Вставить</button>
        <button type="button" class="editor-context-item" data-cmd="copy" role="menuitem">Копировать</button>
        <button type="button" class="editor-context-item" data-cmd="cut" role="menuitem">Вырезать</button>
        <button type="button" class="editor-context-item" data-cmd="delete" role="menuitem">Удалить</button>
        <span class="editor-context-sep"></span>
        <button type="button" class="editor-context-item" data-cmd="link" role="menuitem">Вставить ссылку</button>
        <button type="button" class="editor-context-item" data-cmd="image" role="menuitem">Вставить изображение</button>
    </div>

                <div class="editor-menu-wrap" id="editorMenuWrap">
                    <button type="button" class="editor-menu-btn" id="editorMenuBtn" aria-haspopup="true" aria-expanded="false">Меню</button>
                    <div class="editor-menu-dropdown" role="menu">
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="toggleManagePosts()">Управление статьями</button>
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="openGlobalSettings()">Глобальные параметры</button>
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="openBackupManager()">Менеджер бэкапов</button>
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="checkPostNumbering()">Проверка нумерации</button>
                        <!-- <button type="button" class="editor-menu-item" role="menuitem" onclick="resetTutorial()">🔄 Сбросить обучение</button> -->
                        <button type="button" class="editor-menu-item" id="theme-toggle" role="menuitem">Изменить тему</button>
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="window.location.href='ftp.php'">Опубликовать по FTP</button>
                        <button type="button" class="editor-menu-item" role="menuitem" onclick="window.location.href='data/blog.html'">Перейти к Blog.html</button>
                        <div class="editor-menu-version">ver 2.108 beta</div>
                    </div>
                </div>

        <div class="manage-posts" id="managePosts">
        <div class="manage-posts-header">
            <h2>Все статьи</h2>
            <button type="button" class="close-manage" onclick="toggleManagePosts()" aria-label="Закрыть">×</button>
        </div>
        <div id="postsList"></div>
    </div>
    
    <div id="imageUploadDialog" class="dialog">
    <div class="dialog-content">
        <h3>Добавить изображение</h3>
        

        <div class="image-source-toggle">
            <label>
                <input type="radio" name="imageSource" value="file" checked> Загрузить файл
            </label>
            <label>
                <input type="radio" name="imageSource" value="url"> Вставить ссылку
            </label>
        </div>


        <div id="fileUploadContainer">
            <input type="file" id="imageFile" accept="image/*" multiple>
        </div>


        <div id="urlContainer" style="display: none;">
            <input type="text" id="imageUrl" placeholder="Введите URL изображения (несколько — с новой строки или через запятую)" class="image-url-input">
        </div>
        <div class="form-group">
    <label for="imageCaption">Подпись к изображению:</label>
    <input type="text" id="imageCaption" class="form-control" placeholder="Введите подпись (необязательно)">
</div>

        <div class="image-size-controls">
            <label>
                Размер:
                <select id="imageSize">
                    <option value="small">Маленький</option>
                    <option value="medium" selected>Средний</option>
                    <option value="large">Большой</option>
                    <option value="custom">Свой размер</option>
                </select>
            </label>
            <label for="gridLayout">Расположение:</label>
            <select id="gridLayout">
            <option value="">Обычное</option>
            <option value="2x1">2×1</option>
            <option value="2x2">2×2</option>
            <option value="3x1">3×1</option>
            <option value="3x2">3×2</option>
            <option value="3x3">3×3</option>
            </select>
            <div id="customSizeInputs" style="display: none;">
                <div class="size-input-group">
                    <input type="number" id="customWidth" placeholder="Ширина">
                    <select id="widthUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                    </select>
                </div>
                <div class="size-input-group">
                    <input type="number" id="customHeight" placeholder="Высота">
                    <select id="heightUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="dialog-buttons">
            <button onclick="processImage()">Добавить</button>
            <button onclick="closeImageDialog()">Отмена</button>
        </div>
    </div>
</div>

    <div id="codeDialog" class="dialog code-dialog">
    <div class="dialog-content">
        <h3>Вставить код</h3>
        <select id="codeLanguage" class="language-select">
            <option value="javascript">JavaScript</option>
            <option value="php">PHP</option>
            <option value="html">HTML</option>
            <option value="css">CSS</option>
            <option value="python">Python</option>
            <option value="sql">SQL</option>
            <option value="java">Java</option>
            <option value="cpp">C++</option>
            <option value="csharp">C#</option>
            <option value="ruby">Ruby</option>
            <option value="plain">Текст</option>
        </select>
        <textarea id="codeInput" class="code-input" placeholder="Вставьте ваш код сюда..."></textarea>
        <div class="dialog-buttons">
            <button onclick="insertCodeBlock()">Вставить</button>
            <button onclick="closeCodeDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="fontSizeDialog" class="dialog">
    <div class="dialog-content">
        <h3>Указать размер шрифта</h3>
        <input type="number" id="customFontSize" min="8" max="72" placeholder="Размер в px">
        <div class="dialog-buttons">
            <button onclick="setCustomFontSize()">Применить</button>
            <button onclick="closeFontSizeDialog()">Отмена</button>
        </div>
    </div>
</div>


<div id="mediaDialog" class="dialog">
    <div class="dialog-content">
        <h3>Добавить медиа</h3>
        <input type="text" id="mediaUrl" placeholder="Вставьте ссылку на YouTube, Vimeo или аудио файл" class="media-input">
        <div class="dialog-buttons">
            <button onclick="insertMedia()">Вставить</button>
            <button onclick="closeMediaDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="spoilerDialog" class="dialog">
    <div class="dialog-content">
        <h3>Сворачиваемый блок</h3>
        <label for="spoilerTitle">Заголовок блока:</label>
        <input type="text" id="spoilerTitle" placeholder="Например: Подробности" class="form-control">
        <div class="dialog-buttons">
            <button onclick="insertSpoiler()">Вставить</button>
            <button onclick="closeSpoilerDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="markerDialog" class="dialog">
    <div class="dialog-content">
        <h3>Выделить маркером</h3>
        <label>Выберите стиль:</label>
        <div class="marker-styles">
            <button class="marker-style-btn active" data-style="straight" title="Ровное">
                <span class="marker-style-preview marker-preview-straight">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="rough" title="Кривое">
                <span class="marker-style-preview marker-preview-rough">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="zigzag" title="Зигзагом">
                <span class="marker-style-preview marker-preview-zigzag">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="wavy" title="Волнистое">
                <span class="marker-style-preview marker-preview-wavy">Текст</span>
            </button>
        </div>
        <label style="margin-top: 16px;">Выберите цвет:</label>
        <div class="marker-colors">
            <button class="marker-color-btn" data-color="#ffeb3b" style="background: #ffeb3b;" title="Желтый"></button>
            <button class="marker-color-btn" data-color="#4caf50" style="background: #4caf50;" title="Зеленый"></button>
            <button class="marker-color-btn" data-color="#2196f3" style="background: #2196f3;" title="Синий"></button>
            <button class="marker-color-btn" data-color="#ff9800" style="background: #ff9800;" title="Оранжевый"></button>
            <button class="marker-color-btn" data-color="#e91e63" style="background: #e91e63;" title="Розовый"></button>
            <button class="marker-color-btn" data-color="#9c27b0" style="background: #9c27b0;" title="Фиолетовый"></button>
        </div>
        <div class="dialog-buttons">
            <button onclick="closeMarkerDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="linkDialog" class="dialog">
    <div class="dialog-content">
        <h3>Вставить ссылку</h3>
        <div class="form-group">
            <label for="linkUrl">URL</label>
            <input type="text" id="linkUrl" class="form-control" placeholder="https://">
        </div>
        <div class="form-group">
            <label for="linkText">Текст ссылки (необязательно)</label>
            <input type="text" id="linkText" class="form-control" placeholder="Оставьте пустым — будет использован выделенный текст">
        </div>
        <div class="dialog-buttons">
            <button onclick="insertLinkFromDialog()">Вставить</button>
            <button onclick="closeLinkDialog()">Отмена</button>
        </div>
    </div>
</div>

<script>
    // ——— Система уведомлений ———
    function showNotification(message, type = 'info', title = '') {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        const titles = {
            success: title || 'Успешно',
            error: title || 'Ошибка',
            warning: title || 'Внимание',
            info: title || 'Информация'
        };
        
        notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                <div class="notification-title">${titles[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">×</button>
        `;
        
        container.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, 5000);
    }
    
    function closeNotification(btn) {
        const notification = btn.closest('.notification');
        if (!notification) return;
        
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        setTimeout(() => {
            notification.remove();
        }, 400);
    }

    let currentEditId = null;
    let editorMode = 'visual'; // 'visual' | 'code'
    let savedRange = null;
    let linkInsertStart = 0;
    let linkInsertEnd = 0;
    let colorInsertStart = 0;
    let colorInsertEnd = 0;

    function saveSelection() {
        const ve = document.getElementById('contentVisual');
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (ve && ve.contains(range.commonAncestorContainer)) {
            savedRange = range.cloneRange();
        }
    }

    // Стабильная логика тулбара: не даём кнопкам забирать фокус у редактора.
    // Это сохраняет каретку/выделение и делает execCommand предсказуемым.
    (function initToolbarFocusGuard() {
        var bar = document.getElementById('formatBarRow');
        if (!bar) return;
        bar.addEventListener('mousedown', function(e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            // Не ломаем клики внутри поповеров/диалогов
            if (e.target.closest('.font-size-popover, .font-family-popover, .color-palette-popover')) return;
            e.preventDefault();
            if (editorMode === 'visual') {
                var ve = document.getElementById('contentVisual');
                if (ve) ve.focus();
                saveSelection();
            } else {
                var ta = document.getElementById('content');
                if (ta) ta.focus();
            }
        }, true);
    })();

    // Надёжно обновляем savedRange при наборе/кликах внутри редактора (пробел/Enter/мышь и т.п.)
    (function initVisualSelectionTracking() {
        var ve = document.getElementById('contentVisual');
        if (!ve) return;
        ['mouseup','keyup','input','click','focus','touchend','compositionend'].forEach(function(evt) {
            ve.addEventListener(evt, function() {
                if (editorMode === 'visual') saveSelection();
            }, true);
        });
    })();

    function cleanContentForSave(html) {
        // Создаем временный контейнер для очистки HTML
        var temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Удаляем все элементы интерфейса редактора
        var elementsToRemove = temp.querySelectorAll(
            '.image-toolbar, ' +
            '.image-align-dropdown, ' +
            '.image-size-indicator, ' +
            '.image-resize-handle, ' +
            '.blog-image-overlay'
        );
        elementsToRemove.forEach(function(el) {
            el.parentNode.removeChild(el);
        });
        
        // Удаляем атрибуты data-image-id
        var wraps = temp.querySelectorAll('[data-image-id]');
        wraps.forEach(function(el) {
            el.removeAttribute('data-image-id');
        });
        
        // Удаляем классы selected
        var selected = temp.querySelectorAll('.selected');
        selected.forEach(function(el) {
            el.classList.remove('selected');
        });
        
        // Убираем служебные ZWS (\u200B)
        return temp.innerHTML.replace(/\u200B/g, '');
    }

    function setMode(mode) {
        editorMode = mode;
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        if (mode === 'visual') {
            // sync from code -> visual
            if (ta.style.display !== 'none') {
                ve.innerHTML = ta.value;
                wrapExistingEditorImages();
            }
            ve.style.display = '';
            ta.style.display = 'none';
        } else {
            // sync from visual -> code - очищаем от элементов интерфейса
            if (ve.style.display !== 'none') {
                ta.value = cleanContentForSave(ve.innerHTML);
            }
            ta.style.display = '';
            ve.style.display = 'none';
        }
        document.getElementById('modeVisualBtn').style.backgroundColor = (mode==='visual') ? '#3f3f3fff' : '';
        document.getElementById('modeCodeBtn').style.backgroundColor = (mode==='code') ? '#3f3f3fff' : '';
    }

    const toggleState = { b: false, i: false, u: false, s: false };

    function setBtnActive(id, active) {
        const btn = document.getElementById(id);
        if (!btn) return;
        if (active) btn.classList.add('active'); else btn.classList.remove('active');
    }

    function updateActiveButtons() {
        if (editorMode !== 'visual') return;
        const ve = document.getElementById('contentVisual');
        const sel = window.getSelection();
        // Не подсвечиваем кнопки, если выделение/каретка не в поле статьи
        if (!ve || !sel || sel.rangeCount === 0) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        const r = sel.getRangeAt(0);
        if (!ve.contains(r.commonAncestorContainer)) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        // Проверяем состояние для bold/italic/underline/strike
        const isBold = document.queryCommandState('bold');
        const isItalic = document.queryCommandState('italic');
        const isUnderline = document.queryCommandState('underline');
        const isStrike = document.queryCommandState('strikeThrough');
        const isSup = document.queryCommandState('superscript');
        const isSub = document.queryCommandState('subscript');
        const fb = (document.queryCommandValue('formatBlock') || '').toString().toLowerCase();
        const isH2 = fb.includes('h2');

        // верхняя панель
        setBtnActive('btn-bold', isBold);
        setBtnActive('btn-italic', isItalic);
        setBtnActive('btn-underline', isUnderline);
        setBtnActive('btn-strike', isStrike);
        setBtnActive('btn-sup', isSup);
        setBtnActive('btn-sub', isSub);
        setBtnActive('btn-h2', isH2);
    }

    // Теги форматирования, которые нужно «покидать» при выключении режима
    var FORMAT_TAGS = {
        bold: ['B','STRONG'],
        italic: ['I','EM'],
        underline: ['U'],
        strikeThrough: ['S','STRIKE','DEL'],
        superscript: ['SUP'],
        subscript: ['SUB']
    };

    /**
     * При выключении inline-формата на collapsed каретке:
     *  - Если форматирующий тег пуст / содержит только <br> (новая строка после Enter)
     *    → полностью убираем обёртку (unwrap), каретка остаётся на той же строке.
     *  - Иначе (текст + пробел) → вставляем ZWS после тега и ставим туда каретку.
     */
    function escapeFormatNode(cmd, ve) {
        var tags = FORMAT_TAGS[cmd];
        if (!tags) return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var range = sel.getRangeAt(0);
        if (!range.collapsed) return;

        // Ищем ближайший форматирующий предок
        var node = range.startContainer;
        if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
        var formatEl = null;
        while (node && node !== ve) {
            if (node.nodeType === Node.ELEMENT_NODE && tags.indexOf(node.tagName) !== -1) {
                formatEl = node;
                break;
            }
            node = node.parentNode;
        }
        if (!formatEl) return;

        // Проверяем, пустой ли тег (только пробелы/ZWS и/или <br>)
        var text = formatEl.textContent.replace(/[\u200B\s]/g, '');
        var isEmpty = text.length === 0;

        if (isEmpty) {
            // Unwrap: заменяем <b><br></b> на просто <br>
            var parent = formatEl.parentNode;
            var br = formatEl.querySelector('br');
            if (!br) br = document.createElement('br');
            parent.insertBefore(br, formatEl);
            parent.removeChild(formatEl);
            // Ставим каретку перед <br> (на эту строку)
            var newRange = document.createRange();
            newRange.setStartBefore(br);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        } else {
            // Вставляем ZWS после тега и ставим туда каретку
            var zws = document.createTextNode('\u200B');
            formatEl.parentNode.insertBefore(zws, formatEl.nextSibling);
            var newRange = document.createRange();
            newRange.setStart(zws, 1);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        }
    }

    function formatText(tag) {
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        if (editorMode === 'code') {
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const beforeText = ta.value.substring(0, start);
            const afterText = ta.value.substring(end);
            const formattedText = tag === 'h2' ? `<${tag}>${selectedText}</${tag}>\n` : `<${tag}>${selectedText}</${tag}>`;
            ta.value = beforeText + formattedText + afterText;
            ta.setSelectionRange(start + tag.length + 2, start + tag.length + 2 + selectedText.length);
        } else {
            if (ve) ve.focus();
            const map = { b: 'bold', i: 'italic', u: 'underline', s: 'strikeThrough', sup: 'superscript', sub: 'subscript' };
            if (map[tag]) {
                var cmd = map[tag];
                var waOn = document.queryCommandState(cmd);
                document.execCommand(cmd, false, null);
                var isOn = document.queryCommandState(cmd);
                // Если мы выключали формат и каретка collapsed — вытаскиваем из тега
                if (waOn && !isOn) {
                    escapeFormatNode(cmd, ve);
                }
                saveSelection();
                updateActiveButtons();
                return;
            }
            if (tag === 'h2') {
                const current = (document.queryCommandValue('formatBlock') || '').toString().toLowerCase();
                const next = current.includes('h2') ? 'P' : 'H2';
                document.execCommand('formatBlock', false, next);
                saveSelection();
                updateActiveButtons();
                return;
            }
        }
    }

    function alignText(side) {
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            const html = `<div style="text-align: ${side};">${selectedText || '&nbsp;'}</div>`;
            ta.value = before + html + after;
        } else {
            const ve = document.getElementById('contentVisual');
            if (ve) ve.focus();
            const map = { left: 'justifyLeft', center: 'justifyCenter', right: 'justifyRight' };
            const cmd = map[side] || 'justifyLeft';
            document.execCommand(cmd, false, null);
            saveSelection();
        }
    }

    function insertHtmlAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            return;
        }
        range.deleteContents();
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const frag = document.createDocumentFragment();
        let node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            const s = window.getSelection();
            if (s) {
                s.removeAllRanges();
                s.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    /** Вставка блока с изображением(ями) и пустой строки после; курсор ставится в пустой блок, чтобы текст не привязывался к картинке */
    function insertImageBlockAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            ve.appendChild(emptyDiv);
            range = document.createRange();
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
            return;
        }
        range.deleteContents();
        var temp = document.createElement('div');
        temp.innerHTML = html;
        var frag = document.createDocumentFragment();
        var node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            var parent = lastNode.parentNode;
            parent.insertBefore(emptyDiv, lastNode.nextSibling);
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    function insertList() {
        const listTemplate = "\n<ul>\n  <li>Пункт 1</li>\n  <li>Пункт 2</li>\n  <li>Пункт 3</li>\n</ul>\n";
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + listTemplate + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            insertHtmlAtCaret(listTemplate);
        }
    }

    function addLink() {
        saveSelection();
        var urlInput = document.getElementById('linkUrl');
        var textInput = document.getElementById('linkText');
        urlInput.value = 'https://';
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            linkInsertStart = ta.selectionStart;
            linkInsertEnd = ta.selectionEnd;
            textInput.value = ta.value.substring(linkInsertStart, linkInsertEnd).trim();
        } else {
            textInput.value = document.getSelection().toString().trim();
        }
        document.getElementById('linkDialog').style.display = 'block';
        urlInput.focus();
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function(text) {
                if (text && (text = text.trim())) {
                    if (!/^https?:\/\//i.test(text)) text = 'https://' + text.replace(/^\/+/, '');
                    urlInput.value = text;
                }
            }).catch(function() {});
        }
    }

    function closeLinkDialog() {
        document.getElementById('linkDialog').style.display = 'none';
        document.getElementById('linkUrl').value = '';
        document.getElementById('linkText').value = '';
    }

    function insertLinkFromDialog() {
        var url = document.getElementById('linkUrl').value.trim();
        if (!url) {
            showNotification('Введите URL ссылки', 'warning');
            return;
        }
        var linkText = document.getElementById('linkText').value.trim();
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = linkInsertStart;
            var end = linkInsertEnd;
            var selectedText = ta.value.substring(start, end);
            var text = linkText || selectedText || 'ссылка';
            var link = '<a href="' + url + '">' + text + '</a>';
            ta.value = ta.value.substring(0, start) + link + ta.value.substring(end);
            ta.focus();
        } else {
            var text = linkText || (savedRange ? savedRange.toString() : '') || 'ссылка';
            var html = '<a href="' + url + '">' + text + '</a>';
            insertHtmlAtCaret(html);
        }
        closeLinkDialog();
    }

    // Функции для работы с изображениями
    function showImageUpload() {
    saveSelection();
    document.getElementById('imageUploadDialog').style.display = 'block';
}

document.querySelectorAll('input[name="imageSource"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('fileUploadContainer').style.display = 
            this.value === 'file' ? 'block' : 'none';
        document.getElementById('urlContainer').style.display = 
            this.value === 'url' ? 'block' : 'none';
    });
});

function processImage() {
    const imageSource = document.querySelector('input[name="imageSource"]:checked').value;
    const gridLayout = document.getElementById('gridLayout').value;
    const sizeSelect = document.getElementById('imageSize');
    const sizeValue = sizeSelect.value;

    let width, widthUnit = 'px';
    if (sizeValue === 'custom') {
        width = document.getElementById('customWidth').value;
        widthUnit = document.getElementById('widthUnit').value;
    } else {
        const sizes = {
            small: { width: 300 },
            medium: { width: 500 },
            large: { width: 800 }
        };
        width = sizes[sizeValue].width;
    }

    if (imageSource === 'url') {
        const urlInput = document.getElementById('imageUrl').value.trim();
        if (!urlInput) {
            showNotification('Введите URL изображения (можно несколько — каждое с новой строки или через запятую)', 'warning');
            return;
        }
        const urls = urlInput.split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
        const caption = document.getElementById('imageCaption').value.trim();
        if (urls.length === 1) {
            insertImage(urls[0], width, '', widthUnit, '', caption);
        } else {
            insertImagesInGrid(urls, width, widthUnit, gridLayout);
            closeImageDialog();
        }
        return;
    }

    const files = document.getElementById('imageFile').files;
    if (!files.length) {
        showNotification('Выберите хотя бы одно изображение', 'warning');
        return;
    }

    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('image[]', file);
    });


    formData.append('width', width);
    formData.append('widthUnit', widthUnit);
    formData.append('gridLayout', gridLayout);

    fetch('upload_images_grid.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.urls) {
            insertImagesInGrid(data.urls, width, widthUnit, data.gridLayout);
        } else {
            showNotification('Ошибка при загрузке изображений: ' + data.error, 'error');
        }
    })
    .catch(() => {
        showNotification('Ошибка сети при загрузке изображений', 'error');
    });

    closeImageDialog();
}

function insertImagesInGrid(urls, layout) {
    let html = '';
    if (layout) {
        const [cols] = layout.split('x').map(Number);
        const className = `grid-container grid-${layout}`;
        

        html += `<div class="${className}" style="display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;">`;
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" style="width: 100%; height: auto;" class="blog-image">`);
        });
        html += `</div>`;
    } else {
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" class="blog-image">`);
        });
    }

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(html);
    }

    closeImageDialog();
}

function uploadImage(file, width, height, widthUnit, heightUnit, caption) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('width', width);
    formData.append('height', height || '');
    formData.append('widthUnit', widthUnit);
    formData.append('heightUnit', heightUnit || '');
    formData.append('caption', caption || '');

    fetch('upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            insertImage(data.url, width, height, widthUnit, heightUnit, caption);
        } else {
            showNotification('Ошибка при загрузке изображения: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Ошибка при загрузке изображения', 'error');
    });
}

function wrapImageWithHint(imgHtml) {
    const uniqueId = 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    return '<div class="blog-image-align-wrap" style="text-align:left" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap">' + imgHtml +
        '<div class="image-toolbar" contenteditable="false">' +
        '<button type="button" class="image-toolbar-btn" data-action="align" title="Выравнивание">⚏</button>' +
        '<button type="button" class="image-toolbar-btn" data-action="resize" title="Изменить размер">⇲</button>' +
        '<button type="button" class="image-toolbar-btn" data-action="delete" title="Удалить">🗑</button>' +
        '</div>' +
        '<div class="image-align-dropdown" contenteditable="false">' +
        '<button type="button" class="image-align-option" data-align="left"><span>◄</span> По левому краю</button>' +
        '<button type="button" class="image-align-option" data-align="center"><span>≡</span> По центру</button>' +
        '<button type="button" class="image-align-option" data-align="right"><span>►</span> По правому краю</button>' +
        '</div>' +
        '<div class="image-size-indicator" contenteditable="false"></div>' +
        '<div class="image-resize-handle bottom-right" contenteditable="false"></div>' +
        '<div class="image-resize-handle bottom-left" contenteditable="false"></div>' +
        '</div></div>';
}

function wrapExistingEditorImages() {
    var ve = document.getElementById('contentVisual');
    if (!ve || ve.style.display === 'none') return;
    var imgs = ve.querySelectorAll('img.blog-image, img[src]');
    for (var i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        var wrap = img.closest && img.closest('.blog-image-wrap');
        var alignWrap = img.closest && img.closest('.blog-image-align-wrap');
        var toolbar = wrap && wrap.querySelector('.image-toolbar');
        
        if (wrap && alignWrap && toolbar) continue;
        
        if (wrap && !toolbar) {
            // Удаляем старые элементы управления
            var oldOverlay = wrap.querySelector('.blog-image-overlay');
            if (oldOverlay) oldOverlay.parentNode.removeChild(oldOverlay);
            
            // Добавляем новую панель инструментов
            var uniqueId = 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            if (alignWrap) alignWrap.setAttribute('data-image-id', uniqueId);
            
            var toolbar = document.createElement('div');
            toolbar.className = 'image-toolbar';
            toolbar.setAttribute('contenteditable', 'false');
            toolbar.innerHTML = '<button type="button" class="image-toolbar-btn" data-action="align" title="Выравнивание">⚏</button>' +
                '<button type="button" class="image-toolbar-btn" data-action="resize" title="Изменить размер">⇲</button>' +
                '<button type="button" class="image-toolbar-btn" data-action="delete" title="Удалить">🗑</button>';
            
            var dropdown = document.createElement('div');
            dropdown.className = 'image-align-dropdown';
            dropdown.setAttribute('contenteditable', 'false');
            dropdown.innerHTML = '<button type="button" class="image-align-option" data-align="left"><span>◄</span> По левому краю</button>' +
                '<button type="button" class="image-align-option" data-align="center"><span>≡</span> По центру</button>' +
                '<button type="button" class="image-align-option" data-align="right"><span>►</span> По правому краю</button>';
            
            var sizeIndicator = document.createElement('div');
            sizeIndicator.className = 'image-size-indicator';
            sizeIndicator.setAttribute('contenteditable', 'false');
            
            var handleBR = document.createElement('div');
            handleBR.className = 'image-resize-handle bottom-right';
            handleBR.setAttribute('contenteditable', 'false');
            
            var handleBL = document.createElement('div');
            handleBL.className = 'image-resize-handle bottom-left';
            handleBL.setAttribute('contenteditable', 'false');
            
            wrap.appendChild(toolbar);
            wrap.appendChild(dropdown);
            wrap.appendChild(sizeIndicator);
            wrap.appendChild(handleBR);
            wrap.appendChild(handleBL);
        }
        
        if (wrap && !alignWrap) {
            var outer = wrap.parentNode;
            var alignDiv = document.createElement('div');
            alignDiv.className = 'blog-image-align-wrap';
            alignDiv.style.textAlign = 'left';
            alignDiv.setAttribute('data-image-id', 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
            outer.insertBefore(alignDiv, wrap);
            alignDiv.appendChild(wrap);
        }
        
        if (!wrap) {
            var uniqueId = 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            var alignDiv = document.createElement('div');
            alignDiv.className = 'blog-image-align-wrap';
            alignDiv.style.textAlign = 'left';
            alignDiv.setAttribute('data-image-id', uniqueId);
            
            var wrapSpan = document.createElement('div');
            wrapSpan.className = 'blog-image-wrap';
            
            var toolbar = document.createElement('div');
            toolbar.className = 'image-toolbar';
            toolbar.setAttribute('contenteditable', 'false');
            toolbar.innerHTML = '<button type="button" class="image-toolbar-btn" data-action="align" title="Выравнивание">⚏</button>' +
                '<button type="button" class="image-toolbar-btn" data-action="resize" title="Изменить размер">⇲</button>' +
                '<button type="button" class="image-toolbar-btn" data-action="delete" title="Удалить">🗑</button>';
            
            var dropdown = document.createElement('div');
            dropdown.className = 'image-align-dropdown';
            dropdown.setAttribute('contenteditable', 'false');
            dropdown.innerHTML = '<button type="button" class="image-align-option" data-align="left"><span>◄</span> По левому краю</button>' +
                '<button type="button" class="image-align-option" data-align="center"><span>≡</span> По центру</button>' +
                '<button type="button" class="image-align-option" data-align="right"><span>►</span> По правому краю</button>';
            
            var sizeIndicator = document.createElement('div');
            sizeIndicator.className = 'image-size-indicator';
            sizeIndicator.setAttribute('contenteditable', 'false');
            
            var handleBR = document.createElement('div');
            handleBR.className = 'image-resize-handle bottom-right';
            handleBR.setAttribute('contenteditable', 'false');
            
            var handleBL = document.createElement('div');
            handleBL.className = 'image-resize-handle bottom-left';
            handleBL.setAttribute('contenteditable', 'false');
            
            img.parentNode.insertBefore(alignDiv, img);
            alignDiv.appendChild(wrapSpan);
            wrapSpan.appendChild(img);
            wrapSpan.appendChild(toolbar);
            wrapSpan.appendChild(dropdown);
            wrapSpan.appendChild(sizeIndicator);
            wrapSpan.appendChild(handleBR);
            wrapSpan.appendChild(handleBL);
        }
    }
}

function initImageAlignmentHandlers() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    // Обработчик кликов по панели инструментов изображения
    ve.addEventListener('click', function(e) {
        var toolbarBtn = e.target.closest('.image-toolbar-btn');
        if (toolbarBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            var action = toolbarBtn.getAttribute('data-action');
            var wrap = toolbarBtn.closest('.blog-image-wrap');
            var alignWrap = toolbarBtn.closest('.blog-image-align-wrap');
            
            if (action === 'align') {
                // Переключаем выпадающее меню выравнивания
                var dropdown = wrap.querySelector('.image-align-dropdown');
                if (dropdown) {
                    var isOpen = dropdown.classList.contains('show');
                    // Закрываем все другие dropdown
                    ve.querySelectorAll('.image-align-dropdown.show').forEach(function(d) {
                        d.classList.remove('show');
                    });
                    if (!isOpen) {
                        dropdown.classList.add('show');
                        toolbarBtn.classList.add('active');
                    } else {
                        toolbarBtn.classList.remove('active');
                    }
                }
            } else if (action === 'resize') {
                // Открываем диалог изменения размера
                var img = wrap.querySelector('img');
                if (img) {
                    showImageResizeDialog(img, wrap, alignWrap);
                }
            } else if (action === 'delete') {
                // Удаляем изображение
                if (confirm('Удалить это изображение?')) {
                    if (alignWrap) {
                        alignWrap.parentNode.removeChild(alignWrap);
                    }
                }
            }
            return;
        }
        
        // Обработчик кликов по опциям выравнивания
        var alignOption = e.target.closest('.image-align-option');
        if (alignOption) {
            e.preventDefault();
            e.stopPropagation();
            
            var align = alignOption.getAttribute('data-align');
            var dropdown = alignOption.closest('.image-align-dropdown');
            var wrap = alignOption.closest('.blog-image-wrap');
            var alignWrap = alignOption.closest('.blog-image-align-wrap');
            
            if (alignWrap) {
                alignWrap.style.textAlign = align;
                
                // Обновляем активное состояние
                dropdown.querySelectorAll('.image-align-option').forEach(function(opt) {
                    opt.classList.remove('active');
                });
                alignOption.classList.add('active');
                
                // Закрываем dropdown
                dropdown.classList.remove('show');
                var alignBtn = wrap.querySelector('.image-toolbar-btn[data-action="align"]');
                if (alignBtn) alignBtn.classList.remove('active');
            }
            return;
        }
        
        // Закрываем dropdown при клике вне его
        if (!e.target.closest('.image-align-dropdown') && !e.target.closest('.image-toolbar-btn[data-action="align"]')) {
            ve.querySelectorAll('.image-align-dropdown.show').forEach(function(d) {
                d.classList.remove('show');
            });
            ve.querySelectorAll('.image-toolbar-btn[data-action="align"].active').forEach(function(btn) {
                btn.classList.remove('active');
            });
        }
    });
    
    // Обновление индикатора размера при наведении
    ve.addEventListener('mouseover', function(e) {
        var wrap = e.target.closest('.blog-image-wrap');
        if (wrap) {
            var img = wrap.querySelector('img');
            var indicator = wrap.querySelector('.image-size-indicator');
            if (img && indicator) {
                var width = img.offsetWidth || img.naturalWidth;
                var height = img.offsetHeight || img.naturalHeight;
                indicator.textContent = width + ' × ' + height + ' px';
            }
        }
    });
    
    // Обработчик изменения размера изображения
    initImageResizeHandlers();
}

function initImageResizeHandlers() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    var isResizing = false;
    var currentHandle = null;
    var currentImg = null;
    var startX, startY, startWidth, startHeight;
    
    ve.addEventListener('mousedown', function(e) {
        var handle = e.target.closest('.image-resize-handle');
        if (!handle) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        isResizing = true;
        currentHandle = handle;
        var wrap = handle.closest('.blog-image-wrap');
        currentImg = wrap ? wrap.querySelector('img') : null;
        
        if (!currentImg) return;
        
        startX = e.clientX;
        startY = e.clientY;
        startWidth = currentImg.offsetWidth;
        startHeight = currentImg.offsetHeight;
        
        wrap.classList.add('selected');
        document.body.style.cursor = handle.classList.contains('bottom-right') ? 'nwse-resize' : 'nesw-resize';
    });
    
    document.addEventListener('mousemove', function(e) {
        if (!isResizing || !currentImg) return;
        
        e.preventDefault();
        
        var deltaX = e.clientX - startX;
        var deltaY = e.clientY - startY;
        
        if (currentHandle.classList.contains('bottom-left')) {
            deltaX = -deltaX;
        }
        
        var aspectRatio = startHeight / startWidth;
        var newWidth = startWidth + deltaX;
        var newHeight = newWidth * aspectRatio;
        
        if (newWidth > 50 && newWidth < 2000) {
            currentImg.style.width = newWidth + 'px';
            currentImg.style.height = 'auto';
        }
    });
    
    document.addEventListener('mouseup', function(e) {
        if (isResizing) {
            isResizing = false;
            document.body.style.cursor = '';
            
            if (currentImg) {
                var wrap = currentImg.closest('.blog-image-wrap');
                if (wrap) wrap.classList.remove('selected');
            }
            
            currentHandle = null;
            currentImg = null;
        }
    });
}

function showImageResizeDialog(img, wrap, alignWrap) {
    var currentWidth = img.offsetWidth || img.naturalWidth;
    var currentHeight = img.offsetHeight || img.naturalHeight;
    
    var newWidth = prompt('Введите новую ширину изображения (в пикселях):', currentWidth);
    if (newWidth && !isNaN(newWidth) && newWidth > 0) {
        img.style.width = newWidth + 'px';
        img.style.height = 'auto';
    }
}

initImageAlignmentHandlers();

(function preventEnterInsideImageBlock() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    ve.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' || editorMode !== 'visual') return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var node = sel.anchorNode;
        if (!node || !ve.contains(node)) return;
        var alignWrap = node.nodeType === Node.ELEMENT_NODE ? node.closest('.blog-image-align-wrap') : (node.parentElement && node.parentElement.closest('.blog-image-align-wrap'));
        if (!alignWrap) return;
        e.preventDefault();
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        var next = alignWrap.nextSibling;
        var parent = alignWrap.parentNode;
        if (next) parent.insertBefore(emptyDiv, next);
        else parent.appendChild(emptyDiv);
        var range = document.createRange();
        range.setStart(emptyDiv, 0);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        if (typeof savedRange !== 'undefined') savedRange = range.cloneRange();
    });
})();

(function initEditorContextMenu() {
    var menu = document.getElementById('editorContextMenu');
    var contentVisual = document.getElementById('contentVisual');
    var contentTa = document.getElementById('content');
    var contextMenuImageTarget = null;
    if (!menu || !contentVisual) return;

    function hideMenu() {
        menu.classList.remove('is-open');
        contextMenuImageTarget = null;
    }
    function showMenu(x, y) {
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.classList.add('is-open');
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var w = window.innerWidth;
            var h = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > w - 8) left = w - rect.width - 8;
            if (top + rect.height > h - 8) top = h - rect.height - 8;
            if (left < 8) left = 8;
            if (top < 8) top = 8;
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        });
    }

    function onContextMenu(e) {
        var inEditor = e.target === contentVisual || contentVisual.contains(e.target) ||
                       e.target === contentTa || contentTa.contains(e.target);
        if (!inEditor) return;
        e.preventDefault();
        e.stopPropagation();
        contextMenuImageTarget = null;
        if (editorMode === 'visual' && contentVisual.contains(e.target)) {
            var alignWrap = e.target.closest && e.target.closest('.blog-image-align-wrap');
            var imgWrap = e.target.closest && e.target.closest('.blog-image-wrap');
            var img = e.target.tagName === 'IMG' ? e.target : null;
            if (alignWrap) contextMenuImageTarget = alignWrap;
            else if (imgWrap) contextMenuImageTarget = imgWrap;
            else if (img && img.parentNode) contextMenuImageTarget = img.parentNode;
        }
        saveSelection();
        if (editorMode === 'code' && contentTa) {
            colorInsertStart = contentTa.selectionStart;
            colorInsertEnd = contentTa.selectionEnd;
        }
        showMenu(e.clientX, e.clientY);
    }

    contentVisual.addEventListener('contextmenu', onContextMenu);
    if (contentTa) contentTa.addEventListener('contextmenu', onContextMenu);

    // Обработчик для обеспечения возможности редактирования после spoiler блоков
    contentVisual.addEventListener('click', function(e) {
        // Проверяем, кликнули ли мы на spoiler блок или рядом с ним
        const ve = document.getElementById('contentVisual');
        const spoilers = ve.querySelectorAll('.spoiler-block');
        
        spoilers.forEach(function(spoiler) {
            // Проверяем, есть ли после spoiler следующий элемент
            if (!spoiler.nextSibling || (spoiler.nextSibling.nodeType === Node.TEXT_NODE && spoiler.nextSibling.textContent.trim() === '')) {
                // Если нет следующего элемента или это пустой текстовый узел, создаем div
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                if (spoiler.nextSibling) {
                    spoiler.parentNode.insertBefore(emptyDiv, spoiler.nextSibling);
                } else {
                    spoiler.parentNode.appendChild(emptyDiv);
                }
            }
        });
    });

    // Обработчик для клавиш - создаем пустой блок при нажатии Enter в конце spoiler
    contentVisual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                let node = range.startContainer;
                
                // Ищем родительский spoiler-block
                while (node && node !== contentVisual) {
                    if (node.classList && node.classList.contains('spoiler-block')) {
                        // Проверяем, находимся ли мы в конце spoiler
                        const spoilerContent = node.querySelector('.spoiler-content');
                        if (spoilerContent && spoilerContent.contains(range.startContainer)) {
                            // Проверяем, есть ли после spoiler элемент
                            if (!node.nextSibling || (node.nextSibling.nodeType === Node.TEXT_NODE && node.nextSibling.textContent.trim() === '')) {
                                e.preventDefault();
                                const emptyDiv = document.createElement('div');
                                emptyDiv.innerHTML = '<br>';
                                node.parentNode.insertBefore(emptyDiv, node.nextSibling);
                                
                                // Устанавливаем курсор в новый блок
                                const newRange = document.createRange();
                                newRange.setStart(emptyDiv, 0);
                                newRange.collapse(true);
                                sel.removeAllRanges();
                                sel.addRange(newRange);
                                return;
                            }
                        }
                        break;
                    }
                    node = node.parentNode;
                }
            }
        }
    });

    menu.addEventListener('click', function(e) {
        var item = e.target.closest('.editor-context-item');
        if (!item || !item.dataset.cmd) return;
        e.preventDefault();
        e.stopPropagation();
        var cmd = item.dataset.cmd;
        if (cmd === 'paste' || cmd === 'copy' || cmd === 'cut' || cmd === 'delete') {
            if (cmd === 'delete' && contextMenuImageTarget && contextMenuImageTarget.parentNode) {
                contextMenuImageTarget.parentNode.removeChild(contextMenuImageTarget);
                contextMenuImageTarget = null;
            } else if (editorMode === 'visual') {
                contentVisual.focus();
                document.execCommand(cmd, false, null);
            } else {
                if (cmd === 'copy') document.execCommand('copy');
                if (cmd === 'cut') document.execCommand('cut');
                if (cmd === 'paste') document.execCommand('paste');
                if (cmd === 'delete' && contentTa) {
                    var start = colorInsertStart;
                    var end = colorInsertEnd;
                    contentTa.value = contentTa.value.substring(0, start) + contentTa.value.substring(end);
                    contentTa.focus();
                }
            }
        } else if (cmd === 'link') {
            addLink();
        } else if (cmd === 'image') {
            showImageUpload();
        }
        hideMenu();
    });

    document.addEventListener('click', hideMenu);
    document.addEventListener('contextmenu', function(e) {
        if (!menu.contains(e.target)) hideMenu();
    });
})();

function insertImage(url, width, height, widthUnit, heightUnit, caption = '') {
    const imgStyle = `width: ${width}${widthUnit}; ` + 
                    (height ? `height: ${height}${heightUnit};` : '');
    const imgTag = wrapImageWithHint(`<img src="${url}" style="${imgStyle}" class="blog-image">`) + 
                  (caption ? `<span class="caption">${caption}</span>` : '');
    
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + imgTag + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(imgTag);
    }
    closeImageDialog();
}

function closeImageDialog() {
    document.getElementById('imageUploadDialog').style.display = 'none';
    document.getElementById('imageFile').value = '';
    document.getElementById('imageUrl').value = '';
    document.getElementById('imageCaption').value = '';
    document.getElementById('customWidth').value = '';
    document.getElementById('customHeight').value = '';
    document.querySelector('input[name="imageSource"][value="file"]').checked = true;
    document.getElementById('fileUploadContainer').style.display = 'block';
    document.getElementById('urlContainer').style.display = 'none';
}

    // Функции для работы с размером шрифта
    function setFontSize(size) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var fontSpan = '<span style="font-size: ' + size + 'px;">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
                ta.focus();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="font-size: ' + size + 'px;">' + text + '</span>';
                insertHtmlAtCaret(html);
            }
        }
    }

    function closeFontSizeDialog() {
        document.getElementById('fontSizeDialog').style.display = 'none';
        document.getElementById('customFontSize').value = '';
    }

    function setCustomFontSize() {
        const size = document.getElementById('customFontSize').value;
        if (size && size >= 8 && size <= 72) {
            setFontSize(size);
            closeFontSizeDialog();
        } else {
            showNotification('Пожалуйста, введите размер от 8 до 72 пикселей', 'warning');
        }
    }

    // Функции для работы с медиа
    function showMediaDialog() {
        saveSelection();
        document.getElementById('mediaDialog').style.display = 'block';
    }

    function closeMediaDialog() {
        document.getElementById('mediaDialog').style.display = 'none';
        document.getElementById('mediaUrl').value = '';
    }

    function insertMedia() {
    const url = document.getElementById('mediaUrl').value.trim();
    if (!url) {
        showNotification('Пожалуйста, введите URL медиа', 'warning');
        return;
    }

    let embedCode = '';

    // Определяем тип медиа по URL
    if (url.includes('youtube.com') || url.includes('youtu.be')) {
        const youtubeId = extractYoutubeId(url);
        embedCode = `<iframe width="560" height="315" src="https://www.youtube.com/embed/${youtubeId}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    } else if (url.includes('vimeo.com')) {
        const vimeoId = extractVimeoId(url);
        embedCode = `<iframe width="560" height="315" src="https://player.vimeo.com/video/${vimeoId}" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>`;
    } else if (url.match(/\.(mp3|wav|ogg)$/i)) {
        embedCode = `<audio controls><source src="${url}">Ваш браузер не поддерживает аудио элемент.</audio>`;
    } else {
        // Встраиваем как iframe, безопасный атрибут sandbox и allow
        embedCode = `<iframe width="560" height="315" src="${url}" frameborder="0" sandbox="allow-same-origin allow-scripts allow-popups" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    }

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + embedCode + ta.value.substring(cursorPos);
    } else {
        insertHtmlAtCaret(embedCode);
    }

    closeMediaDialog();
}

// Вспомогательные функции для извлечения ID
function extractYoutubeId(url) {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
}

function extractVimeoId(url) {
    const regExp = /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:$|\/|\?)/;
    const match = url.match(regExp);
    return match ? match[3] : null;
}

    // Функции для работы со spoiler
    // Переменная для хранения выделенного текста для spoiler
    let savedSpoilerText = '';
    let savedSpoilerRange = null;

    function openSpoilerDialog() {
        savedSpoilerText = '';
        savedSpoilerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedSpoilerRange = range.cloneRange();
                const container = document.createElement('div');
                container.appendChild(range.cloneContents());
                savedSpoilerText = container.innerHTML;
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedSpoilerText = ta.value.substring(start, end);
        }
        
        document.getElementById('spoilerDialog').style.display = 'block';
        document.getElementById('spoilerTitle').value = '';
        document.getElementById('spoilerTitle').focus();
    }

    function closeSpoilerDialog() {
        document.getElementById('spoilerDialog').style.display = 'none';
        savedSpoilerText = '';
        savedSpoilerRange = null;
    }

    function insertSpoiler() {
        const title = document.getElementById('spoilerTitle').value.trim() || 'Подробности';
        
        let selectedText = savedSpoilerText || 'Содержимое блока';
        
        const spoilerHtml = `<details class="spoiler-block"><summary class="spoiler-title">${title}</summary><div class="spoiler-content">${selectedText}</div></details>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + spoilerHtml + '\n' + after;
        } else {
            // Восстанавливаем сохраненный range если есть
            if (savedSpoilerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedSpoilerRange);
                savedSpoilerRange.deleteContents();
            }
            insertImageBlockAtCaret(spoilerHtml);
        }
        
        closeSpoilerDialog();
    }

    // Функции для работы с маркером
    let savedMarkerText = '';
    let savedMarkerRange = null;
    let selectedMarkerStyle = 'straight';

    function openMarkerDialog() {
        savedMarkerText = '';
        savedMarkerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedMarkerRange = range.cloneRange();
                savedMarkerText = range.toString();
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedMarkerText = ta.value.substring(start, end);
        }
        
        if (!savedMarkerText) {
            showNotification('Выделите текст для применения маркера', 'warning');
            return;
        }
        
        document.getElementById('markerDialog').style.display = 'block';
        
        // Добавляем обработчики на кнопки стилей
        const styleBtns = document.querySelectorAll('.marker-style-btn');
        styleBtns.forEach(btn => {
            btn.onclick = function() {
                styleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedMarkerStyle = this.getAttribute('data-style');
            };
        });
        
        // Добавляем обработчики на кнопки цветов
        const colorBtns = document.querySelectorAll('.marker-color-btn');
        colorBtns.forEach(btn => {
            btn.onclick = function() {
                const color = this.getAttribute('data-color');
                insertMarker(color, selectedMarkerStyle);
            };
        });
    }

    function closeMarkerDialog() {
        document.getElementById('markerDialog').style.display = 'none';
        savedMarkerText = '';
        savedMarkerRange = null;
    }

    function insertMarker(color, style) {
        if (!savedMarkerText) {
            closeMarkerDialog();
            return;
        }
        
        // Определяем название цвета для data-атрибута
        const colorNames = {
            '#ffeb3b': 'yellow',
            '#4caf50': 'green',
            '#2196f3': 'blue',
            '#ff9800': 'orange',
            '#e91e63': 'pink',
            '#9c27b0': 'purple'
        };
        const colorName = colorNames[color] || 'yellow';
        
        const markerHtml = `<mark data-marker-color="${colorName}" data-marker-style="${style}">${savedMarkerText}</mark>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + markerHtml + after;
            // Устанавливаем курсор после маркера
            const newPos = start + markerHtml.length;
            ta.setSelectionRange(newPos, newPos);
            ta.focus();
        } else {
            if (savedMarkerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedMarkerRange);
                savedMarkerRange.deleteContents();
                
                const temp = document.createElement('div');
                temp.innerHTML = markerHtml;
                const frag = document.createDocumentFragment();
                let node, lastNode;
                while ((node = temp.firstChild)) {
                    lastNode = frag.appendChild(node);
                }
                savedMarkerRange.insertNode(frag);
                
                // Устанавливаем курсор после маркера
                if (lastNode) {
                    const newRange = document.createRange();
                    newRange.setStartAfter(lastNode);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                    
                    // Добавляем пробел после маркера чтобы выйти из форматирования
                    const space = document.createTextNode('\u200B'); // Zero-width space
                    newRange.insertNode(space);
                    newRange.setStartAfter(space);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                }
            }
        }
        
        closeMarkerDialog();
    }

    // Функции для работы с кодом
    function insertCode() {
        saveSelection();
        document.getElementById('codeDialog').style.display = 'block';
    }

    function closeCodeDialog() {
        document.getElementById('codeDialog').style.display = 'none';
        document.getElementById('codeInput').value = '';
    }

    function insertCodeBlock() {
        const code = document.getElementById('codeInput').value;
        const language = document.getElementById('codeLanguage').value;
        
        if (code.trim() === '') {
            showNotification('Пожалуйста, введите код', 'warning');
            return;
        }

        const escapedCode = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        const codeBlock = `<pre class="code-block" data-language="${language}">${escapedCode}</pre>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + codeBlock + ta.value.substring(cursorPos);
        } else {
            insertHtmlAtCaret(codeBlock);
        }
        
        closeCodeDialog();
    }

    // Функции для управления статьями
    function toggleManagePosts() {
        const managePanel = document.getElementById('managePosts');
        managePanel.classList.toggle('active');
        
        if (managePanel.classList.contains('active')) {
            loadPosts();
        }
    }

    function loadPosts() {
        // Добавляем timestamp для предотвращения кэширования
        fetch('data/blog/posts-meta.json?t=' + Date.now())
            .then(response => response.json())
            .then(posts => {
                const postsList = document.getElementById('postsList');
                if (!posts || posts.length === 0) {
                    postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
                    return;
                }
                const escapeHtml = function(str) {
                    if (!str) return '';
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                };
                
                // Сортируем статьи по ID в обратном порядке (новые первыми)
                const sortedPosts = [...posts].sort((a, b) => b.id - a.id);
                
                postsList.innerHTML = '<ul class="post-list">' +
                    sortedPosts.map(post => `
                        <li class="post-item">
                            <div class="post-item-title">${escapeHtml(post.title)}</div>
                            <span class="post-item-date">${escapeHtml(post.date)}</span>
                            <div class="post-item-actions">
                                <button type="button" class="edit-btn" onclick="editPost(${post.id})">Изменить</button>
                                <button type="button" class="additional-btn" onclick="openAdditionalSettings(${post.id}, '${escapeHtml(post.title)}')">Дополнительно</button>
                                <button type="button" class="delete-btn" onclick="deletePost(${post.id})">Удалить</button>
                            </div>
                        </li>
                    `).join('') +
                    '</ul>';
            })
            .catch(error => {
                console.error('Ошибка загрузки статей:', error);
                const postsList = document.getElementById('postsList');
                postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
            });
    }

    function editPost(postId) {
        fetch('get_post_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: postId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('title').value = data.title;

                let editedContent = data.content;
                document.getElementById('content').value = editedContent;
                const ve = document.getElementById('contentVisual');
                if (editorMode === 'visual' && ve) {
                    ve.innerHTML = editedContent;
                    wrapExistingEditorImages();
                    
                    // Убеждаемся что блоки кода имеют правильную высоту
                    setTimeout(() => {
                        const codeBlocks = ve.querySelectorAll('.code-block');
                        codeBlocks.forEach(block => {
                            if (block.scrollHeight > 400) {
                                block.style.maxHeight = '400px';
                            } else {
                                block.style.maxHeight = 'none';
                            }
                        });
                    }, 100);
                }
                currentEditId = postId;
                const submitButton = document.getElementById('submitButton');
                submitButton.textContent = 'Сохранить изменения';
                submitButton.classList.add('editing');
                toggleManagePosts();
                document.getElementById('blogForm').scrollIntoView();
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки статьи:', error);
            showNotification('Ошибка при загрузке статьи', 'error');
        });
    }

    let deletePostId = null;

    function deletePost(postId) {
        deletePostId = postId;
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.add('show');
    }
    
    function closeDeleteConfirm() {
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.remove('show');
        deletePostId = null;
    }
    
    function confirmDelete() {
        if (!deletePostId) return;
        
        fetch('delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: deletePostId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.renumbered 
                    ? 'Статья удалена, нумерация обновлена' 
                    : 'Статья успешно удалена';
                showNotification(message, 'success');
                loadPosts();
                closeDeleteConfirm();
            } else {
                showNotification('Ошибка при удалении статьи', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка удаления:', error);
            showNotification('Ошибка при удалении статьи', 'error');
        });
    }

    // Обработчик отправки формы
    document.getElementById('modeVisualBtn').addEventListener('click', function(){ setMode('visual'); });
    document.getElementById('modeCodeBtn').addEventListener('click', function(){ setMode('code'); });
    setMode('visual');

    function handleSubmit(e) {
        if (e) e.preventDefault();
        const title = document.getElementById('title').value;
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        
        let content;
        if (editorMode === 'visual') {
            // Очищаем контент от элементов интерфейса редактора
            content = cleanContentForSave(ve.innerHTML);
            ta.value = content;
        } else {
            content = ta.value;
        }
        
        const endpoint = currentEditId ? 'update_post.php' : 'save_post.php';
        const data = { title: title, content: content };
        if (currentEditId) { data.id = currentEditId; }
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(async (response) => {
            let payload;
            try { payload = await response.json(); } catch (_) { payload = null; }
            if (!response.ok || (payload && payload.success === false)) {
                throw new Error((payload && payload.error) || 'Server error');
            }
            showNotification(
                currentEditId ? 'Статья успешно обновлена!' : 'Статья успешно добавлена!',
                'success'
            );
            
            // Очищаем форму
            document.getElementById('blogForm').reset();
            
            // Очищаем визуальный редактор
            const ve = document.getElementById('contentVisual');
            if (ve) {
                ve.innerHTML = '';
            }
            
            // Очищаем текстовое поле
            const ta = document.getElementById('content');
            if (ta) {
                ta.value = '';
            }
            
            // Обновляем список статей
            loadPosts();
            
            currentEditId = null;
            const submitButton = document.getElementById('submitButton');
            submitButton.textContent = 'Опубликовать';
            submitButton.classList.remove('editing');
        })
        .catch(() => {
            showNotification('Ошибка при сохранении статьи', 'error');
        });
    }

    document.getElementById('blogForm').addEventListener('submit', handleSubmit);
    document.getElementById('submitButton').addEventListener('click', handleSubmit);

    // Обработчики изменения размера
    document.getElementById('imageSize').addEventListener('change', function(e) {
        const customInputs = document.getElementById('customSizeInputs');
        customInputs.style.display = e.target.value === 'custom' ? 'flex' : 'none';
        
        if (e.target.value !== 'custom') {
            document.getElementById('customWidth').value = '';
            document.getElementById('customHeight').value = '';
            document.getElementById('widthUnit').value = 'px';
            document.getElementById('heightUnit').value = 'px';
        }
    });

    document.getElementById('customFontSize').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            setCustomFontSize();
        }
    });
    function setTextColor(color) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var colorSpan = '<span style="color: ' + color + ';">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + colorSpan + ta.value.substring(end);
                ta.focus();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="color: ' + color + ';">' + text + '</span>';
                insertHtmlAtCaret(html);
            }
        }
    }

    (function initColorPalette() {
        var presetColors = ['#000000','#333333','#666666','#999999','#cccccc','#ffffff','#ff0000','#ff6600','#ff9900','#ffcc00','#99cc00','#00cc00','#00cccc','#0066ff','#0000ff','#6600cc','#9900cc','#cc0099','#ff0066','#8b4513','#a0522d','#cd853f','#deb887','#ff69b4','#ffc0cb','#add8e6','#98fb98','#f0e68c','#ffd700','#ff6347'];
        function fillGrid(gridId) {
            var grid = document.getElementById(gridId);
            if (!grid) return;
            grid.innerHTML = '';
            presetColors.forEach(function(hex) {
                var swatch = document.createElement('span');
                swatch.className = 'color-swatch';
                swatch.style.background = hex;
                swatch.title = hex;
                swatch.setAttribute('data-color', hex);
                grid.appendChild(swatch);
            });
        }
        fillGrid('colorPaletteGridMain');

        function openColorPicker(wrap) {
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            wrap.classList.add('is-open');
        }
        function toggleColorPicker(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function closeAllColorPickers() {
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function applyColorAndClose(hex, wrap) {
            setTextColor(hex);
            wrap.classList.remove('is-open');
            var preview = wrap.querySelector('.color-preview');
            if (preview) preview.style.background = hex;
        }
        
        // Функция для меню "Прочее"
        window.toggleMoreMenu = function() {
            const wrap = document.getElementById('moreMenuWrap');
            if (!wrap) return;
            
            const isOpen = wrap.classList.contains('is-open');
            
            // Закрываем другие открытые меню
            document.querySelectorAll('.color-picker-wrap.is-open, .font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) {
                w.classList.remove('is-open');
            });
            
            if (!isOpen) {
                wrap.classList.add('is-open');
            } else {
                wrap.classList.remove('is-open');
                // Закрываем подменю
                document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                    item.classList.remove('submenu-open');
                });
            }
        };

        ['colorPickerWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.color-picker-btn');
            var popover = wrap.querySelector('.color-palette-popover');
            var customInput = wrap.querySelector('input[type="color"]');
            if (btn) {
                btn.addEventListener('mousedown', function(e) {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleColorPicker(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var swatch = e.target.closest('.color-swatch');
                    if (swatch && swatch.dataset.color) applyColorAndClose(swatch.dataset.color, wrap);
                });
            }
            if (customInput) customInput.addEventListener('change', function() {
                applyColorAndClose(this.value, wrap);
            });
        });
        document.addEventListener('click', closeAllColorPickers);
    })();

    function applyCustomFontSize(wrapId) {
        var wrap = document.getElementById(wrapId);
        var input = wrap.querySelector('.font-size-custom input[type="number"]');
        var size = input && input.value ? parseInt(input.value, 10) : 0;
        if (size >= 8 && size <= 72) {
            setFontSize(String(size));
            input.value = '';
            wrap.classList.remove('is-open');
        } else {
            showNotification('Введите размер от 8 до 72', 'warning');
        }
    }
    function applyCustomFontFamily(wrapId) {
        var wrap = document.getElementById(wrapId);
        var input = wrap.querySelector('.font-family-custom input[type="text"]');
        var font = input && input.value ? input.value.trim() : '';
        if (font) {
            setFontFamily(font);
            input.value = '';
            wrap.classList.remove('is-open');
        } else {
            showNotification('Введите название шрифта', 'warning');
        }
    }

    (function initFontSizeAndFamilyPopovers() {
        function closeAllFontPopovers() {
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function toggleWrap(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function openWrap(wrap, closeOthers) {
            if (closeOthers) {
                document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            }
            wrap.classList.add('is-open');
        }
        
        // Закрытие при клике вне меню
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.more-menu-wrap')) {
                const moreMenu = document.getElementById('moreMenuWrap');
                if (moreMenu) {
                    moreMenu.classList.remove('is-open');
                    // Закрываем подменю
                    document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                        item.classList.remove('submenu-open');
                    });
                }
            }
        });
        
        ['fontSizeWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-size-picker-btn');
            var popover = wrap.querySelector('.font-size-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var item = e.target.closest('.font-size-item[data-size]');
                    if (item) {
                        setFontSize(item.getAttribute('data-size'));
                        wrap.classList.remove('is-open');
                    }
                });
            }
        });
        ['fontFamilyWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-family-picker-btn');
            var popover = wrap.querySelector('.font-family-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var item = e.target.closest('.font-family-item[data-font]');
                    if (item) {
                        setFontFamily(item.getAttribute('data-font'));
                        wrap.classList.remove('is-open');
                    }
                });
            }
        });
        document.addEventListener('click', closeAllFontPopovers);
    })();

// Функции для работы со шрифтом
    function setFontFamily(font) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
                ta.focus();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">' + text + '</span>';
                insertHtmlAtCaret(html);
            }
        }
    }

function closeFontFamilyDialog() {
    document.getElementById('fontFamilyDialog').style.display = 'none';
    document.getElementById('customFontFamily').value = '';
}

function setCustomFontFamily() {
    const font = document.getElementById('customFontFamily').value.trim();
    if (font) {
        setFontFamily(font);
        closeFontFamilyDialog();
    } else {
        showNotification('Пожалуйста, введите название шрифта', 'warning');
    }
}

    function insertImageGrid(layout) {
    const [cols, rows] = layout.split('x').map(Number);
    const gridStyle = `display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;`;
    let imagesHTML = '';

    for (let i = 0; i < cols * rows; i++) {
        // Плейсхолдер для добавления реальных изображений
        imagesHTML += `<img src="" alt="Изображение ${i+1}" style="width: 100%; height: auto;">`;
    }

    const gridHTML = `<div style="${gridStyle}">${imagesHTML}</div>`;

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + gridHTML + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(gridHTML);
    }
}

function insertImagesInGrid(urls, width, unit, layout) {
    let html = '';
    if (layout) {
        const [cols, rows] = layout.split('x').map(Number);
        html += `<div class="grid-container grid-${layout}" style="display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;">`;
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" style="width: 100%; height: auto;" class="blog-image">`);
        });
        html += `</div>`;
    } else {
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" style="width: ${width}${unit}; height: auto; margin: 10px 0;" class="blog-image">`);
        });
    }

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(html);
    }
}

// Прилипающая строка кнопок: при прокрутке только панель форматирования фиксируется сверху
(function() {
    var sentinel = document.getElementById('formatBarSentinel');
    var placeholder = document.getElementById('formatBarPlaceholder');
    var formatBar = document.getElementById('formatBarRow');
    if (!sentinel || !placeholder || !formatBar) return;
    var stickyObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                formatBar.classList.remove('is-floating');
                placeholder.style.display = 'none';
            } else {
                var h = formatBar.offsetHeight;
                placeholder.style.height = h + 'px';
                placeholder.style.display = 'block';
                formatBar.classList.add('is-floating');
            }
        });
    }, { root: null, rootMargin: '0px', threshold: 0 });
    stickyObserver.observe(sentinel);
})();

// Подсветка активных кнопок при изменении выделения
document.addEventListener('selectionchange', function() {
    if (editorMode === 'visual') saveSelection();
    updateActiveButtons();
});

// ——— Проверка целостности файлов при загрузке ———
async function checkIntegrity() {
    try {
        const response = await fetch('check_integrity.php');
        const data = await response.json();
        
        if (!data.success && data.errors.length > 0) {
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.add('show');
        }
    } catch (error) {
        console.error('Ошибка проверки целостности:', error);
    }
}

async function fixIntegrityErrors() {
    const button = document.querySelector('.integrity-error-button');
    button.textContent = 'Исправление...';
    button.disabled = true;
    
    try {
        const response = await fetch('fix_integrity.php');
        const data = await response.json();
        
        if (data.success) {
            showNotification('Все ошибки успешно исправлены!', 'success');
            
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.remove('show');
            
            button.textContent = 'Исправить';
            button.disabled = false;
        } else {
            showNotification('Не удалось исправить некоторые ошибки: ' + data.errors.join(', '), 'error');
            button.textContent = 'Исправить';
            button.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления:', error);
        showNotification('Ошибка при исправлении файлов', 'error');
        button.textContent = 'Исправить';
        button.disabled = false;
    }
}

// Запускаем проверку при загрузке страницы
window.addEventListener('load', checkIntegrity);

// ——— Менеджер бэкапов ———
async function openBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    const content = document.getElementById('backupManagerContent');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="backup-empty">Загрузка...</div>';
    
    try {
        const response = await fetch('get_backups.php');
        const data = await response.json();
        
        if (data.success) {
            if (Object.keys(data.backups).length === 0) {
                content.innerHTML = '<div class="backup-empty">Нет сохраненных бэкапов</div>';
            } else {
                renderBackups(data.backups);
            }
        } else {
            content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
        }
    } catch (error) {
        console.error('Ошибка загрузки бэкапов:', error);
        content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
    }
}

function closeBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    overlay.classList.remove('show');
}

function renderBackups(backups) {
    const content = document.getElementById('backupManagerContent');
    let html = '';
    
    for (const postId in backups) {
        const post = backups[postId];
        const isDeleted = post.deleted === true;
        const displayTitle = isDeleted 
            ? `🗑️ ${escapeHtml(post.postTitle)}` 
            : `Статья #${postId}: ${escapeHtml(post.postTitle)}`;
        
        html += `
            <div class="backup-post-group ${isDeleted ? 'deleted-post' : ''}" id="backup-group-${postId}">
                <div class="backup-post-header" onclick="toggleBackupGroup('${postId}')">
                    <h3 class="backup-post-title">${displayTitle}</h3>
                    <span class="backup-post-toggle">▼</span>
                </div>
                <div class="backup-list">
                    ${post.backups.map((backup, index) => `
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="backup-number">Бэкап #${backup.backupNumber}</div>
                                <div class="backup-date">${escapeHtml(backup.date)}</div>
                                ${isDeleted ? '<div class="backup-date" style="color: #dc3545; font-weight: 600;">Статья удалена: ' + escapeHtml(post.deletedAt || '') + '</div>' : ''}
                            </div>
                            <div class="backup-actions">
                                <button class="backup-btn" onclick="viewBackup('${postId}', '${backup.filename}')">Посмотреть</button>
                                ${!isDeleted ? `<button class="backup-btn" onclick="restoreBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">Восстановить</button>` : ''}
                                <button class="backup-btn" onclick="openDeleteBackup('${postId}', '${backup.filename}', ${backup.backupNumber})">Удалить</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
}

function toggleBackupGroup(postId) {
    const group = document.getElementById('backup-group-' + postId);
    if (group) {
        group.classList.toggle('expanded');
    }
}

async function viewBackup(postId, filename) {
    try {
        const response = await fetch('get_backup_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ postId: postId, filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Открываем в новом окне
            const newWindow = window.open('', '_blank');
            newWindow.document.write(data.content);
            newWindow.document.close();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка просмотра бэкапа:', error);
        showNotification('Ошибка при просмотре бэкапа', 'error');
    }
}

// Восстановление бэкапа
let restoreBackupData = null;

function restoreBackup(postId, filename, backupNumber, backupDate) {
    restoreBackupData = { postId, filename };
    
    const overlay = document.getElementById('restoreBackupOverlay');
    const infoDiv = document.getElementById('restoreBackupInfo');
    
    // Заполняем информацию о бэкапе
    infoDiv.innerHTML = `
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Статья:</span>
            <span class="restore-backup-info-value">#${postId}</span>
        </div>
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Бэкап:</span>
            <span class="restore-backup-info-value">#${backupNumber}</span>
        </div>
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Дата создания:</span>
            <span class="restore-backup-info-value">${backupDate}</span>
        </div>
    `;
    
    overlay.classList.add('show');
}

function closeRestoreBackup() {
    const overlay = document.getElementById('restoreBackupOverlay');
    overlay.classList.remove('show');
    restoreBackupData = null;
}

async function confirmRestoreBackup() {
    if (!restoreBackupData) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Восстановление...';
    
    try {
        const response = await fetch('restore_backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({
                postId: restoreBackupData.postId,
                filename: restoreBackupData.filename
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Бэкап успешно восстановлен', 'success');
            closeRestoreBackup();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
            btn.disabled = false;
            btn.textContent = 'Восстановить';
        }
    } catch (error) {
        console.error('Ошибка восстановления бэкапа:', error);
        showNotification('Ошибка при восстановлении бэкапа', 'error');
        btn.disabled = false;
        btn.textContent = 'Восстановить';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Удаление бэкапа
let deleteBackupData = null;

function openDeleteBackup(postId, filename, backupNumber) {
    deleteBackupData = { postId, filename, backupNumber };
    
    const overlay = document.getElementById('deleteBackupOverlay');
    const input = document.getElementById('deleteBackupConfirmInput');
    const btn = document.getElementById('confirmDeleteBackupBtn');
    
    input.value = '';
    btn.disabled = true;
    
    overlay.classList.add('show');
    
    setTimeout(() => input.focus(), 100);
}

function closeDeleteBackup() {
    const overlay = document.getElementById('deleteBackupOverlay');
    overlay.classList.remove('show');
    deleteBackupData = null;
}

// Проверка ввода для активации кнопки удаления
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('deleteBackupConfirmInput');
    const btn = document.getElementById('confirmDeleteBackupBtn');
    
    if (input && btn) {
        input.addEventListener('input', function() {
            btn.disabled = input.value.trim() !== 'УДАЛИТЬ';
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && input.value.trim() === 'УДАЛИТЬ') {
                openFinalDeleteConfirm();
            }
        });
    }
    
    // Проверка чекбокса для финального подтверждения
    const checkbox = document.getElementById('finalDeleteCheckbox');
    const finalBtn = document.getElementById('finalDeleteBtn');
    
    if (checkbox && finalBtn) {
        checkbox.addEventListener('change', function() {
            finalBtn.disabled = !checkbox.checked;
        });
    }
});

function openFinalDeleteConfirm() {
    // Закрываем первое окно
    const firstOverlay = document.getElementById('deleteBackupOverlay');
    firstOverlay.classList.remove('show');
    
    // Открываем финальное окно
    const finalOverlay = document.getElementById('finalDeleteOverlay');
    const checkbox = document.getElementById('finalDeleteCheckbox');
    const btn = document.getElementById('finalDeleteBtn');
    
    checkbox.checked = false;
    btn.disabled = true;
    
    finalOverlay.classList.add('show');
}

function closeFinalDelete() {
    const overlay = document.getElementById('finalDeleteOverlay');
    overlay.classList.remove('show');
    
    // Возвращаемся к первому окну
    const firstOverlay = document.getElementById('deleteBackupOverlay');
    firstOverlay.classList.add('show');
}

async function executeFinalDelete() {
    if (!deleteBackupData) return;
    
    const btn = document.getElementById('finalDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Удаление...';
    
    try {
        const response = await fetch('delete_backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({
                postId: deleteBackupData.postId,
                filename: deleteBackupData.filename
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Бэкап успешно удален', 'success');
            
            // Закрываем финальное окно
            const finalOverlay = document.getElementById('finalDeleteOverlay');
            finalOverlay.classList.remove('show');
            
            // Закрываем первое окно
            closeDeleteBackup();
            
            // Перезагружаем список бэкапов
            openBackupManager();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
            btn.disabled = false;
            btn.textContent = 'УДАЛИТЬ НАВСЕГДА';
        }
    } catch (error) {
        console.error('Ошибка удаления бэкапа:', error);
        showNotification('Ошибка при удалении бэкапа', 'error');
        btn.disabled = false;
        btn.textContent = 'УДАЛИТЬ НАВСЕГДА';
    }
}

// ——— Система includes ———
function openSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    const input = document.getElementById('includeNameInput');
    input.value = '';
    overlay.classList.add('show');
    
    // Закрываем меню "Прочее"
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    setTimeout(() => input.focus(), 100);
}

function closeSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    overlay.classList.remove('show');
}

async function confirmSaveInclude() {
    const input = document.getElementById('includeNameInput');
    const name = input.value.trim();
    
    if (!name) {
        showNotification('Введите название файла', 'warning');
        return;
    }
    
    // Получаем контент из редактора
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    let content;
    
    if (editorMode === 'visual') {
        content = ve.innerHTML;
    } else {
        content = ta.value;
    }
    
    if (!content.trim()) {
        showNotification('Нет контента для сохранения', 'warning');
        return;
    }
    
    // Блокируем кнопку
    const saveBtn = document.querySelector('.save-include-btn.save');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';
    }
    
    try {
        const response = await fetch('save_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ name: name, content: content })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Include сохранен: ' + (data.displayName || data.filename), 'success');
            includesListLoaded = false; // Сбрасываем флаг для перезагрузки списка
            closeSaveInclude();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка сохранения include:', error);
        showNotification('Ошибка при сохранении include', 'error');
    } finally {
        // Разблокируем кнопку
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    }
}

let includesListLoaded = false;
let articlesListLoaded = false;

function toggleIncludesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadIncludesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadIncludesList() {
    if (includesListLoaded) return;
    
    const submenu = document.getElementById('includesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_includes.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.files.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет сохраненных includes</div>';
            } else {
                submenu.innerHTML = data.files.map(file => 
                    `<button type="button" class="more-submenu-item" onclick="insertInclude('${file.name}')">${file.displayName}</button>`
                ).join('');
            }
            includesListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки includes:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

async function insertInclude(filename) {
    try {
        const response = await fetch('get_include_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            
            if (editorMode === 'visual') {
                if (savedRange) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(savedRange);
                }
                document.execCommand('insertHTML', false, data.content);
                saveSelection();
            } else {
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value;
                ta.value = text.substring(0, start) + data.content + text.substring(end);
                ta.selectionStart = ta.selectionEnd = start + data.content.length;
            }
            
            // Закрываем меню
            const moreMenu = document.getElementById('moreMenuWrap');
            if (moreMenu) moreMenu.classList.remove('is-open');
            
            showNotification('Include вставлен', 'success');
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка вставки include:', error);
        showNotification('Ошибка при вставке include', 'error');
    }
}

// Функции для вставки ссылок на статьи
function toggleArticlesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadArticlesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadArticlesList() {
    if (articlesListLoaded) return;
    
    const submenu = document.getElementById('articlesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('data/blog/posts-meta.json');
        const articles = await response.json();
        
        if (articles.length === 0) {
            submenu.innerHTML = '<div class="more-submenu-empty">Нет статей</div>';
        } else {
            submenu.innerHTML = articles.map(article => 
                `<button type="button" class="more-submenu-item" onclick="insertArticleLink('${article.filename}', '${article.title.replace(/'/g, "\\'")}')">
                    ${article.title}
                </button>`
            ).join('');
        }
        articlesListLoaded = true;
    } catch (error) {
        console.error('Ошибка загрузки статей:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

function insertArticleLink(filename, title) {
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    
    const linkHtml = `<a href="${filename}">${title}</a>`;
    
    if (editorMode === 'visual') {
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
        document.execCommand('insertHTML', false, linkHtml);
        saveSelection();
    } else {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        ta.value = text.substring(0, start) + linkHtml + text.substring(end);
        ta.selectionStart = ta.selectionEnd = start + linkHtml.length;
    }
    
    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    showNotification('Ссылка на статью вставлена', 'success');
}

// ——— Проверка нумерации статей ———
async function checkPostNumbering() {
    const overlay = document.getElementById('numberingCheckOverlay');
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="numbering-status">Проверка нумерации...</div>';
    fixBtn.style.display = 'none';
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'check' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.needsFix) {
                let issuesHtml = '<div class="numbering-status warning">';
                issuesHtml += '<strong>⚠ Обнаружены проблемы с нумерацией!</strong><br><br>';
                issuesHtml += 'Следующие статьи имеют неправильную нумерацию:';
                issuesHtml += '<div class="numbering-issues-list">';
                
                data.issues.forEach(issue => {
                    issuesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${issue.title}</div>
                            <div class="numbering-issue-detail">
                                Текущий номер: ${issue.currentId} → Должен быть: ${issue.expectedId}
                            </div>
                        </div>
                    `;
                });
                
                issuesHtml += '</div></div>';
                content.innerHTML = issuesHtml;
                fixBtn.style.display = 'block';
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ Нумерация корректна!</strong><br><br>
                        Все статьи пронумерованы правильно. Исправление не требуется.
                    </div>
                `;
                fixBtn.style.display = 'none';
            }
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка проверки</strong><br><br>
                    ${data.error || 'Не удалось выполнить проверку'}
                </div>
            `;
            fixBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Ошибка проверки нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка проверки</strong><br><br>
                Не удалось выполнить проверку нумерации
            </div>
        `;
        fixBtn.style.display = 'none';
    }
}

async function fixNumbering() {
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    content.innerHTML = '<div class="numbering-status">Исправление нумерации...</div>';
    fixBtn.disabled = true;
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'fix' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.changes && data.changes.length > 0) {
                let changesHtml = '<div class="numbering-status success">';
                changesHtml += '<strong>✓ Нумерация исправлена!</strong><br><br>';
                changesHtml += 'Выполнены следующие изменения:';
                changesHtml += '<div class="numbering-issues-list">';
                
                data.changes.forEach(change => {
                    changesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${change.title}</div>
                            <div class="numbering-issue-detail">
                                Статья №${change.oldId} → Статья №${change.newId}
                            </div>
                        </div>
                    `;
                });
                
                changesHtml += '</div></div>';
                content.innerHTML = changesHtml;
                
                showNotification('Нумерация исправлена', 'success');
                
                // Обновляем список статей если он открыт
                if (document.getElementById('managePosts').classList.contains('active')) {
                    loadPosts();
                }
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ ${data.message}</strong><br><br>
                        Изменения не требуются.
                    </div>
                `;
            }
            
            fixBtn.style.display = 'none';
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка исправления</strong><br><br>
                    ${data.error || 'Не удалось выполнить исправление'}
                </div>
            `;
            fixBtn.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка исправления</strong><br><br>
                Не удалось выполнить исправление нумерации
            </div>
        `;
        fixBtn.disabled = false;
    }
}

function closeNumberingCheck() {
    const overlay = document.getElementById('numberingCheckOverlay');
    overlay.classList.remove('show');
}

// ——— Гайд для первого запуска ———
const tutorialSteps = [
    {
        title: "👋 Добро пожаловать в NPBlog!",
        text: "Это краткий гайд по использованию редактора. Давайте познакомимся с основными функциями.",
        element: null
    },
    {
        title: "📝 Поле для заголовка",
        text: "Здесь вы вводите заголовок вашей статьи. Заголовок будет отображаться в списке статей и на странице статьи.",
        element: "#title"
    },
    {
        title: "✏️ Редактор контента",
        text: "Основное поле для написания статьи. Вы можете переключаться между визуальным режимом и режимом кода.",
        element: "#contentVisual"
    },
    {
        title: "🎨 Панель форматирования",
        text: "Используйте эти кнопки для форматирования текста: жирный, курсив, заголовки, списки, ссылки, изображения и многое другое.",
        element: ".formatting-buttons"
    },
    {
        title: "📤 Кнопка публикации",
        text: "После написания статьи нажмите эту кнопку для публикации. Статья сохранится и появится в списке.",
        element: "#submitButton"
    },
    {
        title: "☰ Главное меню",
        text: "Здесь находятся дополнительные функции: управление статьями, менеджер бэкапов, проверка нумерации и настройки.",
        element: "#editorMenuBtn"
    },
    {
        title: "📋 Управление статьями",
        text: "Боковая панель для просмотра, редактирования и удаления ваших статей. Откройте её кнопкой справа.",
        element: ".manage-posts-toggle"
    }
];

let currentTutorialStep = 0;

function startTutorial() {
    const tutorialCompleted = localStorage.getItem('npblog_tutorial_completed');
    if (tutorialCompleted === 'true') return;
    
    currentTutorialStep = 0;
    showTutorialStep();
}

function showTutorialStep() {
    const overlay = document.getElementById('tutorialOverlay');
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    overlay.classList.add('show');
    tooltip.style.display = 'block';
    complete.style.display = 'none';
    
    const step = tutorialSteps[currentTutorialStep];
    
    // Обновляем контент
    document.getElementById('tutorialTitle').textContent = step.title;
    document.getElementById('tutorialText').textContent = step.text;
    
    // Обновляем прогресс
    const progressContainer = document.getElementById('tutorialProgress');
    progressContainer.innerHTML = '';
    tutorialSteps.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.className = 'tutorial-progress-dot';
        if (index === currentTutorialStep) dot.classList.add('active');
        progressContainer.appendChild(dot);
    });
    
    // Сбрасываем стили
    tooltip.style.transform = '';
    
    // Позиционируем spotlight и tooltip
    if (step.element) {
        const element = document.querySelector(step.element);
        if (element) {
            const rect = element.getBoundingClientRect();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;
            
            spotlight.style.display = 'block';
            spotlight.style.top = (rect.top + scrollY - 8) + 'px';
            spotlight.style.left = (rect.left + scrollX - 8) + 'px';
            spotlight.style.width = (rect.width + 16) + 'px';
            spotlight.style.height = (rect.height + 16) + 'px';
            
            // Позиционируем tooltip
            tooltip.style.position = 'fixed';
            const tooltipRect = tooltip.getBoundingClientRect();
            const padding = 20;
            
            // Пробуем разместить снизу
            let tooltipTop = rect.bottom + padding;
            let tooltipLeft = rect.left;
            
            // Если не помещается снизу, размещаем сверху
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = rect.top - tooltipRect.height - padding;
            }
            
            // Если не помещается сверху, размещаем справа
            if (tooltipTop < padding) {
                tooltipTop = rect.top;
                tooltipLeft = rect.right + padding;
            }
            
            // Если не помещается справа, размещаем слева
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = rect.left - tooltipRect.width - padding;
            }
            
            // Проверяем границы по горизонтали
            if (tooltipLeft < padding) {
                tooltipLeft = padding;
            }
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = window.innerWidth - tooltipRect.width - padding;
            }
            
            // Проверяем границы по вертикали
            if (tooltipTop < padding) {
                tooltipTop = padding;
            }
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = window.innerHeight - tooltipRect.height - padding;
            }
            
            tooltip.style.top = tooltipTop + 'px';
            tooltip.style.left = tooltipLeft + 'px';
        }
    } else {
        spotlight.style.display = 'none';
        // Центрируем tooltip
        tooltip.style.position = 'fixed';
        tooltip.style.top = '50%';
        tooltip.style.left = '50%';
        tooltip.style.transform = 'translate(-50%, -50%)';
    }
}

function nextTutorialStep() {
    currentTutorialStep++;
    if (currentTutorialStep >= tutorialSteps.length) {
        showTutorialComplete();
    } else {
        showTutorialStep();
    }
}

function skipTutorial() {
    if (confirm('Вы уверены, что хотите пропустить обучение?')) {
        completeTutorial();
    }
}

function showTutorialComplete() {
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    tooltip.style.display = 'none';
    spotlight.style.display = 'none';
    complete.style.display = 'block';
}

function completeTutorial() {
    localStorage.setItem('npblog_tutorial_completed', 'true');
    const overlay = document.getElementById('tutorialOverlay');
    overlay.classList.remove('show');
}

function resetTutorial() {
    if (confirm('Вы уверены, что хотите сбросить обучение? Гайд появится снова при следующей загрузке страницы.')) {
        localStorage.removeItem('npblog_tutorial_completed');
        showNotification('Обучение сброшено. Перезагрузите страницу для запуска гайда.', 'success');
    }
}

// Запускаем гайд при загрузке страницы
window.addEventListener('load', function() {
    setTimeout(startTutorial, 500);
});
</script>

<script>
    (function() {
        var wrap = document.getElementById('editorMenuWrap');
        var btn = document.getElementById('editorMenuBtn');
        if (!wrap || !btn) return;
        function toggleMenu() {
            if (wrap.classList.contains('is-open')) {
                closeMenu();
            } else {
                wrap.classList.remove('is-closing');
                wrap.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        }
        function closeMenu() {
            wrap.classList.add('is-closing');
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            setTimeout(() => {
                wrap.classList.remove('is-closing');
            }, 300);
        }
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });
        document.addEventListener('click', function() {
            if (wrap.classList.contains('is-open')) closeMenu();
        });
        wrap.querySelector('.editor-menu-dropdown').addEventListener('click', function(e) {
            e.stopPropagation();
            closeMenu();
        });
    })();

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

<!-- Модальное окно дополнительных настроек -->
<div id="additionalSettingsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin: 0 0 20px 0; color: var(--text-color); font-size: 20px;">Дополнительные настройки</h3>
        <p id="additionalSettingsPostTitle" style="color: var(--text-color); margin-bottom: 20px; opacity: 0.7;"></p>
        
        <!-- Глобальный фон -->
        <div id="globalBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid #ffc107; border-radius: 8px; background: rgba(255, 193, 7, 0.05);">
            <p style="color: var(--text-color); font-weight: 500; margin-bottom: 10px;">🌍 Применен глобальный фон:</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="globalBackgroundPreview" src="" alt="Глобальный фон" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);">
                <div>
                    <p id="globalBackgroundName" style="color: var(--text-color); font-size: 14px; word-break: break-all;"></p>
                    <p id="globalBackgroundModeText" style="color: var(--text-color); font-size: 12px; opacity: 0.7; margin-top: 5px;"></p>
                    <p style="color: var(--text-color); font-size: 12px; opacity: 0.6; margin-top: 5px; font-style: italic;">Загрузите свой фон ниже, чтобы переопределить глобальный</p>
                </div>
            </div>
        </div>
        
        <!-- Текущий фон статьи -->
        <div id="currentBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid var(--border-color); border-radius: 8px;">
            <p style="color: var(--text-color); font-weight: 500; margin-bottom: 10px;">Текущий фон статьи:</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="currentBackgroundPreview" src="" alt="Фон" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);">
                <div>
                    <p id="currentBackgroundName" style="color: var(--text-color); font-size: 14px; word-break: break-all;"></p>
                    <p id="currentBackgroundMode" style="color: var(--text-color); font-size: 12px; opacity: 0.7; margin-top: 5px;"></p>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Фоновое изображение:</label>
            <input type="file" id="backgroundInput" accept="image/*" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 10px;">
            
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Режим отображения:</label>
            <select id="backgroundMode" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                <option value="cover">Растянуть (cover)</option>
                <option value="contain">По размеру (contain)</option>
                <option value="repeat">Замостить (repeat)</option>
            </select>
            
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Область фона:</label>
            <select id="backgroundScope" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                <option value="content">Только статья (920px)</option>
                <option value="fullpage">Вся страница</option>
            </select>
            
            <button type="button" onclick="uploadBackground()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-right: 10px;">Загрузить фон</button>
            <button type="button" onclick="removeBackground()" style="padding: 10px 20px; background: transparent; color: var(--text-color); border: 2px solid var(--text-color); border-radius: 8px; cursor: pointer; font-weight: 500;">Вернуть стандартный фон</button>
        </div>
        
        <!-- Настройки подложки -->
        <div style="margin-bottom: 20px; padding-top: 20px; border-top: 2px solid var(--border-color);">
            <label style="display: flex; align-items: center; margin-bottom: 15px; color: var(--text-color); font-weight: 500; cursor: pointer;">
                <input type="checkbox" id="overlayEnabled" onchange="toggleOverlaySettings()" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                Включить подложку под статью
            </label>
            
            <div id="overlaySettings" style="display: none; padding-left: 30px;">
                <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Цвет подложки:</label>
                <input type="color" id="overlayColor" value="#ffffff" style="width: 100%; height: 40px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; margin-bottom: 15px;">
                
                <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Прозрачность: <span id="overlayOpacityValue">90%</span></label>
                <input type="range" id="overlayOpacity" min="0" max="100" value="90" oninput="updateOpacityValue()" style="width: 100%; margin-bottom: 15px;">
            </div>
            
            <button type="button" onclick="saveOverlaySettings()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Сохранить настройки подложки</button>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" onclick="closeAdditionalSettings()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Закрыть</button>
        </div>
    </div>
</div>

<!-- Модальное окно глобальных параметров -->
<div id="globalSettingsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 900px; width: 90%; height: 80vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; overflow: hidden;">
        <!-- Навигация слева -->
        <div style="width: 200px; background: rgba(0,0,0,0.05); border-right: 2px solid var(--border-color); padding: 20px; overflow-y: auto;">
            <h3 style="margin: 0 0 20px 0; color: var(--text-color); font-size: 18px;">Навигация</h3>
            <button type="button" onclick="showGlobalSection('backgrounds')" class="global-nav-btn active" data-section="backgrounds" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Фон статей
            </button>
            <!-- Здесь можно добавить другие пункты навигации -->
        </div>
        
        <!-- Контент справа -->
        <div style="flex: 1; padding: 30px; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--text-color); font-size: 20px;" id="globalSectionTitle">Фон статей</h3>
                <button type="button" onclick="closeGlobalSettings()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
            </div>
            
            <!-- Секция: Фон статей -->
            <div id="globalSection-backgrounds" class="global-section">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Загрузите фоновое изображение, которое будет применяться ко всем статьям по умолчанию.</p>
                
                <!-- Текущий глобальный фон -->
                <div id="currentGlobalBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid var(--border-color); border-radius: 8px;">
                    <p style="color: var(--text-color); margin-bottom: 10px; font-weight: 500;">Текущий глобальный фон:</p>
                    <img id="currentGlobalBackgroundPreview" src="" style="max-width: 200px; max-height: 150px; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 10px;">
                    <p style="color: var(--text-color); font-size: 14px; margin-bottom: 5px;" id="currentGlobalBackgroundName"></p>
                    <p style="color: var(--text-color); font-size: 14px;" id="currentGlobalBackgroundMode"></p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Фоновое изображение:</label>
                    <input type="file" id="globalBackgroundInput" accept="image/*" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 10px;">
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Режим отображения:</label>
                    <select id="globalBackgroundMode" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                        <option value="cover">Растянуть (cover)</option>
                        <option value="contain">По размеру (contain)</option>
                        <option value="repeat">Замостить (repeat)</option>
                    </select>
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Область фона:</label>
                    <select id="globalBackgroundScope" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                        <option value="content">Только статья (920px)</option>
                        <option value="fullpage">Вся страница</option>
                    </select>
                    
                    <button type="button" onclick="uploadGlobalBackground()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-right: 10px;">Загрузить глобальный фон</button>
                    <button type="button" onclick="removeGlobalBackground()" style="padding: 10px 20px; background: transparent; color: var(--text-color); border: 2px solid var(--text-color); border-radius: 8px; cursor: pointer; font-weight: 500; margin-right: 10px;">Удалить глобальный фон</button>
                    <button type="button" onclick="updateBackgroundStyles()" style="padding: 10px 20px; background: rgba(33, 150, 243, 0.8); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Обновить стили статей</button>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 193, 7, 0.1); border: 2px solid rgba(255, 193, 7, 0.5); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 14px; margin: 0;">
                        ⚠️ Глобальный фон применяется ко всем существующим статьям и будет автоматически применяться к новым статьям. Индивидуальные настройки фона статьи имеют приоритет над глобальным фоном.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentAdditionalPostId = null;

function openAdditionalSettings(postId, postTitle) {
    currentAdditionalPostId = postId;
    document.getElementById('additionalSettingsPostTitle').textContent = 'Статья: ' + postTitle;
    
    // Загружаем настройки из post_backgrounds.json
    fetch('get_post_backgrounds.php?postId=' + postId)
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            
            // Устанавливаем режим отображения
            document.getElementById('backgroundMode').value = settings.backgroundMode || 'cover';
            
            // Устанавливаем область фона
            document.getElementById('backgroundScope').value = settings.backgroundScope || 'content';
            
            // Проверяем глобальный фон
            return fetch('data/global-settings.json?t=' + Date.now())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No global settings');
                    }
                    return response.json();
                })
                .then(globalSettings => {
                    return { settings, globalSettings };
                })
                .catch(() => {
                    return { settings, globalSettings: null };
                });
        })
        .then(({ settings, globalSettings }) => {
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            const globalBgInfo = document.getElementById('globalBackgroundInfo');
            
            // Отображаем текущий фон статьи если есть
            if (settings.background) {
                const bgPreview = document.getElementById('currentBackgroundPreview');
                const bgName = document.getElementById('currentBackgroundName');
                const bgMode = document.getElementById('currentBackgroundMode');
                
                bgPreview.src = '/data/backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
                globalBgInfo.style.display = 'none';
            } else if (globalSettings && globalSettings.background) {
                // Показываем глобальный фон если у статьи нет своего
                const bgPreview = document.getElementById('globalBackgroundPreview');
                const bgName = document.getElementById('globalBackgroundName');
                const bgMode = document.getElementById('globalBackgroundModeText');
                
                bgPreview.src = '/data/backgrounds/' + globalSettings.background;
                bgName.textContent = globalSettings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[globalSettings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[globalSettings.backgroundScope] || 'Только статья');
                
                globalBgInfo.style.display = 'block';
                currentBgInfo.style.display = 'none';
                
                // Устанавливаем значения из глобальных настроек
                document.getElementById('backgroundMode').value = globalSettings.backgroundMode || 'cover';
                document.getElementById('backgroundScope').value = globalSettings.backgroundScope || 'content';
            } else {
                currentBgInfo.style.display = 'none';
                globalBgInfo.style.display = 'none';
            }
            
            // Загружаем настройки подложки
            if (settings.overlayEnabled) {
                document.getElementById('overlayEnabled').checked = true;
                document.getElementById('overlayColor').value = settings.overlayColor || '#ffffff';
                document.getElementById('overlayOpacity').value = settings.overlayOpacity || 90;
                document.getElementById('overlayOpacityValue').textContent = (settings.overlayOpacity || 90) + '%';
                document.getElementById('overlaySettings').style.display = 'block';
            } else {
                document.getElementById('overlayEnabled').checked = false;
                document.getElementById('overlayColor').value = '#ffffff';
                document.getElementById('overlayOpacity').value = 90;
                document.getElementById('overlayOpacityValue').textContent = '90%';
                document.getElementById('overlaySettings').style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('backgroundMode').value = 'cover';
            document.getElementById('backgroundScope').value = 'content';
            document.getElementById('currentBackgroundInfo').style.display = 'none';
            document.getElementById('globalBackgroundInfo').style.display = 'none';
            document.getElementById('overlayEnabled').checked = false;
            document.getElementById('overlaySettings').style.display = 'none';
        });
    
    const modal = document.getElementById('additionalSettingsModal');
    modal.style.display = 'flex';
    
    // Запускаем анимацию после небольшой задержки
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function closeAdditionalSettings() {
    const modal = document.getElementById('additionalSettingsModal');
    modal.classList.remove('show');
    
    // Скрываем модальное окно после завершения анимации
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('backgroundInput').value = '';
        currentAdditionalPostId = null;
    }, 300);
}

function uploadBackground() {
    const fileInput = document.getElementById('backgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('backgroundMode').value;
    const scope = document.getElementById('backgroundScope').value;
    
    if (!file) {
        alert('Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('postId', currentAdditionalPostId);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Фон успешно загружен');
            fileInput.value = '';
            
            // Обновляем отображение текущего фона
            const bgPreview = document.getElementById('currentBackgroundPreview');
            const bgName = document.getElementById('currentBackgroundName');
            const bgMode = document.getElementById('currentBackgroundMode');
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            
            bgPreview.src = '/data/backgrounds/' + data.filename;
            bgName.textContent = data.filename;
            
            const modeText = {
                'cover': 'Растянуть',
                'contain': 'По размеру',
                'repeat': 'Замостить'
            };
            const scopeText = {
                'content': 'Только статья',
                'fullpage': 'Вся страница'
            };
            bgMode.textContent = 'Режим: ' + (modeText[mode] || 'Растянуть') + ' | Область: ' + (scopeText[scope] || 'Только статья');
            
            currentBgInfo.style.display = 'block';
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка загрузки фона');
    });
}

function removeBackground() {
    if (!confirm('Вернуть стандартный фон?')) {
        return;
    }
    
    fetch('remove_background.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ postId: currentAdditionalPostId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.globalApplied) {
                alert('Индивидуальный фон удален. Применен глобальный фон.');
            } else {
                alert('Фон удален');
            }
            
            // Перезагружаем настройки чтобы показать глобальный фон если он есть
            closeAdditionalSettings();
            // Небольшая задержка перед повторным открытием
            setTimeout(() => {
                // Находим название статьи
                fetch('data/blog/posts-meta.json')
                    .then(response => response.json())
                    .then(meta => {
                        const post = meta.find(p => p.id === currentAdditionalPostId);
                        if (post) {
                            openAdditionalSettings(currentAdditionalPostId, post.title);
                        }
                    });
            }, 100);
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка удаления фона');
    });
}

function toggleOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const settings = document.getElementById('overlaySettings');
    settings.style.display = enabled ? 'block' : 'none';
}

function updateOpacityValue() {
    const value = document.getElementById('overlayOpacity').value;
    document.getElementById('overlayOpacityValue').textContent = value + '%';
}

function saveOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const color = document.getElementById('overlayColor').value;
    const opacity = document.getElementById('overlayOpacity').value;
    
    const data = {
        postId: currentAdditionalPostId,
        overlayEnabled: enabled,
        overlayColor: color,
        overlayOpacity: opacity
    };
    
    fetch('save_overlay_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Настройки подложки сохранены');
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка сохранения настроек');
    });
}

// Глобальные параметры
function openGlobalSettings() {
    const modal = document.getElementById('globalSettingsModal');
    modal.style.display = 'flex';
    
    // Запускаем анимацию после небольшой задержки
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    loadGlobalBackground();
}

function closeGlobalSettings() {
    const modal = document.getElementById('globalSettingsModal');
    modal.classList.remove('show');
    
    // Скрываем модальное окно после завершения анимации
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function showGlobalSection(sectionName) {
    // Обновляем активную кнопку навигации
    document.querySelectorAll('.global-nav-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.section === sectionName) {
            btn.classList.add('active');
        }
    });
    
    // Показываем нужную секцию
    document.querySelectorAll('.global-section').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById('globalSection-' + sectionName).style.display = 'block';
    
    // Обновляем заголовок
    const titles = {
        'backgrounds': 'Фон статей'
    };
    document.getElementById('globalSectionTitle').textContent = titles[sectionName] || '';
}

function loadGlobalBackground() {
    fetch('data/global-settings.json?t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                // Файл не существует
                throw new Error('File not found');
            }
            return response.json();
        })
        .then(settings => {
            if (settings && settings.background) {
                document.getElementById('globalBackgroundMode').value = settings.backgroundMode || 'cover';
                document.getElementById('globalBackgroundScope').value = settings.backgroundScope || 'content';
                
                const bgPreview = document.getElementById('currentGlobalBackgroundPreview');
                const bgName = document.getElementById('currentGlobalBackgroundName');
                const bgMode = document.getElementById('currentGlobalBackgroundMode');
                const currentBgInfo = document.getElementById('currentGlobalBackgroundInfo');
                
                bgPreview.src = '/data/backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
            } else {
                document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
                // Устанавливаем значения по умолчанию
                document.getElementById('globalBackgroundMode').value = 'cover';
                document.getElementById('globalBackgroundScope').value = 'content';
            }
        })
        .catch(() => {
            // Файл не существует или произошла ошибка
            document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
            // Устанавливаем значения по умолчанию
            document.getElementById('globalBackgroundMode').value = 'cover';
            document.getElementById('globalBackgroundScope').value = 'content';
        });
}

function uploadGlobalBackground() {
    const fileInput = document.getElementById('globalBackgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('globalBackgroundMode').value;
    const scope = document.getElementById('globalBackgroundScope').value;
    
    if (!file) {
        alert('Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_global_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Глобальный фон успешно загружен и применен ко всем статьям');
            fileInput.value = '';
            loadGlobalBackground();
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка загрузки фона');
    });
}

function removeGlobalBackground() {
    if (!confirm('Удалить глобальный фон из всех статей?')) {
        return;
    }
    
    fetch('remove_global_background.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Глобальный фон удален');
            loadGlobalBackground();
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка удаления фона');
    });
}

function updateBackgroundStyles() {
    if (!confirm('Обновить стили фона во всех статьях? Это применит новые отступы padding к существующим статьям.')) {
        return;
    }
    
    fetch('update_background_styles.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Стили обновлены в ' + data.updated + ' статьях');
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка обновления стилей');
    });
}
</script>

</body>
</html>
