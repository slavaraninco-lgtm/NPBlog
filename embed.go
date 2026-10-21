package npblog

import "embed"

//go:embed editor-style.css editor-img.js
//go:embed editorjs modals lang assets web
var EmbeddedAssets embed.FS
