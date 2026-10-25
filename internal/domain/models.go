package domain

// PostMeta представляет элемент в data/blog/posts-meta.json
type PostMeta struct {
	ID        int    `json:"id"`
	Title     string `json:"title"`
	Date      string `json:"date"`
	Filename  string `json:"filename"`
	Pinned    bool   `json:"pinned,omitempty"`
	PinnedAt  string `json:"pinned_at,omitempty"`
}

// BackupMeta представляет структуру хранения в data_backup/backup-meta.json
type BackupMeta map[string]PostBackupInfo

type PostBackupInfo struct {
	PostID    interface{}   `json:"postId"`
	PostTitle string        `json:"postTitle"`
	Backups   []BackupEntry `json:"backups"`
	Deleted   bool          `json:"deleted,omitempty"`
	DeletedAt string        `json:"deletedAt,omitempty"`
}

type BackupEntry struct {
	BackupNumber int    `json:"backupNumber"`
	Filename     string `json:"filename"`
	Date         string `json:"date"`
	Title        string `json:"title"`
}

// EditorSettings представляет настройки редактора в editor_settings.json
type EditorSettings struct {
	HideEditorModeButtons bool              `json:"hideEditorModeButtons"`
	AmoledTheme           bool              `json:"amoledTheme"`
	EnableUndoRedo        bool              `json:"enableUndoRedo"`
	SmoothTyping          bool              `json:"smoothTyping"`
	DisableAnimations     bool              `json:"disableAnimations"`
	HeaderBottomPosition  bool              `json:"headerBottomPosition"`
	EnableMarkdown        bool              `json:"enableMarkdown"`
	EnableAPI             bool              `json:"enableApi"`
	AutosaveEnabled       bool              `json:"autosaveEnabled"`
	AutosaveInterval      int               `json:"autosaveInterval"`
	TutorialCompleted     bool              `json:"tutorialCompleted"`
	InitialSetupCompleted bool              `json:"initial_setup_completed"`
	ContentWidth          int               `json:"contentWidth"`
	BlogPaths             []string          `json:"blog_paths"`
	ActiveBlogPath        string            `json:"active_blog_path"`
	BackupPath            string            `json:"backup_path"`
	AutosavePath          string            `json:"autosave_path"`
	EditorBackupPath      string            `json:"editor_backup_path"`
	RssEnabled            bool              `json:"rss_enabled"`
	RssBaseURL            string            `json:"rss_base_url"`
	RssTitle              string            `json:"rss_title"`
	RssDescription        string            `json:"rss_description"`
	RssUseFirstLine       bool              `json:"rss_use_first_line"`
	RssContentTemplate    string            `json:"rss_content_template"`
	ActiveTheme           string            `json:"activeTheme"`
	CustomThemeCSS        string            `json:"customThemeCss"`
	Language              string            `json:"language"`
	PasswordSet           bool              `json:"password_set"`
	PasswordEnabled       bool              `json:"password_enabled"`
	PasswordHash          string            `json:"password_hash,omitempty"`
	IPWhitelistEnabled    bool              `json:"ip_whitelist_enabled"`
	AutoUpdateCheck       bool              `json:"auto_update_check"`
	UpdateChannel         string            `json:"update_channel"`
	UpdateRepo            string            `json:"update_repo"`
	UpdateGithubToken     string            `json:"update_github_token"`
	BottomBar             BottomBarSettings `json:"bottomBar"`
}

type BottomBarSettings struct {
	ShowModeToggle    bool `json:"showModeToggle"`
	ShowBlogSelector  bool `json:"showBlogSelector"`
	ShowWordCount     bool `json:"showWordCount"`
	ShowAutosaveBadge bool `json:"showAutosaveBadge"`
}

// GlobalSettings представляет общие настройки блога в data/global-settings.json
type GlobalSettings struct {
	BlogTitle       string `json:"blogTitle"`
	BlogSubtitle    string `json:"blogSubtitle"`
	Author          string `json:"author"`
	BlogDescription string `json:"blogDescription"`
	BlogKeywords    string `json:"blogKeywords"`
	Theme           string `json:"theme"`
}

