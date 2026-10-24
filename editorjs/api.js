/**
 * NPBlog REST API v1 Client
 * Clean, promise-based API abstraction eliminating legacy PHP endpoints.
 */
(function(window) {
    'use strict';

    const BASE_URL = '/api/v1';

    async function request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') || endpoint.startsWith('/') 
            ? endpoint 
            : `${BASE_URL}/${endpoint}`;

        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (window.CSRF_TOKEN) {
            headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }

        let body = options.body;
        if (body && !(body instanceof FormData) && typeof body === 'object') {
            headers['Content-Type'] = 'application/json; charset=utf-8';
            body = JSON.stringify(body);
        }

        const config = {
            ...options,
            headers,
            body
        };

        let response;
        try {
            response = await fetch(url, config);
        } catch (fetchErr) {
            if (window.ConnectionMonitor) {
                window.ConnectionMonitor.notifyLost();
            }
            throw fetchErr;
        }

        if (response.ok && window.ConnectionMonitor && !window.ConnectionMonitor.isOnline()) {
            window.ConnectionMonitor.notifyRestored();
        }
        
        let json;
        try {
            json = await response.json();
        } catch (e) {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return { success: true };
        }

        if (!response.ok || (json && json.success === false)) {
            const msg = (json && json.error && (json.error.message || json.error.code)) 
                || (json && json.message) 
                || `Request failed with status ${response.status}`;
            const err = new Error(msg);
            err.status = response.status;
            err.data = json;
            throw err;
        }

        return json;
    }

    const api = {
        request,

        // --- Auth ---
        auth: {
            async logout() {
                const res = await request('/api/v1/auth/logout', {
                    method: 'POST'
                });
                return res.data || res;
            }
        },

        // --- Autosaves ---
        autosaves: {
            async list() {
                const res = await request('/api/v1/autosaves');
                return res.data && res.data.autosaves ? res.data.autosaves : (res.autosaves || []);
            },
            async get(id) {
                const res = await request(`/api/v1/autosaves/${encodeURIComponent(id)}`);
                return res.data && res.data.autosave ? res.data.autosave : (res.autosave || res);
            },
            async save(payload) {
                const res = await request('/api/v1/autosaves', {
                    method: 'POST',
                    body: payload
                });
                return res.data || res;
            },
            async delete(id) {
                return await request(`/api/v1/autosaves/${encodeURIComponent(id)}`, {
                    method: 'DELETE'
                });
            },
            async clearAll() {
                return await request('/api/v1/autosaves', {
                    method: 'DELETE'
                });
            }
        },

        // --- Drafts ---
        drafts: {
            async list() {
                const res = await request('/api/v1/drafts');
                return res.data && res.data.drafts ? res.data.drafts : (res.drafts || []);
            },
            async save(payload) {
                const res = await request('/api/v1/drafts', {
                    method: 'POST',
                    body: payload
                });
                return res.data || res;
            },
            async delete(filename) {
                return await request(`/api/v1/drafts/${encodeURIComponent(filename)}`, {
                    method: 'DELETE'
                });
            }
        },

        // --- Includes ---
        includes: {
            async list() {
                const res = await request('/api/v1/includes');
                return res.data && res.data.includes ? res.data.includes : (res.includes || res.files || []);
            },
            async get(filename) {
                const res = await request(`/api/v1/includes/${encodeURIComponent(filename)}`);
                return res.data && res.data.content !== undefined ? res.data.content : (res.content || '');
            },
            async save(payload) {
                const res = await request('/api/v1/includes', {
                    method: 'POST',
                    body: payload
                });
                return res.data || res;
            },
            async delete(filename) {
                return await request(`/api/v1/includes/${encodeURIComponent(filename)}`, {
                    method: 'DELETE'
                });
            }
        },

        // --- Settings ---
        settings: {
            async getEditor() {
                const res = await request('/api/v1/settings/editor?t=' + Date.now());
                return res.data && res.data.settings ? res.data.settings : (res.settings || {});
            },
            async updateEditor(data) {
                const res = await request('/api/v1/settings/editor', {
                    method: 'PUT',
                    body: data
                });
                return res.data || res;
            },
            async getGlobal() {
                const res = await request('/api/v1/settings/global?t=' + Date.now());
                return res.data && res.data.settings ? res.data.settings : (res.settings || {});
            },
            async updateGlobal(data) {
                const res = await request('/api/v1/settings/global', {
                    method: 'PUT',
                    body: data
                });
                return res.data || res;
            },
            async getBlogView() {
                const res = await request('/api/v1/settings/blog-view?t=' + Date.now());
                return res.data && res.data.settings ? res.data.settings : (res.settings || {});
            },
            async updateBlogView(data) {
                const res = await request('/api/v1/settings/blog-view', {
                    method: 'PUT',
                    body: data
                });
                return res.data || res;
            },
            async getBlogNav() {
                const res = await request('/api/v1/settings/blog-nav?t=' + Date.now());
                return res.data || res;
            },
            async updateBlogNav(data) {
                const res = await request('/api/v1/settings/blog-nav', {
                    method: 'PUT',
                    body: data
                });
                return res.data || res;
            }
        },

        // --- Posts ---
        posts: {
            async list() {
                const res = await request('/api/v1/posts');
                return res.data || [];
            },
            async get(id) {
                const res = await request(`/api/v1/posts/${id}`);
                return res.data || res;
            },
            async create(data) {
                const res = await request('/api/v1/posts', {
                    method: 'POST',
                    body: data
                });
                return res.data || res;
            },
            async update(id, data) {
                const res = await request(`/api/v1/posts/${id}`, {
                    method: 'PUT',
                    body: data
                });
                return res.data || res;
            },
            async delete(id) {
                return await request(`/api/v1/posts/${id}`, {
                    method: 'DELETE'
                });
            },
            async renumber() {
                return await request('/api/v1/posts/renumber', {
                    method: 'POST'
                });
            },
            async togglePin(id) {
                return await request(`/api/v1/posts/${id}/pin`, {
                    method: 'POST'
                });
            },
            async regenerate() {
                return await request('/api/v1/posts/regenerate', {
                    method: 'POST'
                });
            }
        },

        // --- History ---
        history: {
            async list() {
                const res = await request('/api/v1/system/history');
                return res.data && res.data.history ? res.data.history : (res.history || []);
            },
            async save(action, details) {
                return await request('/api/v1/system/history', {
                    method: 'POST',
                    body: { action, details: details || '' }
                });
            },
            async clear() {
                return await request('/api/v1/system/history', {
                    method: 'DELETE'
                });
            }
        },

        // --- System & Updates ---
        system: {
            async getVersion() {
                const res = await request('/api/v1/system/version?t=' + Date.now());
                return res.data || res;
            },
            async getChangelog() {
                const res = await request('/api/v1/system/changelog?t=' + Date.now());
                return res.data || res;
            },
            async checkAutoUpdate(params = {}) {
                const qs = new URLSearchParams(params).toString();
                const res = await request(`/api/v1/system/updates/check?${qs}`);
                return res.data || res;
            },
            async prepareAutoUpdate(data) {
                const res = await request('/api/v1/system/updates/prepare', {
                    method: 'POST',
                    body: data || {}
                });
                return res.data || res;
            },
            async getUpdateProgress(token) {
                const res = await request(`/api/v1/system/updates/progress?token=${encodeURIComponent(token)}&t=${Date.now()}`);
                return res.data || res;
            },
            async listBackups() {
                const res = await request('/api/v1/system/backups');
                return res.data && res.data.backups ? res.data.backups : (res.backups || []);
            },
            async restoreBackup(filename) {
                return await request('/api/v1/system/restore', {
                    method: 'POST',
                    body: { filename }
                });
            }
        },

        // --- Integrity ---
        integrity: {
            async check() {
                const res = await request('/api/v1/system/integrity');
                return res.data || res;
            },
            async fix() {
                const res = await request('/api/v1/system/integrity/fix', {
                    method: 'POST'
                });
                return res.data || res;
            }
        },

        // --- Backups ---
        backups: {
            async list() {
                const res = await request('/api/v1/backups');
                return res.data && res.data.backups ? res.data.backups : (res.backups || []);
            },
            async get(postId, backupNum) {
                const res = await request(`/api/v1/backups/content?postId=${postId}&backup=${backupNum}`);
                return res.data || res;
            },
            async restore(postId, backupNum) {
                return await request('/api/v1/backups/restore', {
                    method: 'POST',
                    body: { postId, backupNumber: backupNum }
                });
            },
            async delete(postId, backupNum) {
                return await request(`/api/v1/backups?postId=${postId}&backup=${backupNum}`, {
                    method: 'DELETE'
                });
            }
        },

        // --- Media & Files ---
        media: {
            async listSmiles() {
                const res = await request('/api/v1/media/smiles?t=' + Date.now());
                return res.data && res.data.smiles ? res.data.smiles : (res.smiles || {});
            },
            async listDocuments() {
                const res = await request('/api/v1/media/documents?t=' + Date.now());
                return res.data && res.data.files ? res.data.files : (res.files || []);
            },
            async deleteDocument(filename) {
                return await request(`/api/v1/media/documents/${encodeURIComponent(filename)}`, {
                    method: 'DELETE'
                });
            },
            async listAudio() {
                const res = await request('/api/v1/media/audio?t=' + Date.now());
                return res.data && res.data.files ? res.data.files : (res.files || []);
            },
            async deleteAudio(filename) {
                return await request(`/api/v1/media/audio/${encodeURIComponent(filename)}`, {
                    method: 'DELETE'
                });
            },
            async listVideo() {
                const res = await request('/api/v1/media/video?t=' + Date.now());
                return res.data && res.data.files ? res.data.files : (res.files || []);
            },
            async deleteVideo(filename) {
                return await request(`/api/v1/media/video/${encodeURIComponent(filename)}`, {
                    method: 'DELETE'
                });
            },
            async listFonts() {
                const res = await request('/api/v1/media/fonts?t=' + Date.now());
                return res.data && res.data.fonts ? res.data.fonts : (res.fonts || []);
            }
        },

        // --- Templates ---
        templates: {
            async list() {
                const res = await request('/api/v1/templates?t=' + Date.now());
                return res.data && res.data.templates ? res.data.templates : (res.templates || []);
            },
            async apply(templateName) {
                return await request('/api/v1/templates/apply', {
                    method: 'POST',
                    body: { template_name: templateName }
                });
            }
        }
    };

    window.NPBlogAPI = api;
    window.api = api;
})(window);
