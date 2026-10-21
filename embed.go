package npblog

import "embed"

//go:embed editor-style.css editor-img.js
//go:embed editorjs modals modals_editor lang assets web
var EmbeddedAssets embed.FS
