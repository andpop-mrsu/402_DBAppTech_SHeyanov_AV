const DB_NAME = 'minesweeper_db_v2';
const STORE_RESULTS = 'results';
const STORE_MOVES = 'moves';

export function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 2);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE_RESULTS)) {
                const store = db.createObjectStore(STORE_RESULTS, {
                    keyPath: 'id',
                    autoIncrement: true
                });
                store.createIndex('by_date', 'date');
            }
            if (!db.objectStoreNames.contains(STORE_MOVES)) {
                const moves = db.createObjectStore(STORE_MOVES, {
                    keyPath: 'id',
                    autoIncrement: true
                });
                moves.createIndex('by_gameId', 'gameId');
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function saveResult(result) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_RESULTS, 'readwrite');
        tx.onerror = () => reject(tx.error);
        const store = tx.objectStore(STORE_RESULTS);
        const req = store.add({
            date: new Date().toISOString(),
            width: result.width,
            height: result.height,
            mines: result.mines,
            seconds: result.seconds,
            victory: result.victory,
            player: result.player || '',
            seed: result.seed || null,
            gameId: result.gameId || null,
            surrendered: result.surrendered || false,
        });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function getAllResults() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_RESULTS, 'readonly');
        tx.onerror = () => reject(tx.error);
        const store = tx.objectStore(STORE_RESULTS);
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result.sort((a, b) => (a.date < b.date ? 1 : -1)));
        req.onerror = () => reject(req.error);
    });
}

export async function clearResults() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([STORE_RESULTS, STORE_MOVES], 'readwrite');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
        tx.objectStore(STORE_RESULTS).clear();
        tx.objectStore(STORE_MOVES).clear();
    });
}

export async function addMove(gameId, move) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_MOVES, 'readwrite');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
        tx.objectStore(STORE_MOVES).add({
            gameId,
            ...move
        });
    });
}

export async function getMoves(gameId) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_MOVES, 'readonly');
        tx.onerror = () => reject(tx.error);
        const idx = tx.objectStore(STORE_MOVES).index('by_gameId');
        const req = idx.getAll(IDBKeyRange.only(gameId));
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}