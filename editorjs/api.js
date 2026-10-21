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

        const response = await fetch(url, config);
        
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
            }
        }
    };

    window.NPBlogAPI = api;
    window.api = api;
})(window);