// BlogViewSettings представляет настройки отображения списка статей в data/blog-view-settings.json
type BlogViewSettings struct {
	Title          string      `json:"title"`
	Layout         string      `json:"layout"` // "list" | "grid"
	Background     string      `json:"background,omitempty"`
	BackgroundMode string      `json:"backgroundMode,omitempty"`
	CrossBlogNav   interface{} `json:"crossBlogNav,omitempty"`
	ViewMode       string      `json:"viewMode,omitempty"`
	PostsPerPage   int         `json:"postsPerPage,omitempty"`
	ShowDate       bool        `json:"showDate,omitempty"`
	ShowAuthor     bool        `json:"showAuthor,omitempty"`
	ShowSummary    bool        `json:"showSummary,omitempty"`
	TruncateLength int         `json:"truncateLength,omitempty"`
	CustomCSS      string      `json:"customCss,omitempty"`
}

// DraftData представляет черновик в data/drafts/
type DraftData struct {
	Filename  string `json:"filename"`
	Title     string `json:"title"`
	Content   string `json:"content"`
	Timestamp int64  `json:"timestamp"`
	Date      string `json:"date"`
	PostID    *int   `json:"postId,omitempty"`
}

// AutosaveData представляет автосохранение статьи
type AutosaveData struct {
	ID        string `json:"id"`
	PostID    int    `json:"postId,omitempty"`
	Title     string `json:"title"`
	Content   string `json:"content"`
	Timestamp int64  `json:"timestamp"`
	Date      string `json:"date"`
}

// ApiToken представляет токен доступа для мобильных клиентов в api_tokens.json
type ApiToken struct {
	Token      string `json:"token"`
	DeviceName string `json:"device_name"`
	CreatedAt  int64  `json:"created_at"`
	LastUsedAt int64  `json:"last_used_at"`
	ExpiresAt  int64  `json:"expires_at"`
}

// LockoutInfo представляет запись о блокировке IP за перебор паролей
type LockoutInfo struct {
	Attempts     int    `json:"attempts"`
	LockoutUntil int64  `json:"lockout_until"`
	IP           string `json:"ip"`
}

// HistoryAction представляет запись действия в history.json
type HistoryAction struct {
	Action    string `json:"action"`
	Timestamp int64  `json:"timestamp"`
	Date      string `json:"date"`
	Details   string `json:"details,omitempty"`
}

// IncludeMetaItem представляет запись сниппета в includes/includes-meta.json
type IncludeMetaItem struct {
	Name        string `json:"name"`
	DisplayName string `json:"displayName"`
	Title       string `json:"title,omitempty"`
	Description string `json:"description,omitempty"`
	Date        string `json:"date,omitempty"`
	Size        int64  `json:"size,omitempty"`
}

// PostBgSettings хранит настройки фона и подложки отдельной статьи (data/post_backgrounds.json)
type PostBgSettings struct {
	Background      string `json:"background,omitempty"`
	BackgroundMode  string `json:"backgroundMode,omitempty"`  // cover, contain, repeat
	BackgroundScope string `json:"backgroundScope,omitempty"` // content, fullpage
	OverlayEnabled  bool   `json:"overlayEnabled,omitempty"`
	OverlayColor    string `json:"overlayColor,omitempty"`
	OverlayOpacity  int    `json:"overlayOpacity,omitempty"`
}

// GlobalBgSettings хранит настройки глобального фона для статей (data/global-settings.json)
type GlobalBgSettings struct {
	Background      string `json:"background,omitempty"`
	BackgroundMode  string `json:"backgroundMode,omitempty"`  // cover, contain, repeat
	BackgroundScope string `json:"backgroundScope,omitempty"` // content, fullpage
}

// PostBackgroundConfig представляет обратную совместимость конфигурации фонов
type PostBackgroundConfig struct {
	GlobalBackground *BackgroundItem            `json:"global,omitempty"`
	BlogBackground   *BackgroundItem            `json:"blog,omitempty"`
	Posts            map[string]*BackgroundItem `json:"posts,omitempty"`
}

type BackgroundItem struct {
	Image      string  `json:"image"`
	Blur       float64 `json:"blur,omitempty"`
	Brightness float64 `json:"brightness,omitempty"`
	Opacity    float64 `json:"opacity,omitempty"`
	Fixed      bool    `json:"fixed,omitempty"`
	Repeat     string  `json:"repeat,omitempty"`
	Size       string  `json:"size,omitempty"`
}
