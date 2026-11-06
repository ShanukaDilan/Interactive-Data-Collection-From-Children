import { storage } from './util.js';

const STORAGE_KEY = 'learningfun.outbox.v1';
const MAX_SESSIONS_PER_REQUEST = 50;
const RETRY_DELAYS_MS = [3000, 10000, 30000, 60000];

/**
 * Queues sessions and answers on the device and syncs them to the server.
 *
 * Every answer is kept locally until the server acknowledges it, so a flaky connection
 * (or closing the tab) never loses data. The server is idempotent (uuid / client_uuid),
 * so re-sending is always safe.
 *
 * Entry shape: { meta, responses: [{...response, _acked}], completed_at, metaAcked, completionAcked }
 */
export class Outbox {
    /**
     * @param {{ syncUrl: string, csrfToken: () => string|null, onStatus?: (status: {state: string, pending: number}) => void }} options
     */
    constructor({ syncUrl, csrfToken, onStatus }) {
        this.syncUrl = syncUrl;
        this.csrfToken = csrfToken;
        this.onStatus = onStatus ?? (() => {});
        this.entries = storage.get(STORAGE_KEY, {}) ?? {};

        // Sessions left from an earlier page load (tab closed or reloaded mid-quiz) cannot be
        // resumed: keep their answers until synced, then let them be cleaned up.
        for (const [uuid, entry] of Object.entries(this.entries)) {
            entry.abandoned = true;
            if (!this.needsSync(entry)) {
                delete this.entries[uuid];
            }
        }
        this.flushing = false;
        this.flushAgain = false;
        this.retryIndex = 0;
        this.retryTimer = null;
        this.lastError = null;

        window.addEventListener('online', () => this.flush());
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.flush();
            }
        });

        this.persist();
    }

    startSession(meta) {
        this.entries[meta.uuid] = {
            meta,
            responses: [],
            completed_at: null,
            metaAcked: false,
            completionAcked: false,
        };
        this.persist();
    }

    addResponse(sessionUuid, response) {
        const entry = this.entries[sessionUuid];
        if (!entry) {
            return;
        }
        entry.responses.push({ ...response, _acked: false });
        this.persist();
    }

    completeSession(sessionUuid, completedAt = new Date().toISOString()) {
        const entry = this.entries[sessionUuid];
        if (!entry) {
            return;
        }
        entry.completed_at = completedAt;
        this.persist();
    }

    /** Session ended early by the adult: keep its answers, but forget it once synced. */
    abandonSession(sessionUuid) {
        const entry = this.entries[sessionUuid];
        if (!entry) {
            return;
        }
        entry.abandoned = true;
        if (!this.needsSync(entry)) {
            delete this.entries[sessionUuid];
        }
        this.persist();
    }

    pendingCount() {
        return Object.values(this.entries).reduce(
            (total, entry) => total + entry.responses.filter((r) => !r._acked).length,
            0,
        );
    }

    needsSync(entry) {
        return !entry.metaAcked
            || entry.responses.some((r) => !r._acked)
            || (entry.completed_at !== null && !entry.completionAcked);
    }

    async flush() {
        if (this.flushing) {
            this.flushAgain = true;
            return;
        }

        const batch = Object.values(this.entries).filter((e) => this.needsSync(e)).slice(0, MAX_SESSIONS_PER_REQUEST);
        if (batch.length === 0) {
            this.lastError = null;
            this.emit();
            return;
        }

        this.flushing = true;
        clearTimeout(this.retryTimer);

        // Snapshot exactly what is sent, so answers added mid-request are not marked acked.
        const sent = batch.map((entry) => ({
            uuid: entry.meta.uuid,
            responseUuids: entry.responses.filter((r) => !r._acked).map((r) => r.client_uuid),
            completed: entry.completed_at !== null,
        }));

        const payload = {
            sessions: batch.map((entry) => ({
                ...entry.meta,
                completed_at: entry.completed_at,
                responses: entry.responses.filter((r) => !r._acked).map(({ _acked, ...r }) => r),
            })),
        };

        try {
            const response = await fetch(this.syncUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken() ?? '',
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 401 || response.status === 419) {
                throw Object.assign(new Error('auth'), { kind: 'auth' });
            }
            if (!response.ok) {
                throw Object.assign(new Error(`HTTP ${response.status}`), { kind: 'server' });
            }

            const result = await response.json();
            this.acknowledge(sent, result);
            this.retryIndex = 0;
            this.lastError = null;
        } catch (error) {
            this.lastError = error.kind ?? (navigator.onLine ? 'server' : 'offline');
            const delay = RETRY_DELAYS_MS[Math.min(this.retryIndex++, RETRY_DELAYS_MS.length - 1)];
            this.retryTimer = setTimeout(() => this.flush(), delay);
        } finally {
            this.flushing = false;
            this.persist();
        }

        if (this.flushAgain || (this.lastError === null && Object.values(this.entries).some((e) => this.needsSync(e)))) {
            this.flushAgain = false;
            this.flush();
        }
    }

    acknowledge(sent, result) {
        const accepted = new Set(result.accepted ?? []);
        const rejected = new Set((result.rejected ?? []).map((r) => r.uuid));

        for (const item of sent) {
            const entry = this.entries[item.uuid];
            if (!entry) {
                continue;
            }

            if (rejected.has(item.uuid)) {
                console.warn('Quiz session rejected by server; discarding', item.uuid, result.rejected);
                delete this.entries[item.uuid];
                continue;
            }

            if (!accepted.has(item.uuid)) {
                continue;
            }

            entry.metaAcked = true;
            const ackedIds = new Set(item.responseUuids);
            entry.responses.forEach((r) => {
                if (ackedIds.has(r.client_uuid)) {
                    r._acked = true;
                }
            });
            if (item.completed) {
                entry.completionAcked = true;
            }

            // Fully synced, finished sessions no longer need to live on the device.
            if ((entry.completionAcked || entry.abandoned) && !this.needsSync(entry)) {
                delete this.entries[item.uuid];
            }
        }
    }

    persist() {
        storage.set(STORAGE_KEY, this.entries);
        this.emit();
    }

    emit() {
        const pending = this.pendingCount();
        let state = pending > 0 ? 'pending' : 'synced';
        if (this.lastError === 'offline') {
            state = 'offline';
        } else if (this.lastError === 'auth') {
            state = 'auth';
        }
        this.onStatus({ state, pending });
    }
}
