package npblog

import "embed"

//go:embed editor-style.css editor-img.js editor_settings.example.json
//go:embed editorjs modals lang assets web
var EmbeddedAssets embed.FS
