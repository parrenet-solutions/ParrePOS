const statusEl = document.getElementById('net-status');
const deviceIdEl = document.getElementById('device-id');
const outboxStatus = document.getElementById('outbox-status');
const syncStatus = document.getElementById('sync-status');

function uuid() {
  if (crypto && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

function getDeviceId() {
  let stored = localStorage.getItem('parrepos_device_id');
  if (!stored) {
    stored = 'dev-' + uuid();
    localStorage.setItem('parrepos_device_id', stored);
  }
  return stored;
}

function updateStatus() {
  statusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
}

window.addEventListener('online', updateStatus);
window.addEventListener('offline', updateStatus);
updateStatus();

const deviceId = getDeviceId();
deviceIdEl.textContent = deviceId;

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/pwa/sw.js');
}

const DB_NAME = 'parrepos-pos';
const STORE = 'outbox';
const MAX_RETRIES = 3;

function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'event_id' });
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function addToOutbox(event) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).put(event);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

async function listOutbox() {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly');
    const req = tx.objectStore(STORE).getAll();
    req.onsuccess = () => resolve(req.result || []);
    req.onerror = () => reject(req.error);
  });
}

async function clearOutbox(eventIds) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    const store = tx.objectStore(STORE);
    eventIds.forEach(id => store.delete(id));
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

document.getElementById('btn-add').addEventListener('click', async () => {
  try {
    const branchId = parseInt(document.getElementById('branch-id').value, 10);
    const registerId = parseInt(document.getElementById('register-id').value, 10);
    const cashSessionId = parseInt(document.getElementById('cash-session-id').value, 10);
    const items = JSON.parse(document.getElementById('items').value);

    const event = {
      event_id: uuid(),
      op_id: 'op-' + uuid(),
      device_id: deviceId,
      type: 'pos.sale.paid',
      idempotency_key: uuid(),
      status: 'PENDING',
      retries: 0,
      last_error: null,
      last_attempt_at: null,
      payload: {
        branch_id: branchId,
        register_id: registerId,
        cash_session_id: cashSessionId,
        items: items
      },
      ts: new Date().toISOString()
    };

    await addToOutbox(event);
    outboxStatus.textContent = 'Evento guardado en outbox.';
  } catch (err) {
    outboxStatus.textContent = 'Error en evento: ' + err.message;
  }
});

document.getElementById('btn-sync').addEventListener('click', async () => {
  const token = document.getElementById('access-token').value.trim();
  if (!token) {
    syncStatus.textContent = 'Access token requerido.';
    return;
  }

  const outbox = await listOutbox();
  const events = outbox.filter(e => (e.status || 'PENDING') !== 'CONFLICT' && (e.retries || 0) < MAX_RETRIES);
  if (events.length === 0) {
    syncStatus.textContent = 'Outbox vacío.';
    return;
  }

  try {
    const now = new Date().toISOString();
    for (const ev of events) {
      ev.status = 'SYNCING';
      ev.last_attempt_at = now;
      await addToOutbox(ev);
    }

    const response = await fetch('/api/v1/sync/events', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      },
      body: JSON.stringify({
        device_id: deviceId,
        events: events
      })
    });
    const data = await response.json();

    if (!response.ok) {
      syncStatus.textContent = 'Error de sync: ' + (data.error?.message || response.status);
      return;
    }

    const results = data.data?.results || [];
    const byId = new Map(results.map(r => [r.event_id, r]));
    const removeIds = [];
    let conflicts = 0;
    let failed = 0;

    for (const ev of events) {
      const res = byId.get(ev.event_id);
      if (!res) {
        continue;
      }

      if (res.status === 'applied' || res.status === 'duplicate') {
        removeIds.push(ev.event_id);
        continue;
      }

      if (res.status === 'conflict') {
        ev.status = 'CONFLICT';
        ev.last_error = res.error || 'conflict';
        await addToOutbox(ev);
        conflicts++;
        continue;
      }

      ev.status = 'FAILED';
      ev.retries = (ev.retries || 0) + 1;
      ev.last_error = res.error || 'failed';
      await addToOutbox(ev);
      failed++;
    }

    await clearOutbox(removeIds);
    syncStatus.textContent = 'Sync completado. ok=' + removeIds.length + ', conflict=' + conflicts + ', failed=' + failed;
  } catch (err) {
    syncStatus.textContent = 'Error de sync: ' + err.message;
  }
});
